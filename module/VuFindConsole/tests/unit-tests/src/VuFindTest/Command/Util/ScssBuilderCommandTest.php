<?php

/**
 * ScssBuilderCommand test.
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

namespace VuFindTest\Command\Util;

use Symfony\Component\Console\Tester\CommandTester;
use VuFindConsole\Command\Util\ScssBuilderCommand;

/**
 * ScssBuilderCommand test.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class ScssBuilderCommandTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test that the command delegates proper behavior.
     *
     * @return void
     */
    public function testBasicOperation()
    {
        $cacheDir = '/foo';
        $compiler = $this->getMockBuilder(\VuFindTheme\ScssCompiler::class)
            ->disableOriginalConstructor()->getMock();
        $compiler->expects($this->once())->method('setTempPath')
            ->with($this->equalTo($cacheDir));
        $compiler->expects($this->once())->method('compile')
            ->with($this->equalTo(['foo', 'bar']));
        $command = $this->getMockBuilder(ScssBuilderCommand::class)
            ->onlyMethods(['getCompiler'])
            ->setConstructorArgs([$cacheDir])
            ->getMock();
        $command->expects($this->once())->method('getCompiler')
            ->will($this->returnValue($compiler));
        $commandTester = new CommandTester($command);
        $commandTester->execute(['themes' => ['foo', 'bar', 'foo']]);
        $this->assertEquals('', $commandTester->getDisplay());
        $this->assertEquals(0, $commandTester->getStatusCode());
    }
}
