<?php

/**
 * Generate/ExtendService command test.
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

namespace VuFindTest\Command\Generate;

use Symfony\Component\Console\Tester\CommandTester;
use VuFindConsole\Command\Generate\ExtendServiceCommand;
use VuFindConsole\Generator\GeneratorTools;

/**
 * Generate/ExtendService command test.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class ExtendServiceCommandTest extends \PHPUnit\Framework\TestCase
{
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
            'Not enough arguments (missing: "config_path, target_module").'
        );
        $command = new ExtendServiceCommand(
            $this->getMockGeneratorTools()
        );
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);
    }

    /**
     * Test the simplest possible success case.
     *
     * @return void
     */
    public function testSuccessWithMinimalParameters()
    {
        $tools = $this->getMockGeneratorTools(
            ['extendService', 'setOutputInterface']
        );
        $tools->expects($this->once())->method('setOutputInterface');
        $tools->expects($this->once())->method('extendService')
            ->with(
                $this->equalTo('Foo'),
                $this->equalTo('Bar')
            );
        $command = new ExtendServiceCommand($tools);
        $commandTester = new CommandTester($command);
        $commandTester->execute(
            [
                'config_path' => 'Foo',
                'target_module' => 'Bar',
            ]
        );
        $this->assertEquals(0, $commandTester->getStatusCode());
    }

    /**
     * Test exception handling.
     *
     * @return void
     */
    public function testError()
    {
        $tools = $this->getMockGeneratorTools(
            ['extendService', 'setOutputInterface']
        );
        $tools->expects($this->once())->method('setOutputInterface');
        $tools->expects($this->once())->method('extendService')
            ->will($this->throwException(new \Exception('Foo!')));
        $command = new ExtendServiceCommand($tools);
        $commandTester = new CommandTester($command);
        $commandTester->execute(
            [
                'config_path' => 'Foo',
                'target_module' => 'Bar',
            ]
        );
        $this->assertEquals("Foo!\n", $commandTester->getDisplay());
        $this->assertEquals(1, $commandTester->getStatusCode());
    }

    /**
     * Get a mock generator tools object
     *
     * @param array $methods Methods to mock
     *
     * @return GeneratorTools
     */
    protected function getMockGeneratorTools($methods = [])
    {
        return $this->getMockBuilder(GeneratorTools::class)
            ->disableOriginalConstructor()
            ->onlyMethods($methods)
            ->getMock();
    }
}
