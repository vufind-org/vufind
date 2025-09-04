<?php

/**
 * Config Writing Integration Test Class
 *
 * PHP version 8
 *
 * Copyright (C) Hebis Verbundzentrale 2025.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Tests
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Config;

use VuFind\Config\ConfigManager;
use VuFind\Config\PathResolver;
use VuFindTest\Integration\ConfigTestCase;

/**
 * Config Writing Integration Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class ConfigWritingTest extends ConfigTestCase
{
    /**
     * Upgrade test provider.
     *
     * @return array[]
     */
    public static function upgradeTestProvider(): array
    {
        return [
            'generic file handler' => [
                'generic-file',
                'config',
            ],
            'ini handler' => [
                'ini',
                'config',
            ],
            'dir handler' => [
                'dir',
                'baseDir',
            ],
        ];
    }

    /**
     * Test writing.
     *
     * @param string $fixture    Fixture
     * @param string $configName Config name
     *
     * @return void
     *
     * @dataProvider upgradeTestProvider
     */
    public function testWriting(string $fixture, string $configName): void
    {
        $container = $this->getContainerWithConfigRelatedServices(
            baseDir: $this->getFixtureDir() . 'configs/write/' . $fixture,
            baseSubDir: ''
        );
        $pathResolver = $container->get(PathResolver::class);
        $configManager = $container->get(ConfigManager::class);

        $baseDirPath = $pathResolver->getBaseConfigDirPath();
        $baseConfigLocation = $pathResolver->getMatchingConfigLocation($baseDirPath, $configName);

        $destinationLocation = clone $baseConfigLocation;
        $destinationLocation->setBasePath($this->localDirPath);

        $config = $configManager->loadConfigFromLocation($baseConfigLocation);
        $configManager->writeConfig($destinationLocation, $config, $baseConfigLocation);

        $this->assertDirsEqual($baseDirPath, $this->localDirPath);
    }

    /**
     * Assert that two configuration dirs are equal.
     *
     * @param string $expected Expected directory
     * @param string $actual   Actual directory
     *
     * @return void
     */
    protected function assertDirsEqual(string $expected, string $actual): void
    {
        $this->assertDirectoryExists($expected);
        $this->assertDirectoryExists($actual);

        $expectedContent = scandir($expected);
        $actualContent = scandir($actual);
        $this->assertEquals($expectedContent, $actualContent);

        foreach ($expectedContent as $item) {
            if ($item == '.' || $item == '..') {
                continue;
            }
            if (is_dir($expected . '/' . $item)) {
                $this->assertDirsEqual($expected . '/' . $item, $actual . '/' . $item);
            } else {
                $expectedFileContent = $this->readFileAndNormalizeWhitespace($expected . '/' . $item);
                $actualFileContent = $this->readFileAndNormalizeWhitespace($actual . '/' . $item);
                $this->assertEquals($expectedFileContent, $actualFileContent);
            }
        }
    }

    /**
     * Read file and clean up whitespaces.
     *
     * @param string $path File path
     *
     * @return string
     */
    protected function readFileAndNormalizeWhitespace(string $path): string
    {
        $content = file_get_contents($path);
        return trim(preg_replace('/\s+/', ' ', $content));
    }
}
