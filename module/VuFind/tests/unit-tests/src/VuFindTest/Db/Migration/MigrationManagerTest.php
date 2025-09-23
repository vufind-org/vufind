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

use PHPUnit\Framework\MockObject\MockObject;
use VuFind\Db\Connection;
use VuFind\Db\Migration\MigrationLoader;
use VuFind\Db\Migration\MigrationManager;
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
class MigrationManagerTest extends \PHPUnit\Framework\TestCase
{
    use FixtureTrait;

    /**
     * Get a default mock Connection for use in getMockMigrationManager.
     *
     * @return MockObject&Connection
     */
    protected function getMockConnection(): MockObject&Connection
    {
        $mockConnection = $this->createMock(Connection::class);
        $mockConnection->expects($this->once())->method('getDatabasePlatform')->willReturn(new \stdClass());
        return $mockConnection;
    }

    /**
     * Create a mock MigrationManager.
     *
     * @param string[]         $methods    Array of methods to mock
     * @param ?Connection      $connection Database connection (null for default mock)
     * @param ?MigrationLoader $loader     Migration loader (null for default mock)
     * @param string           $version    Target version number
     *
     * @return MockObject&MigrationManager
     */
    protected function getMockMigrationManager(
        array $methods,
        ?Connection $connection = null,
        ?MigrationLoader $loader = null,
        string $version = '11.0'
    ): MockObject&MigrationManager {
        $constructorArgs = [
            $connection ?? $this->getMockConnection(),
            $loader ?? $this->createMock(MigrationLoader::class),
            $version,
        ];
        return $this->getMockBuilder(MigrationManager::class)
            ->setConstructorArgs($constructorArgs)
            ->onlyMethods($methods)
            ->getMock();
    }

    /**
     * Test getMigrations() sort behavior.
     *
     * @return void
     */
    public function testGetMigrationsSorting(): void
    {
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
        $manager = $this->getMockMigrationManager(['getAppliedMigrations'], loader: $loader);
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

    /**
     * Test that applyMigrations calls applyMigration appropriately.
     *
     * @return void
     */
    public function testApplyMigrations(): void
    {
        $connection = $this->createMock(Connection::class);
        $manager = $this->getMockMigrationManager(['applyMigration']);
        $manager->expects($this->exactly(3))->method('applyMigration')->willReturnCallback(
            function (string $migration, ?Connection $incomingConnection) use ($connection) {
                $this->assertEquals($connection, $incomingConnection);
                return $migration;
            }
        );
        $this->assertEquals('123', $manager->applyMigrations(['1', '2', '3'], $connection));
    }

    /**
     * Test flow of applyMigration() while mocking all database interactions.
     *
     * @return void
     */
    public function testApplyMigration(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->exactly(2))->method('executeQuery');
        $basePath = $this->getFixturePath('db-migrations');
        $loader = $this->createMock(MigrationLoader::class);
        $loader->expects($this->once())->method('getMigrationDirForPlatform')->willReturn($basePath);
        $loader->expects($this->once())->method('splitSqlIntoStatements')->with('')
            ->willReturn(['execute chunk 1', 'execute chunk 2']);
        $manager = $this->getMockMigrationManager(['logMigrationEvent', 'cleanUpMigrationEvents'], loader: $loader);
        $manager->expects($this->exactly(4))->method('logMigrationEvent')->willReturnCallback(
            function ($incomingConnection, $name, $msg) use ($connection) {
                $this->assertEquals($connection, $incomingConnection);
                return "log $name : $msg\n";
            }
        );
        $shortName = '10.1/001-dummy.sql';
        $manager->expects($this->once())->method('cleanUpMigrationEvents')->with($connection, $shortName)
            ->willReturn('cleanup');
        $result = $manager->applyMigration($basePath . '/' . $shortName, $connection);
        $this->assertEquals(
            <<<EXPECTED_RESULT
                log 10.1/001-dummy.sql : start
                log 10.1/001-dummy.sql : writing chunk 0
                execute chunk 1;
                log 10.1/001-dummy.sql : writing chunk 1
                execute chunk 2;
                log 10.1/001-dummy.sql : success
                cleanup
                EXPECTED_RESULT,
            $result
        );
    }

    /**
     * Test getShortMigrationName().
     *
     * @return void
     */
    public function testGetShortMigrationName(): void
    {
        $loader = $this->createMock(MigrationLoader::class);
        $loader->expects($this->once())->method('getMigrationDirForPlatform')->willReturn('/base/path/foo');
        $manager = $this->getMockMigrationManager([], loader: $loader);
        $this->assertEquals('10.0/001-foo.sql', $manager->getShortMigrationName('/base/path/foo/10.0/001-foo.sql'));
    }

    /**
     * Test markMigrationApplied().
     *
     * @return void
     */
    public function testMarkMigrationApplied(): void
    {
        $shortName = 'foo';
        $longName = "/base/path/$shortName";
        $connection = null;
        $resultSql = 'fake sql goes here';
        $manager = $this->getMockMigrationManager(['getShortMigrationName', 'logMigrationEvent']);
        $manager->expects($this->once())->method('getShortMigrationName')->with($longName)->willReturn($shortName);
        $manager->expects($this->once())->method('logMigrationEvent')->with($connection, $shortName, 'success')
            ->willReturn($resultSql);
        $this->assertEquals($resultSql, $manager->markMigrationApplied($longName, $connection));
    }
}
