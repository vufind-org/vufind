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

use Zend\Console\Console;

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
     * Add a new dynamic route definition
     *
     * @return \Zend\Console\Response
     */
    public function dynamicrouteAction()
    {
        $request = $this->getRequest();
        $route = $request->getParam('name');
        $controller = $request->getParam('newController');
        $action = $request->getParam('newAction');
        $module = $request->getParam('module');
        if (empty($module)) {
            Console::writeLine(
                'Usage: ' . $request->getScriptName() . ' generate dynamicroute'
                . ' [route] [controller] [action] [target_module]'
            );
            Console::writeLine(
                "\troute - the route name (used by router), e.g. customList"
            );
            Console::writeLine(
                "\tcontroller - the controller name (used in URL), e.g. MyResearch"
            );
            Console::writeLine(
                "\taction - the action and segment params, e.g. CustomList/[:id]"
            );
            Console::writeLine(
                "\ttarget_module - the module where the new route will be generated"
            );
            return $this->getFailureResponse();
        }

        // Create backup of configuration
        $generator = $this->getGeneratorTools();
        $configPath = $generator->getModuleConfigPath($module);
        $generator->backUpFile($configPath);

        // Append the route
        $config = include $configPath;
        $routeGenerator = new \VuFind\Route\RouteGenerator();
        $routeGenerator->addDynamicRoute($config, $route, $controller, $action);

        // Write updated configuration
        $generator->writeModuleConfig($configPath, $config);
        return $this->getSuccessResponse();
    }

    /**
     * Extend an existing class
     *
     * @return \Zend\Console\Response
     */
    public function extendclassAction()
    {
        // Display help message if parameters missing:
        $request = $this->getRequest();
        $class = $request->getParam('class');
        $target = $request->getParam('target');
        $extendFactory = $request->getParam('extendfactory');

        if (empty($class) || empty($target)) {
            Console::writeLine(
                'Usage: ' . $request->getScriptName() . ' generate extendclass'
                . ' [--extendfactory] [class_name] [target_module]'
            );
            Console::writeLine(
                "\t--extendfactory - optional switch; when set, subclass "
                . 'the factory; otherwise, use existing factory'
            );
            Console::writeLine(
                "\tclass_name - the name of the class you wish to extend"
            );
            Console::writeLine(
                "\ttarget_module - the module where the new class will be generated"
            );
            return $this->getFailureResponse();
        }

        try {
            $this->getGeneratorTools()->extendClass(
                $this->serviceLocator, $class, $target, $extendFactory
            );
        } catch (\Exception $e) {
            Console::writeLine($e->getMessage());
            return $this->getFailureResponse();
        }

        return $this->getSuccessResponse();
    }

    /**
     * Extend an existing service
     *
     * @return \Zend\Console\Response
     */
    public function extendserviceAction()
    {
        // Display help message if parameters missing:
        $request = $this->getRequest();
        $source = $request->getParam('source');
        $target = $request->getParam('target');
        if (empty($source) || empty($target)) {
            Console::writeLine(
                'Usage: ' . $request->getScriptName() . ' generate extendservice'
                . ' [config_path] [target_module]'
            );
            Console::writeLine(
                "\tconfig_path - the path to the service in the framework config"
            );
            Console::writeLine("\t\te.g. controllers/invokables/generate");
            Console::writeLine(
                "\ttarget_module - the module where the new class will be generated"
            );
            return $this->getFailureResponse();
        }

        try {
            $this->getGeneratorTools()->extendService($source, $target);
        } catch (\Exception $e) {
            Console::writeLine($e->getMessage());
            return $this->getFailureResponse();
        }

        return $this->getSuccessResponse();
    }

    /**
     * Add a new non-tab record action to all existing record routes
     *
     * @return \Zend\Console\Response
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
     * Create a new plugin class
     *
     * @return \Zend\Console\Response
     */
    public function pluginAction()
    {
        // Display help message if parameters missing:
        $request = $this->getRequest();
        $class = $request->getParam('class');
        $factory = $request->getParam('factory');

        if (empty($class)) {
            Console::writeLine(
                'Usage: ' . $request->getScriptName() . ' generate plugin'
                . ' [class_name] [factory]'
            );
            Console::writeLine(
                "\tclass_name - the name of the class you wish to create"
            );
            Console::writeLine(
                "\tfactory - an existing factory to use (omit to generate a new one)"
            );
            return $this->getFailureResponse();
        }

        try {
            $this->getGeneratorTools()
                ->createPlugin($this->serviceLocator, $class, $factory);
        } catch (\Exception $e) {
            Console::writeLine($e->getMessage());
            return $this->getFailureResponse();
        }

        return $this->getSuccessResponse();
    }

    /**
     * Add a new record route definition
     *
     * @return \Zend\Console\Response
     */
    public function recordrouteAction()
    {
        $request = $this->getRequest();
        $base = $request->getParam('base');
        $controller = $request->getParam('newController');
        $module = $request->getParam('module');
        if (empty($module)) {
            Console::writeLine(
                'Usage: ' . $request->getScriptName() . ' generate recordroute'
                . ' [base] [controller] [target_module]'
            );
            Console::writeLine(
                "\tbase - the base route name (used by router), e.g. record"
            );
            Console::writeLine(
                "\tcontroller - the controller name (used in URL), e.g. Record"
            );
            Console::writeLine(
                "\ttarget_module - the module where the new route will be generated"
            );
            return $this->getFailureResponse();
        }

        // Create backup of configuration
        $generator = $this->getGeneratorTools();
        $configPath = $generator->getModuleConfigPath($module);
        $generator->backUpFile($configPath);

        // Append the route
        $config = include $configPath;
        $routeGenerator = new \VuFind\Route\RouteGenerator();
        $routeGenerator->addRecordRoute($config, $base, $controller);

        // Write updated configuration
        $generator->writeModuleConfig($configPath, $config);
        return $this->getSuccessResponse();
    }

    /**
     * Add a new static route definition
     *
     * @return \Zend\Console\Response
     */
    public function staticrouteAction()
    {
        $request = $this->getRequest();
        $route = $request->getParam('name');
        $module = $request->getParam('module');
        if (empty($module)) {
            Console::writeLine(
                'Usage: ' . $request->getScriptName() . ' generate staticroute'
                . ' [route_definition] [target_module]'
            );
            Console::writeLine(
                "\troute_definition - a Controller/Action string, e.g. Search/Home"
            );
            Console::writeLine(
                "\ttarget_module - the module where the new route will be generated"
            );
            return $this->getFailureResponse();
        }

        // Create backup of configuration
        $generator = $this->getGeneratorTools();
        $configPath = $generator->getModuleConfigPath($module);
        $generator->backUpFile($configPath);

        // Append the route
        $config = include $configPath;
        $routeGenerator = new \VuFind\Route\RouteGenerator();
        $routeGenerator->addStaticRoute($config, $route);

        // Write updated configuration
        $generator->writeModuleConfig($configPath, $config);
        return $this->getSuccessResponse();
    }

    /**
     * Create a custom theme from the template, configure.
     *
     * @return \Zend\Console\Response
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
        $generator = $this->serviceLocator->get('VuFindTheme\ThemeGenerator');
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
     * @return \Zend\Console\Response
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
        $generator = $this->serviceLocator->get('VuFindTheme\MixinGenerator');
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
        return $this->serviceLocator->get('VuFindConsole\Generator\GeneratorTools');
    }
}
