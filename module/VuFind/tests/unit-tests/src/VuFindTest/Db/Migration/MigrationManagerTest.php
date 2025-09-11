<?php

/**
 * Database Migration Manager Test Class
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

use VuFind\Db\Connection;
use VuFind\Db\Migration\MigrationLoader;
use VuFind\Db\Migration\MigrationManager;

/**
 * Database Migration Loader Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class MigrationManagerTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test getMigrations() sort behavior.
     *
     * @return void
     */
    public function testGetMigrationsSorting(): void
    {
        $mockConnection = $this->createMock(Connection::class);
        $mockConnection->expects($this->once())->method('getDatabasePlatform')->willReturn(new \stdClass());
        $basePath = '/fake/path';
        // Test data is intentionally out of order, so we can test that sorting behaves as intended.
        $testData = [
            "$basePath/10.0" => ['001-foo', '002-foo', '003-foo'],
            "$basePath/9.0" => ['001-bar', '002-baz'],
            "$basePath/11.0" => ['001-baz'],
        ];
        $loader = $this->createMock(MigrationLoader::class);
        $loader->expects($this->once())->method('getMigrationDirForPlatform')->willReturn($basePath);
        $loader->expects($this->once())->method('getMigrationSubdirectoriesMatchingVersion')->willReturn(
            array_keys($testData)
        );
        $loader->method('getMigrationsFromDir')->willReturnCallback(
            fn ($version) => array_map(fn ($file) => "$version/$file.sql", $testData[$version])
        );
        $manager = $this->getMockBuilder(MigrationManager::class)
            ->setConstructorArgs([$mockConnection, $loader, '11.0'])
            ->onlyMethods(['getAppliedMigrations'])
            ->getMock();
        $manager->expects($this->any())->method('getAppliedMigrations')->willReturn([]);
        $this->assertEquals(
            [
                '/fake/path/9.0/001-bar.sql',
                '/fake/path/9.0/002-baz.sql',
                '/fake/path/10.0/001-foo.sql',
                '/fake/path/10.0/002-foo.sql',
                '/fake/path/10.0/003-foo.sql',
                '/fake/path/11.0/001-baz.sql',
            ],
            $manager->getMigrations('9.0')
        );
    }
}
