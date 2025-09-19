<?php

/**
 * Database Builder Test Class
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

namespace VuFindTest\Db;

use PHPUnit\Framework\MockObject\MockObject;
use VuFind\Config\Version;
use VuFind\Db\Connection;
use VuFind\Db\ConnectionFactory;
use VuFind\Db\DbBuilder;
use VuFind\Db\Migration\MigrationLoader;

use function count;

/**
 * Database Builder Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class DbBuilderTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Get a mock database connection with a working quote method.
     *
     * @return MockObject&Connection
     */
    protected function getMockConnectionWithQuote(): MockObject&Connection
    {
        $mockConnection = $this->createMock(Connection::class);
        $mockConnection->expects($this->once())->method('quote')->willReturnCallback(fn ($str) => "'$str'");
        return $mockConnection;
    }

    /**
     * Data provider for testPortHandling().
     *
     * @return array[]
     */
    public static function portHandlingProvider(): array
    {
        return [
            'port' => ['localhost:1234', 'localhost', '1234'],
            'no port' => ['localhost', 'localhost', null],
        ];
    }

    /**
     * Test port number processing.
     *
     * @param string  $host         Host string
     * @param string  $expectedHost Expected hostname parsed from string
     * @param ?string $expectedPort Expected port number (or null) parsed from string
     *
     * @return void
     *
     * @dataProvider portHandlingProvider
     */
    public function testPortHandling(string $host, string $expectedHost, ?string $expectedPort): void
    {
        $mockConnectionFactory = $this->createMock(ConnectionFactory::class);
        $mockLoader = $this->createMock(MigrationLoader::class);
        $builder = $this->getMockBuilder(DbBuilder::class)->onlyMethods(['getRootDatabaseConnection'])
            ->setConstructorArgs([$mockConnectionFactory, $mockLoader])
            ->getMock();
        $builder->expects($this->exactly(2))->method('getRootDatabaseConnection')
            ->with('mysql', $expectedHost, 'root', '', $expectedPort)
            ->willReturn($this->getMockConnectionWithQuote());
        $builder->build('newName', 'newUser', 'newPass', 'mysql', $host);
    }

    /**
     * Data provider for testPreCommands().
     *
     * @return array
     */
    public static function preCommandsProvider(): array
    {
        $expectedMySql = [
            'CREATE DATABASE name;',
            "CREATE USER 'user'@'localhost' IDENTIFIED BY 'pass';",
            "GRANT SELECT,INSERT,UPDATE,DELETE ON name.* TO 'user'@'localhost' WITH GRANT OPTION;",
            'FLUSH PRIVILEGES;',
            'USE name;',
        ];
        $expectedPgSql = [
            'CREATE DATABASE name;',
            "ALTER DATABASE name SET bytea_output='escape';",
            "CREATE USER user WITH PASSWORD 'pass';",
            'GRANT ALL PRIVILEGES ON DATABASE name TO user;',
        ];
        return [
            'mariadb, sql-only' => ['mariadb', $expectedMySql, true],
            'mariadb, not sql-only' => ['mariadb', $expectedMySql, false],
            'mysql, sql-only' => ['mysql', $expectedMySql, true],
            'mysql, not sql-only' => ['mysql', $expectedMySql, false],
            'pgsql, sql-only' => ['pgsql', $expectedPgSql, true],
            'pgsql, not sql-only' => ['pgsql', $expectedPgSql, false],
        ];
    }

    /**
     * Test the pre-commands.
     *
     * @param string   $driver           Database driver to use
     * @param string[] $expectedCommands Expected pre-commands
     * @param bool     $sqlOnly          Test in SQL-only mode?
     *
     * @return void
     *
     * @dataProvider preCommandsProvider
     */
    public function testPreCommands(string $driver, array $expectedCommands, bool $sqlOnly): void
    {
        $factory = $this->createMock(ConnectionFactory::class);
        if ($sqlOnly) {
            $factory->expects($this->never())->method('getConnectionFromOptions');
        } else {
            $mockConnection = $this->getMockConnectionWithQuote();
            $mockConnection->expects($this->exactly(count($expectedCommands)))->method('executeQuery');
            $factory->expects($this->exactly(1))->method('getConnectionFromOptions')->willReturn($mockConnection);
        }
        $builder = new DbBuilder($factory, $this->createMock(MigrationLoader::class));
        $result = $builder->build('name', 'user', 'pass', $driver, returnSqlOnly: $sqlOnly, steps: ['pre']);
        $this->assertEquals(implode("\n", $expectedCommands), trim($result));
    }

    /**
     * Data provider for testMainCommands().
     *
     * @return array
     */
    public static function mainCommandsProvider(): array
    {
        $mysql = APPLICATION_PATH . '/module/VuFind/sql/mysql.sql';
        $pgsql = APPLICATION_PATH . '/module/VuFind/sql/pgsql.sql';
        return ['mysql' => ['mysql', $mysql], 'mariadb' => ['mysql', $mysql], 'pgsql' => ['pgsql', $pgsql]];
    }

    /**
     * Test the main commands.
     *
     * @param string $driver       Database driver to use
     * @param string $expectedFile File containing expected commands
     *
     * @return void
     *
     * @dataProvider mainCommandsProvider
     */
    public function testMainCommands(string $driver, string $expectedFile): void
    {
        $factory = $this->createMock(ConnectionFactory::class);
        $factory->expects($this->never())->method('getConnectionFromOptions');
        $builder = new DbBuilder($factory, $this->createMock(MigrationLoader::class));
        $result = $builder->build('name', 'user', 'pass', $driver, returnSqlOnly: true, steps: ['main']);
        $this->assertEquals(trim(file_get_contents($expectedFile)), trim($result));
    }

    /**
     * Data provider for testPostCommands().
     *
     * @return array
     */
    public static function postCommandsProvider(): array
    {
        $version = Version::getBuildVersion();
        $expectedMySql = [
            "INSERT INTO migrations(name, status, target_version) VALUES ('mysql.sql', 'success', '$version');",
            "INSERT INTO migrations(name, status, target_version) VALUES ('11.0/001-fake.sql', 'success', '$version');",
            "INSERT INTO migrations(name, status, target_version) VALUES ('11.0/002-fake.sql', 'success', '$version');",
        ];
        $expectedPgSql = [
            'GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA public TO user;',
            'GRANT ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA public TO user;',
            "INSERT INTO migrations(name, status, target_version) VALUES ('pgsql.sql', 'success', '$version');",
            "INSERT INTO migrations(name, status, target_version) VALUES ('11.0/001-fake.sql', 'success', '$version');",
            "INSERT INTO migrations(name, status, target_version) VALUES ('11.0/002-fake.sql', 'success', '$version');",
        ];
        return [
            'mariadb, sql-only' => ['mariadb', $expectedMySql, true],
            'mariadb, not sql-only' => ['mariadb', $expectedMySql, false],
            'mysql, sql-only' => ['mysql', $expectedMySql, true],
            'mysql, not sql-only' => ['mysql', $expectedMySql, false],
            'pgsql, sql-only' => ['pgsql', $expectedPgSql, true],
            'pgsql, not sql-only' => ['pgsql', $expectedPgSql, false],
        ];
    }

    /**
     * Test the post-commands.
     *
     * @param string   $driver           Database driver to use
     * @param string[] $expectedCommands Expected post-commands
     * @param bool     $sqlOnly          Test in SQL-only mode?
     *
     * @return void
     *
     * @dataProvider postCommandsProvider
     */
    public function testPostCommands(string $driver, array $expectedCommands, bool $sqlOnly): void
    {
        $factory = $this->createMock(ConnectionFactory::class);
        if ($sqlOnly) {
            $factory->expects($this->never())->method('getConnectionFromOptions');
        } else {
            $mockConnection = $this->createMock(Connection::class);
            $mockConnection->expects($this->exactly(count($expectedCommands)))->method('executeQuery');
            $factory->expects($this->exactly(1))->method('getConnectionFromOptions')->willReturn($mockConnection);
        }
        $loader = $this->createMock(MigrationLoader::class);
        $migrationDir = '/dummy/value/for/migration/directory';
        $migrationSubdir = "$migrationDir/11.0";
        $loader->expects($this->once())->method('getMigrationDirForPlatform')->with($driver)
            ->willReturn($migrationDir);
        $loader->expects($this->once())->method('getMigrationSubdirectoriesMatchingVersion')
            ->with(Version::getBuildVersion(), $migrationDir)
            ->willReturn([$migrationSubdir]);
        $loader->expects($this->once())->method('getMigrationsFromDir')->with($migrationSubdir)
            ->willReturn(["$migrationSubdir/001-fake.sql", "$migrationSubdir/002-fake.sql"]);
        $builder = new DbBuilder($factory, $loader);
        $result = $builder->build('name', 'user', 'pass', $driver, returnSqlOnly: $sqlOnly, steps: ['post']);
        $this->assertEquals(implode("\n", $expectedCommands), trim($result));
    }
}
