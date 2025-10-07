<?php

/**
 * Config Upgrade Integration Test Class
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
use VuFind\Config\Upgrade;
use VuFindTest\Integration\ConfigTestCase;

/**
 * Config Upgrade Integration Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class ConfigUpgradeTest extends ConfigTestCase
{
    /**
     * Target upgrade version
     *
     * @var string
     */
    protected static string $targetVersion = '11.0';

    /**
     * Get an upgrade object for the specified source version:
     *
     * @param string $fixture Fixture to use
     *
     * @return Upgrade
     */
    protected function getUpgrader(string $fixture): Upgrade
    {
        $this->setUpLocalConfigDir($fixture);
        $container = $this->getContainerWithConfigRelatedServices();
        return new Upgrade(
            $container->get(PathResolver::class),
            $container->get(ConfigManagerInterface::class),
        );
    }

    /**
     * Test old config backup.
     *
     * @return void
     */
    public function testOldConfigBackup(): void
    {
        $upgrader = $this->getUpgrader('backup');
        $dirContent = scandir($this->localDirPath);
        $this->assertContains('config.ini', $dirContent);
        $oldContent = file_get_contents($this->localDirPath . '/config.ini');

        $upgrader->run(self::$targetVersion);

        $dirContent = scandir($this->localDirPath);
        $hasBackup = false;
        foreach ($dirContent as $file) {
            if (str_starts_with($file, 'config.ini.bak')) {
                $hasBackup = true;
                $backupContent = file_get_contents($this->localDirPath . '/' . $file);
                $this->assertEquals($oldContent, $backupContent);
            }
        }
        $this->assertTrue($hasBackup);
    }

    /**
     * Upgrade test provider.
     *
     * @return array[]
     */
    public static function upgradeTestProvider(): array
    {
        return [
            'changing-upgrade' => [
                'defaultgenerator',
                'VuFind ' . self::$targetVersion,
            ],
            'unchanging-upgrade' => [
                'customgenerator',
                'Custom Generator',
            ],
        ];
    }

    /**
     * Test upgrade.
     *
     * @param string $fixture           Fixture
     * @param string $expectedGenerator Expected Generator
     *
     * @return void
     *
     * @dataProvider upgradeTestProvider
     */
    public function testUpgrade($fixture, $expectedGenerator): void
    {
        $baseConfig = $this->readConfig('config', $this->baseDirPath);
        unset($baseConfig['Site']['generator']);

        $upgrader = $this->getUpgrader($fixture);
        $upgrader->run(self::$targetVersion);
        $upgradedConfig = $this->readConfig('config');
        $this->assertEquals(
            $expectedGenerator,
            $upgradedConfig['Site']['generator']
        );
        unset($upgradedConfig['Site']['generator']);
        $this->assertEquals($baseConfig, $upgradedConfig);
    }

    /**
     * Test that legacy RecordDataFormatter.ini is moved to RecordDataFormatter/DefaultRecord.ini.
     *
     * @return void
     */
    public function testMovingOfRecordDataFormatterConfig(): void
    {
        $upgrader = $this->getUpgrader('legacy-record-data-formatter-config');
        $oldConfig = $this->readConfig('RecordDataFormatter');
        $upgrader->run(self::$targetVersion);
        $this->assertFileDoesNotExist($this->localDirPath . '/RecordDataFormatter.ini');
        $movedConfig = $this->readConfig('RecordDataFormatter/DefaultRecord');
        $this->assertEquals(
            $oldConfig,
            $movedConfig
        );
    }
}
