<?php
/**
 * Install command test.
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
namespace VuFindTest\Command\Import;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use VuFindConsole\Command\Install\InstallCommand;

/**
 * Install command test.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class InstallCommandTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test the interactive installation process.
     *
     * @return void
     */
    public function testInteractiveInstallation()
    {
        $expectedBaseDir = realpath(__DIR__ . '/../../../../../../../../');
        $localFixtures = $expectedBaseDir . '/module/VuFindConsole/tests/fixtures';
        $command = $this->getMockCommand(
            ['buildDirs', 'displaySuccessMessage', 'getInput', 'writeFileToDisk']
        );
        $command->expects($this->at(0))->method('getInput')
            ->with(
                $this->isInstanceOf(InputInterface::class),
                $this->isInstanceOf(OutputInterface::class),
                $this->equalTo(
                    'Where would you like to store your local settings? '
                    . "[$expectedBaseDir/local] "
                )
            )->will($this->returnValue($localFixtures));
        $expectedDirs = [
            $localFixtures,
            $localFixtures . '/cache',
            $localFixtures . '/config',
            $localFixtures . '/harvest',
            $localFixtures . '/import',
        ];
        $command->expects($this->at(1))->method('buildDirs')
            ->with($this->equalTo($expectedDirs))
            ->will($this->returnValue(true));
        $command->expects($this->at(2))->method('getInput')
            ->with(
                $this->isInstanceOf(InputInterface::class),
                $this->isInstanceOf(OutputInterface::class),
                $this->equalTo(
                    "\nWhat module name would you like to use? [blank for none] "
                )
            )->will($this->returnValue(''));
        $command->expects($this->at(3))->method('getInput')
            ->with(
                $this->isInstanceOf(InputInterface::class),
                $this->isInstanceOf(OutputInterface::class),
                $this->equalTo(
                    'What base path should be used in VuFind\'s URL? [/vufind] '
                )
            )->will($this->returnValue('/bar'));
        $command->expects($this->at(4))->method('buildDirs')
            ->with($this->equalTo($expectedDirs))
            ->will($this->returnValue(true));
        $command->expects($this->at(5))->method('writeFileToDisk')
            ->with($this->equalTo("$expectedBaseDir/env.bat"))
            ->will($this->returnValue(true));
        $command->expects($this->at(6))->method('writeFileToDisk')
            ->with($this->equalTo("$localFixtures/import/import.properties"))
            ->will($this->returnValue(true));
        $command->expects($this->at(7))->method('writeFileToDisk')
            ->with($this->equalTo("$localFixtures/import/import_auth.properties"))
            ->will($this->returnValue(true));
        $command->expects($this->at(8))->method('writeFileToDisk')
            ->with($this->equalTo("$localFixtures/httpd-vufind.conf"))
            ->will($this->returnValue(true));
        $command->expects($this->at(9))->method('displaySuccessMessage')
            ->with($this->isInstanceOf(OutputInterface::class));
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);
        $expectedOutput = <<<TEXT
VuFind has been found in $expectedBaseDir.

VuFind supports use of a custom module for storing local code changes.
If you do not plan to customize the code, you can skip this step.
If you decide to use a custom module, the name you choose will be used for
the module's directory name and its PHP namespace.

TEXT;
        $this->assertEquals(
            $expectedOutput, $commandTester->getDisplay()
        );
        $this->assertEquals(0, $commandTester->getStatusCode());
    }

    /**
     * Get a mock command object
     *
     * @param array $methods Methods to mock
     *
     * @return InstallCommand
     */
    protected function getMockCommand(
        array $methods = ['buildDirs', 'getInput', 'writeFileToDisk']
    ) {
        return $this->getMockBuilder(InstallCommand::class)
            ->setMethods($methods)
            ->getMock();
    }
}
