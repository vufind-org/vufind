<?php
/**
 * SwitchDbHashCommand command test.
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2020.
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
namespace VuFindTest\Command\Util;

use Laminas\Config\Config;
use Symfony\Component\Console\Tester\CommandTester;
use VuFind\Db\Table\User;
use VuFindConsole\Command\Util\SwitchDbHashCommandCommand;

/**
 * SwitchDbHashCommand command test.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class SwitchDbHashCommandTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Get mock table object
     *
     * @return User
     */
    protected function getMockTable()
    {
        return $this->getMockBuilder(User::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    /**
     * Get mock command object
     *
     * @param array $config Config settings
     * @param User  $table  User table gateway
     */
    protected function getMockCommand(array $config = [], $table = null)
    {
        return $this->getMockBuilder(SwitchDbHashCommandCommand::class)
            ->setConstructorArgs(
                [
                    new Config($config),
                    $table ?? $this->getMockTable(),
                ]
            )->setMethods(['getConfigWriter'])
            ->getMock();
    }

    /**
     * Test that missing parameters yield an error message.
     *
     * @return void
     */
    public function testWithoutParameters()
    {
        $this->expectException(
            \Symfony\Component\Console\Exception\RuntimeException::class
        );
        $this->expectExceptionMessage(
            'Not enough arguments (missing: "newmethod").'
        );
        $command = $this->getMockCommand();
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);
    }
}
