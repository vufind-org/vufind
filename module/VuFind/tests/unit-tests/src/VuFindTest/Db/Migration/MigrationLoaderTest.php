<?php

/**
 * Database Migration Loader Test Class
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2025.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Db\Migration;

use VuFind\Db\Migration\MigrationLoader;
use VuFindTest\Feature\FixtureTrait;

/**
 * Database Migration Loader Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class MigrationLoaderTest extends \PHPUnit\Framework\TestCase
{
    use FixtureTrait;

    /**
     * Data provider for testGetMigrationDirForPlatform().
     *
     * @return array
     */
    public static function getMigrationDirForPlatformProvider(): array
    {
        $basePath = APPLICATION_PATH . '/module/VuFind/sql/migrations';
        return [
            ['postgres', "$basePath/pgsql"],
            ['pgsql', "$basePath/pgsql"],
            ['mysql', "$basePath/mysql"],
            ['mariadb', "$basePath/mysql"],
        ];
    }

    /**
     * Test getMigrationDirForPlatform().
     *
     * @param string $platform    Platform to test
     * @param string $expectedDir Expected result
     *
     * @return void
     *
     * @dataProvider getMigrationDirForPlatformProvider
     */
    public function testGetMigrationDirForPlatform(string $platform, string $expectedDir): void
    {
        $loader = new MigrationLoader();
        $this->assertEquals($expectedDir, $loader->getMigrationDirForPlatform($platform));
    }

    /**
     * Data provider for testGetMigrationSubdirectoriesMatchingVersion().
     *
     * @return array[]
     */
    public static function getMigrationSubdirectoriesMatchingVersionProvider(): array
    {
        return [
            ['10.1', ['10.1', '11.0', '11.1']],
            ['11.0', ['11.0', '11.1']],
            ['11.1', ['11.1']],
            ['11.2', []],
        ];
    }

    /**
     * Test getMigrationSubdirectoriesMatchingVersion().
     *
     * @param string $version      Version to test
     * @param array  $expectedDirs Expected matching versions
     *
     * @return void
     *
     * @dataProvider getMigrationSubdirectoriesMatchingVersionProvider
     */
    public function testGetMigrationSubdirectoriesMatchingVersion(string $version, array $expectedDirs): void
    {
        $fixtureDir = $this->getFixtureDir() . 'db-migrations';
        $loader = new MigrationLoader();
        $this->assertEquals(
            array_map(fn ($dir) => "$fixtureDir/$dir", $expectedDirs),
            $loader->getMigrationSubdirectoriesMatchingVersion($version, $fixtureDir)
        );
    }

    /**
     * Test getMigrationSubdirectoriesMatchingVersion().
     *
     * @return void
     */
    public function testGetMigrationsFromDir(): void
    {
        $loader = new MigrationLoader();
        $fixtureDir = $this->getFixtureDir() . 'db-migrations';
        $baseDir = "$fixtureDir/11.0";
        $this->assertEquals(
            ["$baseDir/001-dummy.sql", "$baseDir/002-dummy.sql"],
            $loader->getMigrationsFromDir($baseDir)
        );
    }

    /**
     * Test splitSqlIntoStatements().
     *
     * @return void
     */
    public function testSplitSqlIntoStatements(): void
    {
        $loader = new MigrationLoader();
        $statement1 = "select * from table where field='has;semicolon';";
        $statement2 = 'drop table foo;';
        $sql = "$statement1\n$statement2\r$statement1     \n$statement2";
        $this->assertEquals(
            [$statement1, $statement2, $statement1, $statement2],
            array_map(
                fn ($line) => "$line;", // restore semicolons for easier assertion
                $loader->splitSqlIntoStatements($sql)
            )
        );
    }
}
