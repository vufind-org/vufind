<?php
/**
 * CLI Controller Module (language tools)
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2010.
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
 * @package  Controller
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
namespace VuFindConsole\Controller;

use Laminas\Console\Console;

/**
 * This controller handles various command-line tools for dealing with language files
 *
 * @category VuFind
 * @package  Controller
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
class GenerateController extends AbstractBase
{
    /**
     * Add a new non-tab record action to all existing record routes
     *
     * @return \Laminas\Console\Response
     */
    public function nontabrecordactionAction()
    {
        $request = $this->getRequest();
        $action = $request->getParam('newAction');
        $module = $request->getParam('module');
        if (empty($action) || empty($module)) {
            Console::writeLine(
                'Usage: ' . $request->getScriptName()
                . ' generate nontabrecordaction [action] [target_module]'
            );
            Console::writeLine(
                "\taction - new action to add"
            );
            Console::writeLine(
                "\ttarget_module - the module where the new routes will be generated"
            );
            return $this->getFailureResponse();
        }

        // Create backup of configuration
        $generator = $this->getGeneratorTools();
        $configPath = $generator->getModuleConfigPath($module);
        $generator->backUpFile($configPath);

        // Load the route config
        $config = include $configPath;

        // Append the route
        $mainConfig = $this->serviceLocator->get('Config');
        foreach ($mainConfig['router']['routes'] as $key => $val) {
            if (isset($val['options']['route'])
                && substr($val['options']['route'], -14) == '[:id[/[:tab]]]'
            ) {
                $newRoute = $key . '-' . strtolower($action);
                if (isset($mainConfig['router']['routes'][$newRoute])) {
                    Console::writeLine($newRoute . ' already exists; skipping.');
                } else {
                    $val['options']['route'] = str_replace(
                        '[:id[/[:tab]]]', "[:id]/$action", $val['options']['route']
                    );
                    $val['options']['defaults']['action'] = $action;
                    $config['router']['routes'][$newRoute] = $val;
                }
            }
        }

        // Write updated configuration
        $generator->writeModuleConfig($configPath, $config);
        return $this->getSuccessResponse();
    }

    /**
     * Create a custom theme from the template, configure.
     *
     * @return \Laminas\Console\Response
     */
    public function themeAction()
    {
        // Validate command line argument:
        $request = $this->getRequest();
        $name = $request->getParam('themename');
        if (empty($name)) {
            Console::writeLine("\tNo themename found, using \"custom\"");
            $name = 'custom';
        }

        // Use the theme generator to create and configure the theme:
        $generator = $this->serviceLocator->get(\VuFindTheme\ThemeGenerator::class);
        if (!$generator->generate($name)
            || !$generator->configure($this->getConfig(), $name)
        ) {
            Console::writeLine($generator->getLastError());
            return $this->getFailureResponse();
        }
        Console::writeLine("\tFinished.");
        return $this->getSuccessResponse();
    }

    /**
     * Create a custom theme from the template.
     *
     * @return \Laminas\Console\Response
     */
    public function thememixinAction()
    {
        // Validate command line argument:
        $request = $this->getRequest();
        $name = $request->getParam('name');
        if (empty($name)) {
            Console::writeLine("\tNo mixin name found, using \"custom\"");
            $name = 'custom';
        }

        // Use the theme generator to create and configure the theme:
        $generator = $this->serviceLocator->get(\VuFindTheme\MixinGenerator::class);
        if (!$generator->generate($name)) {
            Console::writeLine($generator->getLastError());
            return $this->getFailureResponse();
        }
        Console::writeLine(
            "\tFinished. Add to your theme.config.php 'mixins' setting to activate."
        );
        return $this->getSuccessResponse();
    }

    /**
     * Get generator tools
     *
     * @return \VuFindConsole\Generator\GeneratorTools
     */
    protected function getGeneratorTools()
    {
        return $this->serviceLocator->get(
            \VuFindConsole\Generator\GeneratorTools::class
        );
    }
}
