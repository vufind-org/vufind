<?php

/**
 * Install/Database command test.
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

namespace VuFindTest\Command\Install;

use Symfony\Component\Console\Tester\CommandTester;
use VuFind\Db\DbBuilder;
use VuFindConsole\Command\Install\DatabaseCommand;

/**
 * Install/Database command test.
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
     * Test the simplest possible success case.
     *
     * @return void
     */
    public function testSimpleSuccess(): void
    {
        $builder = $this->createMock(DbBuilder::class);
        $builder->expects($this->atLeast(0))->method('build')->with(
            'name',
            'user',
            'pass',
            'mysql',
            'localhost',
            'localhost',
            'root',
            '',
            false,
            []
        )->willReturn('foo');
        $command = new DatabaseCommand($builder);
        $commandTester = new CommandTester($command);
        $commandTester->execute(['newName' => 'name', 'newUser' => 'user', 'newPass' => 'pass']);
        $this->assertEquals(
            "Successfully created database.\n",
            $commandTester->getDisplay()
        );
        $this->assertEquals(0, $commandTester->getStatusCode());
    }

    /**
     * Test SQL-only mode.
     *
     * @return void
     */
    public function testSqlOnly(): void
    {
        $builder = $this->createMock(DbBuilder::class);
        $builder->expects($this->atLeast(0))->method('build')->with(
            'name',
            'user',
            'pass',
            'mysql',
            'localhost',
            'localhost',
            'root',
            '',
            true,
            []
        )->willReturn('foo');
        $command = new DatabaseCommand($builder);
        $commandTester = new CommandTester($command);
        $commandTester->execute(['newName' => 'name', 'newUser' => 'user', 'newPass' => 'pass', '--sql-only' => true]);
        $this->assertEquals(
            "foo\n",
            $commandTester->getDisplay()
        );
        $this->assertEquals(0, $commandTester->getStatusCode());
    }
}
