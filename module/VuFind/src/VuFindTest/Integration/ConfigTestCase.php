<?php

/**
 * Abstract base class for config integration test cases.
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

namespace VuFindTest\Integration;

use VuFind\Exception\FileAccess;
use VuFind\Feature\DirUtilityTrait;
use VuFindTest\Feature\ConfigRelatedServicesTrait;
use VuFindTest\Feature\FixtureTrait;
use VuFindTest\Feature\LiveDetectionTrait;

/**
 * Abstract base class for config integration test cases.
 *
 * @category VuFind
 * @package  Tests
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
abstract class ConfigTestCase extends \PHPUnit\Framework\TestCase
{
    use LiveDetectionTrait;
    use FixtureTrait;
    use DirUtilityTrait;
    use ConfigRelatedServicesTrait;

    /**
     * Path to base configurations
     *
     * @var string
     */
    protected string $baseDirPath;

    /**
     * Path to local dir configurations
     *
     * @var string
     */
    protected string $localDirPath;

    /**
     * Path to local dir backup
     *
     * @var string
     */
    protected string $localDirBackupPath;

    /**
     * Set local dir path.
     *
     * @param string $localDirPath Local dir path
     *
     * @return void
     */
    protected function setLocalDirPath(string $localDirPath): void
    {
        $this->localDirPath = $localDirPath;
        $this->localDirBackupPath = $localDirPath . '.bak';
    }

    /**
     * Setup local dir with fixture.
     *
     * @param string $fixture Fixture to use
     *
     * @return void
     */
    protected function setUpLocalConfigDir(string $fixture): void
    {
        $fixtureDir = realpath($this->getFixtureDir() . 'configs/' . $fixture);
        if (is_dir($this->localDirPath)) {
            self::rmDir($this->localDirPath);
        }
        self::cpDir($fixtureDir, $this->localDirPath);
    }

    /**
     * Read the current config.
     *
     * @param string  $config Config name
     * @param ?string $path   Optional alternative path to config directory
     *
     * @return array
     */
    protected function readConfig(string $config, ?string $path = null): array
    {
        $configFile = ($path ?? $this->localDirPath) . '/' . $config . '.ini';
        $result = parse_ini_file($configFile, true);
        if ($result === false) {
            throw new FileAccess('Could not read config file: ' . $configFile);
        }
        return $result;
    }

    /**
     * Standard setup method.
     *
     * @return void
     */
    public function setUp(): void
    {
        // Give up if we're not running in CI:
        if (!$this->continuousIntegrationRunning()) {
            $this->markTestSkipped('Continuous integration not running.');
            return;
        }

        $pathResolver = $this->getPathResolver();
        $this->baseDirPath = $pathResolver->getBaseConfigDirPath();
        $this->setLocalDirPath($pathResolver->getLocalConfigDirPath());
        if ($this->localDirPath === null) {
            $this->markTestSkipped('No local config dir configured.');
        }

        // create backup of local config dir
        if (is_dir($this->localDirPath)) {
            rename($this->localDirPath, $this->localDirBackupPath);
            mkdir($this->localDirPath);
        }
    }

    /**
     * Standard teardown method.
     *
     * @return void
     */
    public function tearDown(): void
    {
        // restore backup of local config dir
        if (isset($this->localDirPath) && is_dir($this->localDirPath)) {
            self::rmDir($this->localDirPath);
        }
        if (isset($this->localDirBackupPath) && is_dir($this->localDirBackupPath)) {
            rename($this->localDirBackupPath, $this->localDirPath);
        }
    }
}
