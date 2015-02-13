<?php
/**
 * CLI Controller Module (language tools)
 *
 * PHP version 5
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Controller
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_a_controller Wiki
 */
namespace VuFindConsole\Controller;
use Zend\Code\Generator\ClassGenerator;
use Zend\Code\Generator\FileGenerator;
use Zend\Console\Console;

/**
 * This controller handles various command-line tools for dealing with language files
 *
 * @category VuFind2
 * @package  Controller
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_a_controller Wiki
 */
class GenerateController extends AbstractBase
{
    /**
     * Copy one language string to another
     *
     * @return \Zend\Console\Response
     */
    public function extendserviceAction()
    {
        // Display help message if parameters missing:
        $argv = $this->consoleOpts->getRemainingArgs();
        if (!isset($argv[1])) {
            Console::writeLine(
                "Usage: {$_SERVER['argv'][0]} [config_path] [target_module]"
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

        $source = $argv[0];
        $target = $argv[1];

        $parts = explode('/', $source);
        $partCount = count($parts);
        if ($partCount < 3) {
            Console::writeLine("Config path too short.");
            return $this->getFailureResponse();
        }
        $sourceType = $parts[$partCount - 2];

        $supportedTypes = array('factories', 'invokables');
        if (!in_array($sourceType, $supportedTypes)) {
            Console::writeLine(
                'Unsupported service type; supported values: '
                . implode(', ', $supportedTypes)
            );
            return $this->getFailureResponse();
        }

        $config = $this->retrieveConfig($parts);
        if (!$config) {
            Console::writeLine("{$source} not found in configuration.");
            return $this->getFailureResponse();
        }

        try {
            switch ($sourceType) {
            case 'factories':
                $newConfig = $this->cloneFactory($config, $target);
                break;
            case 'invokables':
                $newConfig = $this->createSubclassInModule($config, $target);
                break;
            default:
                throw new \Exception('Reached unreachable code!');
            }
            $this->writeNewConfig($parts, $newConfig, $target);
        } catch (\Exception $e) {
            Console::writeLine($e->getMessage());
            return $this->getFailureResponse();
        }

        return $this->getSuccessResponse();
    }

    /**
     * Create a new subclass and factory to override a factory-generated
     * service.
     *
     * @param mixed  $factory Factory configuration for class to extend
     * @param string $module  Module in which to create the new factory
     *
     * @return string
     * @throws \Exception
     */
    protected function cloneFactory($factory, $module)
    {
        throw new \Exception('Factories not supported yet.');
    }

    /**
     * Extend a specified class within a specified module. Return the name of
     * the new subclass.
     *
     * @param string $class  Name of class to extend
     * @param string $module Module in which to create the new invokable
     *
     * @return string
     * @throws \Exception
     */
    protected function createSubclassInModule($class, $module)
    {
        // Determine the name of the new class by exploding the old class and
        // replacing the namespace:
        $parts = explode('\\', $class);
        if (count($parts) < 2) {
            throw new \Exception('Expected a namespaced class; found ' . $class);
        }
        $parts[0] = $module;
        $newClass = implode('\\', $parts);

        // Use the class name parts from the previous step to determine a path
        // and filename, then create the new path.
        $filename = array_pop($parts) . '.php';
        array_unshift($parts, 'module', $module, 'src');
        $this->createTree($parts);

        // Generate the new class:
        $generator = FileGenerator::fromArray(
            [
                'classes' => [new ClassGenerator($newClass, null, null, "\\$class")]
            ]
        );
        $fullPath = APPLICATION_PATH . '/' . implode('/', $parts) . '/' . $filename;
        if (file_exists($fullPath)) {
            throw new \Exception("$fullPath already exists.");
        }
        if (!file_put_contents($fullPath, $generator->generate())) {
            throw new \Exception("Problem writing to $fullPath.");
        }
        Console::writeLine("Generated new file: $fullPath");

        // Send back the name of the new class:
        return $newClass;
    }

    /**
     * Create a directory tree.
     *
     * @param array $path Array of subdirectories to create relative to
     * APPLICATION_PATH
     *
     * @return void
     * @throws \Exception
     */
    protected function createTree($path)
    {
        $fullPath = APPLICATION_PATH;
        foreach ($path as $part) {
            $fullPath .= '/' . $part;
            if (!file_exists($fullPath)) {
                if (!mkdir($fullPath)) {
                    throw new \Exception("Problem creating $fullPath");
                }
            }
            if (!is_dir($fullPath)) {
                throw new \Exception("$fullPath is not a directory!");
            }
        }
    }

    /**
     * Update the configuration of a target module.
     *
     * @param array  $path    Representation of path in config array
     * @param string $setting New setting to write into config
     * @param string $module  Module in which to write the configuration
     *
     * @return void
     * @throws \Exception
     */
    protected function writeNewConfig($path, $setting, $module)
    {
        // Locate module configuration
        $configPath = APPLICATION_PATH . "/module/$module/config/module.config.php";
        if (!file_exists($configPath)) {
            throw new \Exception("Cannot find $configPath");
        }

        // Create backup of configuration
        $backup = $configPath . '.' . time() . '.bak';
        if (!copy($configPath, $backup)) {
            throw new \Exception("Problem generating backup file: $backup");
        }
        Console::writeLine("Created configuration backup: $backup");

        $config = require $configPath;
        $current = & $config;
        $finalStep = array_pop($path);
        foreach ($path as $step) {
            if (!is_array($current)) {
                throw new \Exception('Unexpected non-array: ' . $current);
            }
            if (!isset($current[$step])) {
                $current[$step] = [];
            }
            $current = & $current[$step];
        }
        if (!is_array($current)) {
            throw new \Exception('Unexpected non-array: ' . $current);
        }
        $current[$finalStep] = $setting;

        $generator = FileGenerator::fromArray(
            [
                'body' => 'return ' . var_export($config, true) . ';'
            ]
        );
        if (!file_put_contents($configPath, $generator->generate())) {
            throw new \Exception("Cannot write to $configPath");
        }
        Console::writeLine("Successfully updated $configPath");
    }

    /**
     * Retrieve a value from the application configuration (or return false
     * if the path is not found).
     *
     * @param array $path Path to walk through configuration
     *
     * @return mixed
     */
    protected function retrieveConfig(array $path)
    {
        $config = $this->getServiceLocator()->get('config');
        foreach ($path as $part) {
            if (!isset($config[$part])) {
                return false;
            }
            $config = $config[$part];
        }
        return $config;
    }
}
