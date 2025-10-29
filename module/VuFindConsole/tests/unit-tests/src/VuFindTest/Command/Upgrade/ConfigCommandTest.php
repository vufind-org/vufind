<?php

/**
 * Upgrade/Config command test.
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

use Symfony\Component\Console\Tester\CommandTester;
use VuFind\Config\Upgrade;
use VuFindConsole\Command\Upgrade\ConfigCommand;

/**
 * Upgrade/Config command test.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class ConfigCommandTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test a success case.
     *
     * @return void
     */
    public function testSuccess(): void
    {
        $upgrader = $this->createMock(Upgrade::class);
        $upgrader->expects($this->once())->method('run');
        $upgrader->expects($this->once())->method('getWarnings')->willReturn(['WARNING1', 'WARNING2']);
        $command = new ConfigCommand($upgrader);
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);
        $this->assertEquals(
            "WARNING1\nWARNING2\nConfiguration upgrade successful! Please review your configurations.\n"
            . "The automatic update process sometimes re-enables disabled settings and removes comments.\n"
            . "Backups of your old configurations have been created for comparison purposes.\n",
            $commandTester->getDisplay()
        );
        $this->assertEquals(ConfigCommand::SUCCESS, $commandTester->getStatusCode());
    }

    /**
     * Test an exception scenario.
     *
     * @return void
     */
    public function testUpgradeThrowsException(): void
    {
        $upgrader = $this->createMock(Upgrade::class);
        $upgrader->expects($this->once())->method('run')->willThrowException(new \Exception('Kaboom'));
        $upgrader->expects($this->never())->method('getWarnings');
        $command = new ConfigCommand($upgrader);
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);
        $this->assertStringStartsWith(
            'Exception: Kaboom',
            $commandTester->getDisplay()
        );
        $this->assertEquals(ConfigCommand::FAILURE, $commandTester->getStatusCode());
    }
}
