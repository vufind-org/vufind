<?php

/**
 * Console command: Generate plugin.
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
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Console command: Generate plugin.
 *
 * @category VuFind
 * @package  Console
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
#[AsCommand(
    name: 'generate/plugin',
    description: 'Plugin generator'
)]
class PluginCommand extends AbstractContainerAwareCommand
{
    /**
     * Configure the command.
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setHelp('Creates a new plugin class.')
            ->addArgument(
                'class_name',
                InputArgument::REQUIRED,
                'the name of the class you wish to create'
            )->addArgument(
                'factory',
                InputArgument::OPTIONAL,
                'an existing factory to use (omit to generate a new one)'
            )->addOption(
                'top-level',
                null,
                InputOption::VALUE_NONE,
                'when set, create the plugin as a top-level service instead of'
                . ' inside a plugin manager'
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
        $class = $input->getArgument('class_name');
        $factory = $input->getArgument('factory');
        $topLevel = $input->getOption('top-level');
        try {
            $this->generatorTools->setOutputInterface($output)
                ->createPlugin($this->container, $class, $factory, $topLevel);
        } catch (\Exception $e) {
            $output->writeln($e->getMessage());
            return 1;
        }
        return 0;
    }
}
