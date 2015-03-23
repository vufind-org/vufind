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
use Zend\Code\Generator\MethodGenerator;
use Zend\Code\Generator\FileGenerator;
use Zend\Code\Reflection\ClassReflection;
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
     * Add a new dynamic route definition
     *
     * @return \Zend\Console\Response
     */
    public function dynamicrouteAction()
    {
        $argv = $this->consoleOpts->getRemainingArgs();
        if (!isset($argv[3])) {
            Console::writeLine(
                "Usage: {$_SERVER['argv'][0]} [route] [controller] [action]"
                . " [target_module]"
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

        $route = $argv[0];
        $controller = $argv[1];
        $action = $argv[2];
        $module = $argv[3];

        // Create backup of configuration
        $configPath = $this->getModuleConfigPath($module);
        $this->backUpFile($configPath);

        // Append the route
        $config = include $configPath;
        $routeGenerator = new \VuFind\Route\RouteGenerator();
        $routeGenerator->addDynamicRoute($config, $route, $controller, $action);

        // Write updated configuration
        $this->writeModuleConfig($configPath, $config);
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

        $supportedTypes = ['factories', 'invokables'];
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
     * Add a new non-tab record action to all existing record routes
     *
     * @return \Zend\Console\Response
     */
    public function nontabrecordactionAction()
    {
        $argv = $this->consoleOpts->getRemainingArgs();
        if (!isset($argv[1])) {
            Console::writeLine(
                "Usage: {$_SERVER['argv'][0]} [action] [target_module]"
            );
            Console::writeLine(
                "\taction - new action to add"
            );
            Console::writeLine(
                "\ttarget_module - the module where the new routes will be generated"
            );
            return $this->getFailureResponse();
        }

        $action = $argv[0];
        $module = $argv[1];

        // Create backup of configuration
        $configPath = $this->getModuleConfigPath($module);
        $this->backUpFile($configPath);

        // Load the route config
        $config = include $configPath;

        // Append the route
        $mainConfig = $this->getServiceLocator()->get('Config');
        foreach ($mainConfig['router']['routes'] as $key => $val) {
            if (isset($val['options']['route'])
                && substr($val['options']['route'], -12) == '[:id[/:tab]]'
            ) {
                $newRoute = $key . '-' . strtolower($action);
                if (isset($mainConfig['router']['routes'][$newRoute])) {
                    Console::writeLine($newRoute . ' already exists; skipping.');
                } else {
                    $val['options']['route'] = str_replace(
                        '[:id[/:tab]]', "[:id]/$action", $val['options']['route']
                    );
                    $val['options']['defaults']['action'] = $action;
                    $config['router']['routes'][$newRoute] = $val;
                }
            }
        }

        // Write updated configuration
        $this->writeModuleConfig($configPath, $config);
        return $this->getSuccessResponse();
    }

    /**
     * Add a new record route definition
     *
     * @return \Zend\Console\Response
     */
    public function recordrouteAction()
    {
        $argv = $this->consoleOpts->getRemainingArgs();
        if (!isset($argv[2])) {
            Console::writeLine(
                "Usage: {$_SERVER['argv'][0]} [base] [controller] [target_module]"
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

        $base = $argv[0];
        $controller = $argv[1];
        $module = $argv[2];

        // Create backup of configuration
        $configPath = $this->getModuleConfigPath($module);
        $this->backUpFile($configPath);

        // Append the route
        $config = include $configPath;
        $routeGenerator = new \VuFind\Route\RouteGenerator();
        $routeGenerator->addRecordRoute($config, $base, $controller);

        // Write updated configuration
        $this->writeModuleConfig($configPath, $config);
        return $this->getSuccessResponse();
    }

    /**
     * Add a new static route definition
     *
     * @return \Zend\Console\Response
     */
    public function staticrouteAction()
    {
        $argv = $this->consoleOpts->getRemainingArgs();
        if (!isset($argv[1])) {
            Console::writeLine(
                "Usage: {$_SERVER['argv'][0]} [route_definition] [target_module]"
            );
            Console::writeLine(
                "\troute_definition - a Controller/Action string, e.g. Search/Home"
            );
            Console::writeLine(
                "\ttarget_module - the module where the new route will be generated"
            );
            return $this->getFailureResponse();
        }

        $route = $argv[0];
        $module = $argv[1];

        // Create backup of configuration
        $configPath = $this->getModuleConfigPath($module);
        $this->backUpFile($configPath);

        // Append the route
        $config = include $configPath;
        $routeGenerator = new \VuFind\Route\RouteGenerator();
        $routeGenerator->addStaticRoute($config, $route);

        // Write updated configuration
        $this->writeModuleConfig($configPath, $config);
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
        // Make sure we can figure out how to handle the factory; it should
        // either be a [controller, method] array or a "controller::method"
        // string; anything else will cause a problem.
        $parts = is_string($factory) ? explode('::', $factory) : $factory;
        if (!is_array($parts) || count($parts) != 2 || !class_exists($parts[0])
            || !method_exists($parts[0], $parts[1])
        ) {
            throw new \Exception('Unexpected factory configuration format.');
        }
        list($factoryClass, $factoryMethod) = $parts;
        $newFactoryClass = $this->generateLocalClassName($factoryClass, $module);
        if (!class_exists($newFactoryClass)) {
            $this->createClassInModule($newFactoryClass, $module);
            $skipBackup = true;
        } else {
            $skipBackup = false;
        }
        if (method_exists($newFactoryClass, $factoryMethod)) {
            throw new \Exception("$newFactoryClass::$factoryMethod already exists.");
        }

        $oldReflection = new ClassReflection($factoryClass);
        $newReflection = new ClassReflection($newFactoryClass);

        $generator = ClassGenerator::fromReflection($newReflection);
        $method = MethodGenerator::fromReflection(
            $oldReflection->getMethod($factoryMethod)
        );
        $this->createServiceClassAndUpdateFactory(
            $method, $oldReflection->getNamespaceName(), $module
        );
        $generator->addMethodFromGenerator($method);
        $this->writeClass($generator, $module, true, $skipBackup);

        return $newFactoryClass . '::' . $factoryMethod;
    }

    /**
     * Given a factory method, extend the class being constructed and create
     * a new factory for the subclass.
     *
     * @param MethodGenerator $method Method to modify
     * @param string          $ns     Namespace of old factory
     * @param string          $module Module in which to make changes
     *
     * @return void
     * @throws \Exception
     */
    protected function createServiceClassAndUpdateFactory(MethodGenerator $method,
        $ns, $module
    ) {
        $body = $method->getBody();
        $regex = '/new\s+([\w\\\\]*)\s*\(/m';
        preg_match_all($regex, $body, $matches);
        $classNames = $matches[1];
        $count = count($classNames);
        if ($count != 1) {
            throw new \Exception("Found $count class names; expected 1.");
        }
        $className = $classNames[0];
        // Figure out fully qualified name for purposes of createSubclassInModule():
        $fqClassName = (substr($className, 0, 1) != '\\')
            ? "$ns\\$className" : $className;
        $newClass = $this->createSubclassInModule($fqClassName, $module);
        $body = preg_replace(
            '/new\s+' . addslashes($className) . '\s*\(/m',
            'new \\' . $newClass . '(',
            $body
        );
        $method->setBody($body);
    }

    /**
     * Determine the name of a local replacement class within the specified
     * module.
     *
     * @param string $class  Name of class to extend/replace
     * @param string $module Module in which to create the new class
     *
     * @return string
     * @throws \Exception
     */
    protected function generateLocalClassName($class, $module)
    {
        // Determine the name of the new class by exploding the old class and
        // replacing the namespace:
        $parts = explode('\\', trim($class, '\\'));
        if (count($parts) < 2) {
            throw new \Exception('Expected a namespaced class; found ' . $class);
        }
        $parts[0] = $module;
        return implode('\\', $parts);
    }

    /**
     * Extend a specified class within a specified module. Return the name of
     * the new subclass.
     *
     * @param string $class  Name of class to create
     * @param string $module Module in which to create the new class
     * @param string $parent Parent class (null for no parent)
     *
     * @return void
     * @throws \Exception
     */
    protected function createClassInModule($class, $module, $parent = null)
    {
        $generator = new ClassGenerator($class, null, null, $parent);
        return $this->writeClass($generator, $module);
    }

    /**
     * Write a class to disk.
     *
     * @param ClassGenerator $classGenerator Representation of class to write
     * @param string         $module         Module in which to write class
     * @param bool           $allowOverwrite Allow overwrite of existing file?
     * @param bool           $skipBackup     Should we skip backing up the file?
     *
     * @return void
     * @throws \Exception
     */
    protected function writeClass(ClassGenerator $classGenerator, $module,
        $allowOverwrite = false, $skipBackup = false
    ) {
        // Use the class name parts from the previous step to determine a path
        // and filename, then create the new path.
        $parts = explode('\\', $classGenerator->getNamespaceName());
        array_unshift($parts, 'module', $module, 'src');
        $this->createTree($parts);

        // Generate the new class:
        $generator = FileGenerator::fromArray(['classes' => [$classGenerator]]);
        $filename = $classGenerator->getName() . '.php';
        $fullPath = APPLICATION_PATH . '/' . implode('/', $parts) . '/' . $filename;
        if (file_exists($fullPath)) {
            if ($allowOverwrite) {
                if (!$skipBackup) {
                    $this->backUpFile($fullPath);
                }
            } else {
                throw new \Exception("$fullPath already exists.");
            }
        }
        if (!file_put_contents($fullPath, $generator->generate())) {
            throw new \Exception("Problem writing to $fullPath.");
        }
        Console::writeLine("Saved file: $fullPath");
    }

    /**
     * Extend a specified class within a specified module. Return the name of
     * the new subclass.
     *
     * @param string $class  Name of class to extend
     * @param string $module Module in which to create the new class
     *
     * @return string
     * @throws \Exception
     */
    protected function createSubclassInModule($class, $module)
    {
        // Normalize leading backslashes; in some contexts we will
        // have them and in others we may not.
        $class = trim($class, '\\');
        $newClass = $this->generateLocalClassName($class, $module);
        $this->createClassInModule($newClass, $module, "\\$class");
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
     * Create a backup of a file.
     *
     * @param string $filename File to back up
     *
     * @return void
     * @throws \Exception
     */
    protected function backUpFile($filename)
    {
        $backup = $filename . '.' . time() . '.bak';
        if (!copy($filename, $backup)) {
            throw new \Exception("Problem generating backup file: $backup");
        }
        Console::writeLine("Created backup: $backup");
    }

    /**
     * Get the path to the module configuration; throw an exception if it is
     * missing.
     *
     * @param string $module Module name
     *
     * @return string
     * @throws \Exception
     */
    protected function getModuleConfigPath($module)
    {
        $configPath = APPLICATION_PATH . "/module/$module/config/module.config.php";
        if (!file_exists($configPath)) {
            throw new \Exception("Cannot find $configPath");
        }
        return $configPath;
    }

    /**
     * Write a module configuration.
     *
     * @param string $configPath Path to write to
     * @param string $config     Configuration array to write
     *
     * @return void
     * @throws \Exception
     */
    protected function writeModuleConfig($configPath, $config)
    {
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
        // Create backup of configuration
        $configPath = $this->getModuleConfigPath($module);
        $this->backUpFile($configPath);

        $config = include $configPath;
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

        // Write updated configuration
        $this->writeModuleConfig($configPath, $config);
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
