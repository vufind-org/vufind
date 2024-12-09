<?php

/**
 * Generate/Theme command test.
 *
 * PHP version 8
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
use VuFindConsole\Command\Generate\ThemeCommand;
use VuFindTheme\ThemeGenerator;

/**
 * Generate/Theme command test.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class ThemeCommandTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test the simplest possible success case.
     *
     * @return void
     */
    public function testSuccessWithMinimalParameters()
    {
        $config = new \Laminas\Config\Config([]);
        $generator = $this->getMockGenerator();
        $generator->expects($this->once())
            ->method('generate')
            ->with($this->equalTo('custom'))
            ->will($this->returnValue(true));
        $generator->expects($this->once())
            ->method('configure')
            ->with($this->equalTo($config), $this->equalTo('custom'))
            ->will($this->returnValue(true));
        $command = new ThemeCommand($generator, $config);
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);
        $this->assertEquals(
            "\tNo theme name provided, using \"custom\"\n\tFinished.\n",
            $commandTester->getDisplay()
        );
        $this->assertEquals(0, $commandTester->getStatusCode());
    }

    /**
     * Test a failure scenario.
     *
     * @return void
     */
    public function testFailure()
    {
        $config = new \Laminas\Config\Config([]);
        $generator = $this->getMockGenerator();
        $generator->expects($this->once())
            ->method('generate')
            ->with($this->equalTo('foo'))
            ->will($this->returnValue(true));
        $generator->expects($this->once())
            ->method('configure')
            ->with($this->equalTo($config), $this->equalTo('foo'))
            ->will($this->returnValue(false));
        $generator->expects($this->once())
            ->method('getLastError')
            ->will($this->returnValue('fake error'));
        $command = new ThemeCommand($generator, $config);
        $commandTester = new CommandTester($command);
        $commandTester->execute(['name' => 'foo']);
        $this->assertEquals("fake error\n", $commandTester->getDisplay());
        $this->assertEquals(1, $commandTester->getStatusCode());
    }

    /**
     * Create a mock generator object.
     *
     * @return ThemeGenerator
     */
    protected function getMockGenerator()
    {
        return $this->getMockBuilder(ThemeGenerator::class)
            ->disableOriginalConstructor()
            ->getMock();
    }
}
