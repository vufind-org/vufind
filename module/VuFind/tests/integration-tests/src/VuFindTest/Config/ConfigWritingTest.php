<?php

/**
 * Config Writing Integration Test Class.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Tests
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Config;

use VuFind\Config\ConfigManagerInterface;
use VuFind\Config\PathResolver;
use VuFindTest\Integration\ConfigTestCase;

/**
 * Config Writing Integration Test Class.
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
     * Write with inheritance fails test provider.
     *
     * @return \Iterator
     */
    public static function writeWithInheritanceFailsTestProvider(): \Iterator
    {
        yield 'ini Parent_Config' => [
            'inheritance',
            'unit-test-child',
            'Can not write INI configuration with inheritance.',
        ];
        yield 'ini include::' => [
            'include',
            'parent',
            'Can not write INI configuration with include:: statement.',
        ];
        yield 'ini include:: section' => [
            'include',
            'child-section',
            'Can not write INI configuration with include:: statement.',
        ];
        yield 'ini @include' => [
            'ini-file-with-include',
            'test',
            'Can not write INI configuration with @include statement.',
        ];
        yield 'ini @include section' => [
            'ini-file-with-include',
            'test-section',
            'Can not write INI configuration with @include statement.',
        ];
    }

    /**
     * Test writing with inheritance fails.
     *
     * @param string $fixture          Fixture
     * @param string $configName       Config name
     * @param string $exceptionMessage Expected exception message
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('writeWithInheritanceFailsTestProvider')]
    public function testWritingWithInheritanceFails(string $fixture, string $configName, string $exceptionMessage): void
    {
        $this->setUpLocalConfigDir($fixture);
        $container = $this->getContainerWithConfigRelatedServices(
            baseDir: $this->getFixtureDir() . 'configs/' . $fixture,
            baseSubDir: ''
        );
        $pathResolver = $container->get(PathResolver::class);
        $configManager = $container->get(ConfigManagerInterface::class);

        $baseDirPath = $pathResolver->getBaseConfigDirPath();
        $baseConfigLocation = $pathResolver->getMatchingConfigLocation($baseDirPath, $configName);

        $destinationLocation = clone $baseConfigLocation;
        $destinationLocation->setBasePath($this->localDirPath);

        $config = $configManager->loadConfigFromLocation($baseConfigLocation);

        $this->expectException(\VuFind\Exception\ConfigException::class);
        $this->expectExceptionMessage($exceptionMessage);
        $configManager->writeConfig($destinationLocation, $config, $baseConfigLocation);
    }

    /**
     * Write test provider.
     *
     * @return \Iterator
     */
    public static function writeTestProvider(): \Iterator
    {
        yield 'generic file handler' => [
            'generic-file',
            'config',
        ];
        yield 'ini handler' => [
            'ini',
            'config',
        ];
        yield 'dir handler' => [
            'dir',
            'baseDir',
        ];
    }

    /**
     * Test writing.
     *
     * @param string $fixture    Fixture
     * @param string $configName Config name
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('writeTestProvider')]
    public function testWriting(string $fixture, string $configName): void
    {
        $container = $this->getContainerWithConfigRelatedServices(
            baseDir: $this->getFixtureDir() . 'configs/write/' . $fixture,
            baseSubDir: ''
        );
        $pathResolver = $container->get(PathResolver::class);
        $configManager = $container->get(ConfigManagerInterface::class);

        $baseDirPath = $pathResolver->getBaseConfigDirPath();
        $baseConfigLocation = $pathResolver->getMatchingConfigLocation($baseDirPath, $configName);

        $destinationLocation = clone $baseConfigLocation;
        $destinationLocation->setBasePath($this->localDirPath);

        $config = $configManager->loadConfigFromLocation($baseConfigLocation);
        $configManager->writeConfig($destinationLocation, $config, $baseConfigLocation);

        $this->assertDirsEqual($baseDirPath, $this->localDirPath);
    }

    /**
     * Test writing when the base and destination locations are identical.
     *
     * @return void
     */
    public function testWritingWithIdenticalBaseAndDestination(): void
    {
        $this->setUpLocalConfigDir('write/ini');
        $container = $this->getContainerWithConfigRelatedServices(
            baseDir: $this->getFixtureDir() . 'configs/write/ini',
            baseSubDir: ''
        );
        $pathResolver = $container->get(PathResolver::class);
        $configManager = $container->get(ConfigManagerInterface::class);

        $configLocation = $pathResolver->getForcedLocalConfigLocation('config');
        $config = $configManager->loadConfigFromLocation($configLocation);
        $config['Section1']['key1'] = 'newval';

        $configManager->writeConfig($configLocation, $config, $configLocation);

        $result = $this->readConfig('config');
        $this->assertSame('newval', $result['Section1']['key1']);
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
                $this->assertSame($expectedFileContent, $actualFileContent);
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
