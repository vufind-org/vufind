<?php

/**
 * Console command: Generate non-tab record action route.
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
 * @package  Console
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFindConsole\Command\Generate;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use VuFindConsole\Generator\GeneratorTools;

/**
 * Console command: Generate non-tab record action route.
 *
 * @category VuFind
 * @package  Console
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
#[AsCommand(
    name: 'generate/nontabrecordaction',
    description: 'Non-tab record action route generator'
)]
class NonTabRecordActionCommand extends AbstractCommand
{
    /**
     * Main framework configuration
     *
     * @var array
     */
    protected $mainConfig;

    /**
     * Constructor
     *
     * @param GeneratorTools $tools      Generator tools
     * @param array          $mainConfig Main framework configuration
     * @param string|null    $name       The name of the command; passing null
     * means it must be set in configure()
     */
    public function __construct(
        GeneratorTools $tools,
        array $mainConfig,
        $name = null
    ) {
        $this->mainConfig = $mainConfig;
        parent::__construct($tools, $name);
    }

    /**
     * Configure the command.
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setHelp('Adds routes for a non-tab record action.')
            ->addArgument(
                'action',
                InputArgument::REQUIRED,
                'new action to add'
            )->addArgument(
                'target_module',
                InputArgument::REQUIRED,
                'the module where the new routes will be generated'
            );
    }

    /**
     * Run the command.
     *
     * @param InputInterface  $input  Input object
     * @param OutputInterface $output Output object
     *
     * @return int 0 for success
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $action = $input->getArgument('action');
        $module = $input->getArgument('target_module');

        $this->generatorTools->setOutputInterface($output);

        // Create backup of configuration
        $configPath = $this->generatorTools->getModuleConfigPath($module);
        $this->generatorTools->backUpFile($configPath);

        // Append the routes
        $config = include $configPath;
        foreach ($this->mainConfig['router']['routes'] as $key => $val) {
            if (
                isset($val['options']['route'])
                && str_ends_with($val['options']['route'], '[:id[/[:tab]]]')
            ) {
                $newRoute = $key . '-' . strtolower($action);
                if (isset($this->mainConfig['router']['routes'][$newRoute])) {
                    $output->writeln($newRoute . ' already exists; skipping.');
                } else {
                    $val['options']['route'] = str_replace(
                        '[:id[/[:tab]]]',
                        "[:id]/$action",
                        $val['options']['route']
                    );
                    $val['options']['defaults']['action'] = $action;
                    $config['router']['routes'][$newRoute] = $val;
                }
            }
        }

        // Write updated configuration
        $this->generatorTools->writeModuleConfig($configPath, $config);
        return 0;
    }
}
