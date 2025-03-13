<?php

/**
 * Install command test.
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

namespace VuFindTest\Command\Import;

use PHPUnit\Framework\MockObject\MockObject;
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
    use \VuFindTest\Feature\WithConsecutiveTrait;

    /**
     * Data provider for testing with or without the skip-backups flag.
     *
     * @return array[]
     */
    public static function skipBackupsProvider(): array
    {
        return [
            'skip backups' => [true],
            'with backups' => [false],
        ];
    }

    /**
     * Test the interactive installation process.
     *
     * @param bool $skipBackups Should we test with backups disabled?
     *
     * @return void
     *
     * @dataProvider skipBackupsProvider
     */
    public function testInteractiveInstallation(bool $skipBackups): void
    {
        $expectedBaseDir = realpath(__DIR__ . '/../../../../../../../../');
        $localFixtures = $expectedBaseDir . '/module/VuFindConsole/tests/fixtures';
        $methodsToMock = ['buildDirs', 'getApacheLocation', 'getInput', 'writeFileToDisk'];
        // If we test without the --skip-backups flag, we need to mock the backUpFile method,
        // because we don't want the test to cause files to get written to disk. If we test
        // with the flag, we can skip this mocking because the actual method will bypass
        // file writing. We will know the flag worked, because if something goes wrong, the
        // backup process will add messages to the output which will cause an assertion to fail.
        if (!$skipBackups) {
            $methodsToMock[] = 'backUpFile';
        }
        $command = $this->getMockCommand($methodsToMock);
        if (!$skipBackups) {
            $command->expects($this->exactly(5))->method('backUpFile')->willReturn(true);
        }
        $this->expectConsecutiveCalls(
            $command,
            'getInput',
            [
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
                    'What base path should be used in VuFind®\'s URL? [/vufind] ',
                ],
                [
                    $this->isInstanceOf(InputInterface::class),
                    $this->isInstanceOf(OutputInterface::class),
                    'What port number should Solr use? [8983] ',
                ],
            ],
            [$localFixtures, '', '/bar', '8080']
        );
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
            . "@set VUFIND_LOCAL_DIR=$localFixtures\n"
            . "@set SOLR_PORT=8080\n";
        $expectedEnvSh = str_replace('@set', 'export', $expectedEnvBat);
        $this->expectConsecutiveCalls(
            $command,
            'writeFileToDisk',
            [
                ["$expectedBaseDir/env.bat", $expectedEnvBat],
                ["$expectedBaseDir/env.sh", $expectedEnvSh],
                ["$localFixtures/import/import.properties"],
                ["$localFixtures/import/import_auth.properties"],
                ["$localFixtures/httpd-vufind.conf"],
            ],
            true
        );
        $command->expects($this->once())->method('getApacheLocation')
            ->with($this->isInstanceOf(OutputInterface::class));
        $commandTester = new CommandTester($command);
        $commandTester->execute($skipBackups ? ['--skip-backups' => true] : []);
        $expectedOutput = <<<TEXT
            VuFind® has been found in $expectedBaseDir.

            VuFind® supports use of a custom module for storing local code changes.
            If you do not plan to customize the code, you can skip this step.
            If you decide to use a custom module, the name you choose will be used for
            the module's directory name and its PHP namespace.
            Apache configuration written to $localFixtures/httpd-vufind.conf.

            You now need to load this configuration into Apache.
            Once the configuration is linked, restart Apache. You should now be able
            to access VuFind® at http://localhost/bar

            For proper use of command line tools, you should ensure that your

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
    public function testNonInteractiveInstallation(): void
    {
        $expectedBaseDir = realpath(__DIR__ . '/../../../../../../../../');
        $localFixtures = $expectedBaseDir . '/module/VuFindConsole/tests/fixtures';
        $command = $this->getMockCommand(
            ['backUpFile', 'buildDirs', 'getApacheLocation', 'getInput', 'writeFileToDisk']
        );
        $expectedDirs = [
            $localFixtures,
            $localFixtures . '/cache',
            $localFixtures . '/config',
            $localFixtures . '/harvest',
            $localFixtures . '/import',
        ];
        $command->expects($this->exactly(5))->method('backUpFile')->willReturn(true);
        $command->expects($this->once())->method('buildDirs')
            ->with($this->equalTo($expectedDirs))
            ->will($this->returnValue(true));
        $expectedEnvBat = "@set VUFIND_HOME=$expectedBaseDir\n"
            . "@set VUFIND_LOCAL_DIR=$localFixtures\n"
            . "@set SOLR_PORT=8983\n";
        $expectedEnvSh = str_replace('@set', 'export', $expectedEnvBat);
        $this->expectConsecutiveCalls(
            $command,
            'writeFileToDisk',
            [
                ["$expectedBaseDir/env.bat", $expectedEnvBat],
                ["$expectedBaseDir/env.sh", $expectedEnvSh],
                ["$localFixtures/import/import.properties"],
                ["$localFixtures/import/import_auth.properties"],
                ["$localFixtures/httpd-vufind.conf"],
            ],
            true
        );
        $command->expects($this->once())->method('getApacheLocation')
            ->with($this->isInstanceOf(OutputInterface::class));
        $commandTester = new CommandTester($command);
        $commandTester->execute(
            ['--non-interactive' => true, '--overridedir' => $localFixtures]
        );
        $expectedOutput = <<<EXPECTED
            VuFind® has been found in $expectedBaseDir.
            Apache configuration written to $localFixtures/httpd-vufind.conf.

            You now need to load this configuration into Apache.
            Once the configuration is linked, restart Apache. You should now be able
            to access VuFind® at http://localhost/vufind

            For proper use of command line tools, you should ensure that your

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
     * Test that providing an invalid Solr port number causes an error.
     *
     * @return void
     */
    public function testInvalidSolrPort(): void
    {
        $expectedBaseDir = realpath(__DIR__ . '/../../../../../../../../');
        $command = $this->getMockCommand(
            ['backUpFile', 'buildDirs', 'getApacheLocation', 'getInput', 'writeFileToDisk']
        );
        $commandTester = new CommandTester($command);
        $commandTester->execute(
            ['--solr-port' => 'bad']
        );
        $expectedOutput = <<<EXPECTED
            VuFind® has been found in $expectedBaseDir.
            Solr port must be a number.
            EXPECTED;
        $this->assertEquals(
            $expectedOutput,
            trim($commandTester->getDisplay())
        );
        $this->assertEquals(1, $commandTester->getStatusCode());
    }

    /**
     * Get a mock command object
     *
     * @param array $methods Methods to mock
     *
     * @return InstallCommand&MockObject
     */
    protected function getMockCommand(
        array $methods = ['buildDirs', 'getInput', 'writeFileToDisk']
    ): InstallCommand&MockObject {
        return $this->getMockBuilder(InstallCommand::class)
            ->onlyMethods($methods)
            ->getMock();
    }
}
