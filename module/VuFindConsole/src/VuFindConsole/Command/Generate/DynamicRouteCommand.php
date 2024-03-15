<?php

/**
 * Console command: Generate dynamic route.
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

/**
 * Console command: Generate dynamic route.
 *
 * @category VuFind
 * @package  Console
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
#[AsCommand(
    name: 'generate/dynamicroute',
    description: 'Dynamic route generator'
)]
class DynamicRouteCommand extends AbstractRouteCommand
{
    /**
     * Configure the command.
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setHelp('Adds a dynamic route.')
            ->addArgument(
                'route',
                InputArgument::REQUIRED,
                'the route name (used by router), e.g. customList'
            )->addArgument(
                'controller',
                InputArgument::REQUIRED,
                'the controller name (used in URL), e.g. MyResearch'
            )->addArgument(
                'action',
                InputArgument::REQUIRED,
                'the action and segment params, e.g. CustomList/[:id]'
            )->addArgument(
                'target_module',
                InputArgument::REQUIRED,
                'the module where the new route will be generated'
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
        $route = $input->getArgument('route');
        $controller = $input->getArgument('controller');
        $action = $input->getArgument('action');
        $module = $input->getArgument('target_module');

        $this->generatorTools->setOutputInterface($output);

        // Create backup of configuration
        $configPath = $this->generatorTools->getModuleConfigPath($module);
        $this->generatorTools->backUpFile($configPath);

        // Append the route
        $config = include $configPath;
        $this->routeGenerator
            ->addDynamicRoute($config, $route, $controller, $action);

        // Write updated configuration
        $this->generatorTools->writeModuleConfig($configPath, $config);
        return 0;
    }
}
