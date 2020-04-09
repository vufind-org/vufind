<?php
/**
 * CssBuilderCommand test.
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

use Symfony\Component\Console\Tester\CommandTester;
use VuFindConsole\Command\Util\AbstractExpireCommand;

/**
 * CssBuilderCommand test.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class CssBuilderCommandTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Name of class being tested
     *
     * @var string
     */
    protected $targetClass = AbstractExpireCommand::class;

    /**
     * Name of a valid table class to test with
     *
     * @var string
     */
    protected $validTableClass = \VuFind\Db\Table\AuthHash::class;

    /**
     * Test that the command delegates proper behavior.
     *
     * @return void
     */
    public function testBasicOperation()
    {
        $table = $this->getMockBuilder($this->validTableClass)
            ->disableOriginalConstructor()
            ->getMock();
        $table->expects($this->at(1))->method('getExpiredIdRange')
            ->with($this->equalTo(2))
            ->will($this->returnValue([0, 1500]));
        $table->expects($this->at(2))->method('deleteExpired')
            ->with($this->equalTo(2), $this->equalTo(0), $this->equalTo(999))
            ->will($this->returnValue(50));
        $table->expects($this->at(3))->method('deleteExpired')
            ->with($this->equalTo(2), $this->equalTo(1000), $this->equalTo(1999))
            ->will($this->returnValue(7));
        $command = new $this->targetClass($table, 'foo');
        $commandTester = new CommandTester($command);
        $commandTester->execute(['--sleep' => 1]);
        $this->assertEquals('', $commandTester->getDisplay());
        $this->assertEquals(0, $commandTester->getStatusCode());
    }
}
