<?php
/**
 * DedupeCommand test.
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

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use VuFindConsole\Command\Util\DedupeCommand;

/**
 * DedupeCommand test.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class DedupeCommandTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Unit\FixtureTrait;

    /**
     * Get a mocked-out command object.
     *
     * @return DedupeCommand
     */
    protected function getMockCommand()
    {
        $mockMethods = [
            'getInput',
            'openOutputFile',
            'writeToOutputFile',
            'closeOutputFile',
        ];
        return $this->getMockBuilder(DedupeCommand::class)
            ->setMethods($mockMethods)
            ->getMock();
    }

    /**
     * Set up basic expectations on a command.
     *
     * @param DedupeCommand $command  Mock command
     * @param string        $output   Output filename
     * @param int           $sequence Expectation sequence number
     *
     * @return void
     */
    protected function setSuccessfulExpectations($command, $output, $sequence = 0)
    {
        $fakeHandle = 7;    // arbitrary number for test purposes
        $command->expects($this->at($sequence++))->method('openOutputFile')
            ->with($this->equalTo($output))
            ->will($this->returnValue($fakeHandle));
        $command->expects($this->at($sequence++))->method('writeToOutputFile')
            ->with($this->equalTo($fakeHandle), $this->equalTo("foo\n"));
        $command->expects($this->at($sequence++))->method('writeToOutputFile')
            ->with($this->equalTo($fakeHandle), $this->equalTo("bar\n"));
        $command->expects($this->at($sequence++))->method('writeToOutputFile')
            ->with($this->equalTo($fakeHandle), $this->equalTo("baz\n"));
        $command->expects($this->at($sequence++))->method('closeOutputFile')
            ->with($this->equalTo($fakeHandle));
    }

    /**
     * Test that missing file yields an error message.
     *
     * @return void
     */
    public function testWithMissingFile()
    {
        $command = new DedupeCommand();
        $commandTester = new CommandTester($command);
        $commandTester->execute(['input' => '/does/not/exist']);
        $this->assertEquals(1, $commandTester->getStatusCode());
        $this->assertEquals(
            "Could not open input file: /does/not/exist\n",
            $commandTester->getDisplay()
        );
    }

    /**
     * Test success with command line arguments.
     *
     * @return void
     */
    public function testSuccessWithArguments()
    {
        $outputFilename = '/fake/outfile';
        $command = $this->getMockCommand();
        $this->setSuccessfulExpectations($command, $outputFilename);
        $commandTester = new CommandTester($command);
        $fixture = $this->getFixtureDir('VuFindConsole') . 'fileWithDuplicateLines';
        $commandTester->execute(
            [
                'input' => $fixture,
                'output' => $outputFilename,
            ]
        );
        $this->assertEquals(0, $commandTester->getStatusCode());
        $this->assertEquals("", $commandTester->getDisplay());
    }

    /**
     * Test success with interactive input.
     *
     * @return void
     */
    public function testSuccessWithoutArguments()
    {
        $fixture = $this->getFixtureDir('VuFindConsole') . 'fileWithDuplicateLines';
        $outputFilename = '/fake/outfile';
        $command = $this->getMockCommand();
        $command->expects($this->at(0))->method('getInput')
            ->with(
                $this->isInstanceOf(InputInterface::class),
                $this->isInstanceOf(OutputInterface::class),
                $this->equalTo(
                    'Please specify an input file: '
                )
            )->will($this->returnValue($fixture));
        $command->expects($this->at(1))->method('getInput')
            ->with(
                $this->isInstanceOf(InputInterface::class),
                $this->isInstanceOf(OutputInterface::class),
                $this->equalTo(
                    'Please specify an output file: '
                )
            )->will($this->returnValue($outputFilename));
        $this->setSuccessfulExpectations($command, $outputFilename, 2);
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);
        $this->assertEquals(0, $commandTester->getStatusCode());
        $this->assertEquals("", $commandTester->getDisplay());
    }
}
