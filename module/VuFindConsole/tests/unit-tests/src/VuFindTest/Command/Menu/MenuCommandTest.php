<?php

/**
 * Menu command test.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2025.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Command\Import;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use VuFind\CSV\Importer;
use VuFindConsole\Command\Import\ImportCsvCommand;
use VuFindConsole\Command\Menu\MenuCommand;
use VuFindTest\Feature\ReflectionTrait;

/**
 * Install command test.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class MenuCommandTest extends \PHPUnit\Framework\TestCase
{
    use ReflectionTrait;

    /**
     * Test that running an internal command translates into running an external command.
     *
     * @return void
     */
    public function testInternalCommand(): void
    {
        $config = [
            'main' => [
                'label' => 'Import CSV',
                'type' => 'internal-command',
                'command' => 'install/install',
            ],
        ];
        $commandManager = $this->createMock(\VuFindConsole\Command\PluginManager::class);
        $importCsvCommand = new ImportCsvCommand($this->createMock(Importer::class));
        $input = $this->createMock(InputInterface::class);
        $output = $this->createMock(OutputInterface::class);
        $commandManager->expects($this->once())->method('get')->with('install/install')->willReturn($importCsvCommand);
        $command = $this->getMockBuilder(MenuCommand::class)
            ->setConstructorArgs([$config, $commandManager])
            ->onlyMethods(['runExternalCommand'])
            ->getMock();
        $expectedConfig = [
            'label' => 'Import CSV',
            'type' => 'external-command',
            'phpCommand' => 'public/index.php install/install',
            'arguments' => [
                [
                    'label' => 'source file to index',
                    'required' => true,
                    'default' => null,
                ],
                [
                    'label' => "import configuration file (\$VUFIND_LOCAL_DIR/import and  \$VUFIND_HOME/import will\n"
                        . 'be searched for this filename; see csv.ini for configuration examples)',
                    'required' => true,
                    'default' => null,

                ],
            ],
            'options' => [
                [
                    'label' => 'activates test mode, which displays transformed output without updating Solr',
                    'switch' => '--test-only',
                    'type' => 'no-value',
                    'default' => false,
                ],
                [
                    'label' => "name of search backend to index content into (could be overridden with,\n"
                        . 'for example, SolrAuth to index authority records)',
                    'switch' => '--index',
                    'type' => 'string',
                    'default' => 'Solr',
                ],
            ],
        ];
        $command->expects($this->once())
            ->method('runExternalCommand')
            ->with($input, $output, $expectedConfig)
            ->willReturn(Command::SUCCESS);
        $this->assertEquals(Command::SUCCESS, $this->callMethod($command, 'execute', [$input, $output]));
    }

    /**
     * Data provider for testSimpleExternalCommand().
     *
     * @return array[]
     */
    public static function simpleExternalCommandProvider(): array
    {
        return ['success' => [true], 'failure' => [false]];
    }

    /**
     * Test running an external command.
     *
     * @param bool $success Should the command succeed?
     *
     * @return void
     *
     * @dataProvider simpleExternalCommandProvider
     */
    public function testSimpleExternalCommand(bool $success): void
    {
        $config = [
            'main' => [
                'label' => 'sample command',
                'type' => 'external-command',
                'command' => 'foo',
            ],
        ];
        $commandManager = $this->createMock(\VuFindConsole\Command\PluginManager::class);
        $command = $this->getMockBuilder(MenuCommand::class)
            ->setConstructorArgs([$config, $commandManager])
            ->onlyMethods(['runCommand', 'getHelper'])
            ->getMock();
        $command->expects($this->once())->method('runCommand')->with(APPLICATION_PATH . '/foo')->willReturn($success);
        $tester = new CommandTester($command);
        $tester->execute([]);
        $this->assertEquals(
            $success ? "sample command\nCommand successful.\n" : "sample command\nCommand failed.\n",
            $tester->getDisplay()
        );
        $this->assertEquals($success ? Command::SUCCESS : Command::FAILURE, $tester->getStatusCode());
    }

    /**
     * Test the summary feature.
     *
     * @return void
     */
    public function testSummary(): void
    {
        $config = [
            'main' => [
                'label' => 'Main Menu',
                'type' => 'menu',
                'contents' => [
                    [
                        'label' => 'Summary',
                        'type' => 'summary',
                    ],
                    [
                        'label' => 'Submenu',
                        'type' => 'menu',
                        'contents' => [
                            [
                                'label' => 'Command',
                                'type' => 'internal-command',
                            ],
                        ],
                    ],
                ],
            ],
        ];
        $commandManager = $this->createMock(\VuFindConsole\Command\PluginManager::class);
        $command = $this->getMockBuilder(MenuCommand::class)
            ->setConstructorArgs([$config, $commandManager, 'menu/menu'])
            ->onlyMethods(['runCommand'])
            ->getMock();
        // We need to add the command to an application to set up the question helper:
        $app = new Application();
        $app->addCommands([$command]);
        $tester = new CommandTester($command);
        $tester->setInputs(['0', '2']); // choose first menu option, then exit
        $tester->execute([]);
        $expectedSummary = <<<OUTPUT
            Summary

            Main Menu
                Submenu
                    Command
            OUTPUT;
        $this->assertStringContainsString($expectedSummary, $tester->getDisplay());
        $this->assertEquals(Command::SUCCESS, $tester->getStatusCode());
    }
}
