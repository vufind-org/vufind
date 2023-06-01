<?php

/**
 * Compile/Theme command test.
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

namespace VuFindTest\Command\Compile;

use Symfony\Component\Console\Tester\CommandTester;
use VuFindConsole\Command\Compile\ThemeCommand;
use VuFindTheme\ThemeCompiler;

/**
 * Compile/Theme command test.
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
            'Not enough arguments (missing: "source").'
        );
        $command = new ThemeCommand($this->getMockCompiler());
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
        $compiler = $this->getMockCompiler(['compile']);
        $compiler->expects($this->once())->method('compile')
            ->with(
                $this->equalTo('theme'),
                $this->equalTo('theme_compiled'),
                $this->equalTo(false)
            )->will($this->returnValue(true));
        $command = new ThemeCommand($compiler);
        $commandTester = new CommandTester($command);
        $commandTester->execute(['source' => 'theme']);
        $this->assertEquals(
            "Success.\n",
            $commandTester->getDisplay()
        );
        $this->assertEquals(0, $commandTester->getStatusCode());
    }

    /**
     * Simulate failure caused by existing theme and no '--force' option.
     *
     * @return void
     */
    public function testFailureWithMissingForce()
    {
        $compiler = $this->getMockCompiler(['compile', 'getLastError']);
        $compiler->expects($this->once())->method('compile')
            ->with(
                $this->equalTo('theme'),
                $this->equalTo('compiled_theme'),
                $this->equalTo(false)
            )->will($this->returnValue(false));
        $compiler->expects($this->once())->method('getLastError')
            ->will($this->returnValue('Error!'));
        $command = new ThemeCommand($compiler);
        $commandTester = new CommandTester($command);
        $commandTester->execute(
            [
                'source' => 'theme',
                'target' => 'compiled_theme',
            ]
        );
        $this->assertEquals(
            "Error!\n",
            $commandTester->getDisplay()
        );
        $this->assertEquals(1, $commandTester->getStatusCode());
    }

    /**
     * Simulate success with '--force' option.
     *
     * @return void
     */
    public function testSuccessWithForceOption()
    {
        $compiler = $this->getMockCompiler(['compile']);
        $compiler->expects($this->once())->method('compile')
            ->with(
                $this->equalTo('theme'),
                $this->equalTo('compiled_theme'),
                $this->equalTo(true)
            )->will($this->returnValue(true));
        $command = new ThemeCommand($compiler);
        $commandTester = new CommandTester($command);
        $commandTester->execute(
            [
                'source' => 'theme',
                'target' => 'compiled_theme',
                '--force' => true,
            ]
        );
        $this->assertEquals(
            "Success.\n",
            $commandTester->getDisplay()
        );
        $this->assertEquals(0, $commandTester->getStatusCode());
    }

    /**
     * Get a mock compiler object
     *
     * @param array $methods Methods to mock
     *
     * @return ThemeCompiler
     */
    protected function getMockCompiler($methods = [])
    {
        return $this->getMockBuilder(ThemeCompiler::class)
            ->disableOriginalConstructor()
            ->onlyMethods($methods)
            ->getMock();
    }
}
