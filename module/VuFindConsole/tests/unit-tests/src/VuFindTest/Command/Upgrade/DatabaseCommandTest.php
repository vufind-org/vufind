<?php

/**
 * Upgrade/Database command test.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Command\Upgrade;

use Closure;
use Symfony\Component\Console\Tester\CommandTester;
use VuFind\Cache\Manager as CacheManager;
use VuFind\Db\Connection;
use VuFind\Db\ConnectionFactory;
use VuFind\Db\Migration\MigrationManager;
use VuFindConsole\Command\Upgrade\DatabaseCommand;

/**
 * Upgrade/Database command test.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class DatabaseCommandTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Data provider for SQL-only or non-SQL-only scenarios.
     *
     * @return array[]
     */
    public static function sqlOnlyProvider(): array
    {
        return [
            'sql-only' => [true],
            'not sql-only' => [false],
        ];
    }

    /**
     * Test the simplest possible success case.
     *
     * @param bool $sqlOnly Test in sql-only mode?
     *
     * @return void
     *
     * @dataProvider sqlOnlyProvider
     */
    public function testSimpleSuccess(bool $sqlOnly): void
    {
        $connection = $this->createMock(Connection::class);
        $migrations = ['1', '2', '3'];
        $manager = $this->createMock(MigrationManager::class);
        $manager->expects($this->once())->method('determineOldVersion')->willReturn('10.0');
        $manager->expects($this->once())->method('getMigrations')->with('10.0')->willReturn($migrations);
        $manager->expects($this->once())->method('applyMigrations')
            ->with($migrations, $sqlOnly ? null : $connection)
            ->willReturn('123');
        $factory = $this->createMock(ConnectionFactory::class);
        if (!$sqlOnly) {
            $factory->expects($this->once())->method('getConnection')->with(null, null)->willReturn($connection);
        }
        $cacheManager = $this->createMock(CacheManager::class);
        $cacheManager->expects($this->once())
            ->method('getCacheDir')
            ->with(false)
            ->willReturn('CACHEDIR/');
        $command = new DatabaseCommand(Closure::fromCallable(fn () => $manager), $factory, $cacheManager);
        $commandTester = new CommandTester($command);
        $commandTester->execute($sqlOnly ? ['--sql-only' => true] : []);
        if ($sqlOnly) {
            $expectedMsg = "123\n"
                . "\nPlease clear the object cache (CACHEDIR/objects) after applying the migrations to ensure that the"
                . " metadata is up to date.\n\n";
        } else {
            $expectedMsg = "Successfully upgraded database.\n"
                . "\nPlease clear the object cache (CACHEDIR/objects) now to ensure that the metadata is up to date."
                . "\n\n";
        }
        $this->assertEquals(
            $expectedMsg,
            $commandTester->getDisplay()
        );
        $this->assertEquals(0, $commandTester->getStatusCode());
    }

    /**
     * Test the "nothing to do" scenario.
     *
     * @return void
     */
    public function testNothingToDo(): void
    {
        $manager = $this->createMock(MigrationManager::class);
        $factory = $this->createMock(ConnectionFactory::class);
        $cacheManager = $this->createMock(CacheManager::class);
        $command = new DatabaseCommand(Closure::fromCallable(fn () => $manager), $factory, $cacheManager);
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);
        $this->assertEquals(
            "Nothing to do.\n",
            $commandTester->getDisplay()
        );
        $this->assertEquals(0, $commandTester->getStatusCode());
    }
}
