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
            ['buildDirs', 'getApacheLocation', 'getInput', 'writeFileToDisk']
        );
        $command->expects($this->exactly(3))->method('getInput')
            ->withConsecutive(
                [
                    $this->isInstanceOf(InputInterface::class),
                    $this->isInstanceOf(OutputInterface::class),
                    'Where would you like to store your local settings? '
                    . "[$expectedBaseDir/local] ",
                ],
                [
                    $this->isInstanceOf(InputInterface::class),
                    $this->isInstanceOf(OutputInterface::class),
                    "\nWhat module name would you like to use? [blank for none] ",
                ],
                [
                    $this->isInstanceOf(InputInterface::class),
                    $this->isInstanceOf(OutputInterface::class),
                    'What base path should be used in VuFind\'s URL? [/vufind] ',
                ]
            )->willReturnOnConsecutiveCalls($localFixtures, '', '/bar');
        $expectedDirs = [
            $localFixtures,
            $localFixtures . '/cache',
            $localFixtures . '/config',
            $localFixtures . '/harvest',
            $localFixtures . '/import',
        ];
        $command->expects($this->exactly(2))->method('buildDirs')
            ->with($this->equalTo($expectedDirs))
            ->will($this->returnValue(true));
        $expectedEnvBat = "@set VUFIND_HOME=$expectedBaseDir\n"
            . "@set VUFIND_LOCAL_DIR=$localFixtures\n";
        $command->expects($this->exactly(4))->method('writeFileToDisk')
            ->withConsecutive(
                ["$expectedBaseDir/env.bat", $expectedEnvBat],
                ["$localFixtures/import/import.properties"],
                ["$localFixtures/import/import_auth.properties"],
                ["$localFixtures/httpd-vufind.conf"]
            )->will($this->returnValue(true));
        $command->expects($this->once())->method('getApacheLocation')
            ->with($this->isInstanceOf(OutputInterface::class));
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);
        $expectedOutput = <<<TEXT
            VuFind has been found in $expectedBaseDir.

            VuFind supports use of a custom module for storing local code changes.
            If you do not plan to customize the code, you can skip this step.
            If you decide to use a custom module, the name you choose will be used for
            the module's directory name and its PHP namespace.
            Apache configuration written to $localFixtures/httpd-vufind.conf.

            You now need to load this configuration into Apache.
            Once the configuration is linked, restart Apache.  You should now be able
            to access VuFind at http://localhost/bar

            For proper use of command line tools, you should also ensure that your

            VUFIND_HOME and VUFIND_LOCAL_DIR environment variables are set to
            $expectedBaseDir and $localFixtures respectively.
            TEXT;
        $this->assertEquals(
            $expectedOutput,
            trim($commandTester->getDisplay())
        );
        $this->assertEquals(0, $commandTester->getStatusCode());
    }

    /**
     * Test the non-interactive installation process.
     *
     * @return void
     */
    public function testNonInteractiveInstallation()
    {
        $expectedBaseDir = realpath(__DIR__ . '/../../../../../../../../');
        $localFixtures = $expectedBaseDir . '/module/VuFindConsole/tests/fixtures';
        $command = $this->getMockCommand(
            ['buildDirs', 'getApacheLocation', 'getInput', 'writeFileToDisk']
        );
        $expectedDirs = [
            $localFixtures,
            $localFixtures . '/cache',
            $localFixtures . '/config',
            $localFixtures . '/harvest',
            $localFixtures . '/import',
        ];
        $command->expects($this->once())->method('buildDirs')
            ->with($this->equalTo($expectedDirs))
            ->will($this->returnValue(true));
        $expectedEnvBat = "@set VUFIND_HOME=$expectedBaseDir\n"
            . "@set VUFIND_LOCAL_DIR=$localFixtures\n";
        $command->expects($this->exactly(4))->method('writeFileToDisk')
            ->withConsecutive(
                ["$expectedBaseDir/env.bat", $expectedEnvBat],
                ["$localFixtures/import/import.properties"],
                ["$localFixtures/import/import_auth.properties"],
                ["$localFixtures/httpd-vufind.conf"]
            )->will($this->returnValue(true));
        $command->expects($this->once())->method('getApacheLocation')
            ->with($this->isInstanceOf(OutputInterface::class));
        $commandTester = new CommandTester($command);
        $commandTester->execute(
            ['--non-interactive' => true, '--overridedir' => $localFixtures]
        );
        $expectedOutput = <<<EXPECTED
            VuFind has been found in $expectedBaseDir.
            Apache configuration written to $localFixtures/httpd-vufind.conf.

            You now need to load this configuration into Apache.
            Once the configuration is linked, restart Apache.  You should now be able
            to access VuFind at http://localhost/vufind

            For proper use of command line tools, you should also ensure that your

            VUFIND_HOME and VUFIND_LOCAL_DIR environment variables are set to
            $expectedBaseDir and $localFixtures respectively.
            EXPECTED;
        $this->assertEquals(
            $expectedOutput,
            trim($commandTester->getDisplay())
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
            ->onlyMethods($methods)
            ->getMock();
    }
}
