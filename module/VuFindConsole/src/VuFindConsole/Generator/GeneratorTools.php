<?php
/**
 * Generator tools.
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2018.
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
 * @package  Generator
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace VuFindConsole\Generator;

use Interop\Container\ContainerInterface;
use Zend\Code\Generator\ClassGenerator;
use Zend\Code\Generator\FileGenerator;
use Zend\Code\Generator\MethodGenerator;
use Zend\Code\Reflection\ClassReflection;
use Zend\Console\Console;

/**
 * Generator tools.
 *
 * @category VuFind
 * @package  Generator
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class GeneratorTools
{
    /**
     * Zend Framework configuration
     *
     * @var array
     */
    protected $config;

    /**
     * Constructor.
     *
     * @param array $config Zend Framework configuration
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Create a plugin class.
     *
     * @param ContainerInterface $container Service manager
     * @param string             $class     Class name to create
     * @param string             $factory   Existing factory to use (null to
     * generate a new one)
     *
     * @return bool
     * @throws \Exception
     */
    public function createPlugin(ContainerInterface $container, $class,
        $factory = null
    ) {
        // Derive some key bits of information from the new class name:
        $classParts = explode('\\', $class);
        $module = $classParts[0];
        $shortName = strtolower(array_pop($classParts));
        $classParts[0] = 'VuFind';
        $classParts[] = 'PluginManager';
        $pmClass = implode('\\', $classParts);
        // Set a flag for whether to generate a factory, and create class name
        // if necessary. If existing factory specified, ensure it really exists.
        if ($generateFactory = empty($factory)) {
            $factory = $class . 'Factory';
        } elseif (!class_exists($factory)) {
            throw new \Exception("Undefined factory: $factory");
        }

        // Figure out further information based on the plugin manager:
        if (!$container->has($pmClass)) {
            throw new \Exception('Cannot find expected plugin manager: ' . $pmClass);
        }
        $pm = $container->get($pmClass);
        if (!method_exists($pm, 'getExpectedInterface')) {
            throw new \Exception(
                $pmClass . ' does not implement getExpectedInterface!'
            );
        }

        // Force getExpectedInterface() to be public so we can read it:
        $reflectionMethod = new \ReflectionMethod($pm, 'getExpectedInterface');
        $reflectionMethod->setAccessible(true);
        $interface = $reflectionMethod->invoke($pm);

        // Figure out whether the plugin requirement is an interface or a
        // parent class so we can create the right thing....
        if (interface_exists($interface)) {
            $parent = null;
            $interfaces = [$interface];
        } else {
            $parent = $interface;
            $interfaces = [];
        }
        $apmFactory = new \VuFind\ServiceManager\AbstractPluginManagerFactory();
        $pmKey = $apmFactory->getConfigKey(get_class($pm));
        $configPath = ['vufind', 'plugin_managers', $pmKey];

        // Generate the classes and configuration:
        $this->createClassInModule($class, $module, $parent, $interfaces);
        if ($generateFactory) {
            $this->generateFactory($class, $factory, $module);
        }
        $factoryPath = array_merge($configPath, ['factories', $class]);
        $this->writeNewConfig($factoryPath, $factory, $module);
        $aliasPath = array_merge($configPath, ['aliases', $shortName]);
        // Don't back up the config twice -- the first backup from the previous
        // write operation is sufficient.
        $this->writeNewConfig($aliasPath, $class, $module, false);

        return true;
    }

    /**
     * Generate a factory class.
     *
     * @param string $class   Name of class being built by factory
     * @param string $factory Name of factory to generate
     * @param string $module  Name of module to generate factory within
     *
     * @return void
     */
    protected function generateFactory($class, $factory, $module)
    {
        $this->createClassInModule(
            $factory, $module, null,
            ['Zend\ServiceManager\Factory\FactoryInterface'],
            function ($generator) use ($class) {
                $method = MethodGenerator::fromArray(
                    [
                        'name' => '__invoke',
                        'body' => 'return new \\' . $class . '();',
                    ]
                );
                $param1 = [
                    'name' => 'container',
                    'type' => 'Interop\Container\ContainerInterface'
                ];
                $param2 = [
                    'name' => 'requestedName',
                ];
                $param3 = [
                    'name' => 'options',
                    'type' => 'array',
                    'defaultValue' => null,
                ];
                $method->setParameters([$param1, $param2, $param3]);
                // Copy doc block from this class' factory:
                $reflection = new \Zend\Code\Reflection\MethodReflection(
                    GeneratorToolsFactory::class, '__invoke'
                );
                $example = MethodGenerator::fromReflection($reflection);
                $method->setDocBlock($example->getDocBlock());
                $generator->addMethods([$method]);
            }
        );
    }

    /**
     * Extend a class defined somewhere in the service manager or its child
     * plugin managers.
     *
     * @param ContainerInterface $container     Service manager
     * @param string             $class         Class name to extend
     * @param string             $target        Target module in which to create new
     * service
     * @param bool               $extendFactory Should we extend the factory?
     *
     * @return bool
     * @throws \Exception
     */
    public function extendClass(ContainerInterface $container, $class, $target,
        $extendFactory = false
    ) {
        // Set things up differently depending on whether this is a top-level
        // service or a class in a plugin manager.
        $cm = $container->get('ControllerManager');
        $cpm = $container->get('ControllerPluginManager');
        $delegators = [];
        if ($container->has($class)) {
            $factory = $this->getFactoryFromContainer($container, $class);
            $configPath = ['service_manager'];
        } elseif ($factory = $this->getFactoryFromContainer($cm, $class)) {
            $configPath = ['controllers'];
        } elseif ($factory = $this->getFactoryFromContainer($cpm, $class)) {
            $configPath = ['controller_plugins'];
        } elseif ($pm = $this->getPluginManagerContainingClass($container, $class)) {
            $apmFactory = new \VuFind\ServiceManager\AbstractPluginManagerFactory();
            $pmKey = $apmFactory->getConfigKey(get_class($pm));
            $factory = $this->getFactoryFromContainer($pm, $class);
            $configPath = ['vufind', 'plugin_managers', $pmKey];
            $delegators = $this->getDelegatorsFromContainer($pm, $class);
        }

        // No factory found? Throw an error!
        if (empty($factory)) {
            throw new \Exception('Could not find factory for ' . $class);
        }

        // Create the custom subclass.
        $newClass = $this->createSubclassInModule($class, $target);

        // Create the custom factory only if requested.
        $newFactory = $extendFactory
            ? $this->cloneFactory($factory, $target) : $factory;

        // Finalize the local module configuration -- create a factory for the
        // new class, and set up the new class as an alias for the old class.
        $factoryPath = array_merge($configPath, ['factories', $newClass]);
        $this->writeNewConfig($factoryPath, $newFactory, $target);
        $aliasPath = array_merge($configPath, ['aliases', $class]);
        // Don't back up the config twice -- the first backup from the previous
        // write operation is sufficient.
        $this->writeNewConfig($aliasPath, $newClass, $target, false);

        // Clone/configure delegator factories as needed.
        if (!empty($delegators)) {
            $newDelegators = [];
            foreach ($delegators as $delegator) {
                $newDelegators[] = $extendFactory
                    ? $this->cloneFactory($delegator, $target) : $delegator;
            }
            $delegatorPath = array_merge($configPath, ['delegators', $newClass]);
            $this->writeNewConfig($delegatorPath, $newDelegators, $target, false);
        }

        return true;
    }

    /**
     * Get a list of factories in the provided container.
     *
     * @param ContainerInterface $container Container to inspect
     *
     * @return array
     */
    protected function getAllFactoriesFromContainer(ContainerInterface $container)
    {
        // There is no "getFactories" method, so we need to use reflection:
        $reflectionProperty = new \ReflectionProperty($container, 'factories');
        $reflectionProperty->setAccessible(true);
        return $reflectionProperty->getValue($container);
    }

    /**
     * Get a factory from the provided container (or null if undefined).
     *
     * @param ContainerInterface $container Container to inspect
     * @param string             $class     Class whose factory we want
     *
     * @return string
     */
    protected function getFactoryFromContainer(ContainerInterface $container, $class)
    {
        $factories = $this->getAllFactoriesFromContainer($container);
        return $factories[$class] ?? null;
    }

    /**
     * Get a list of delegators in the provided container.
     *
     * @param ContainerInterface $container Container to inspect
     *
     * @return array
     */
    protected function getAllDelegatorsFromContainer(ContainerInterface $container)
    {
        // There is no "getDelegators" method, so we need to use reflection:
        $reflectionProperty = new \ReflectionProperty($container, 'delegators');
        $reflectionProperty->setAccessible(true);
        return $reflectionProperty->getValue($container);
    }

    /**
     * Get delegators from the provided container (or empty array if undefined).
     *
     * @param ContainerInterface $container Container to inspect
     * @param string             $class     Class whose delegators we want
     *
     * @return array
     */
    protected function getDelegatorsFromContainer(ContainerInterface $container,
        $class
    ) {
        $delegators = $this->getAllDelegatorsFromContainer($container);
        return $delegators[$class] ?? [];
    }

    /**
     * Search all plugin managers for one containing the requested class (or return
     * null if none found).
     *
     * @param ContainerInterface $container Service manager
     * @param string             $class     Class to search for
     *
     * @return ContainerInterface
     */
    protected function getPluginManagerContainingClass(ContainerInterface $container,
        $class
    ) {
        $factories = $this->getAllFactoriesFromContainer($container);
        foreach (array_keys($factories) as $service) {
            if (substr($service, -13) == 'PluginManager') {
                $pm = $container->get($service);
                if (null !== $this->getFactoryFromContainer($pm, $class)) {
                    return $pm;
                }
            }
        }
        return null;
    }

    /**
     * Extend a service defined in module.config.php.
     *
     * @param string $source Configuration path to use as source
     * @param string $target Target module in which to create new service
     *
     * @return bool
     * @throws \Exception
     */
    public function extendService($source, $target)
    {
        $parts = explode('/', $source);
        $partCount = count($parts);
        if ($partCount < 3) {
            throw new \Exception('Config path too short.');
        }
        $sourceType = $parts[$partCount - 2];

        $supportedTypes = ['factories', 'invokables'];
        if (!in_array($sourceType, $supportedTypes)) {
            throw new \Exception(
                'Unsupported service type; supported values: '
                . implode(', ', $supportedTypes)
            );
        }

        $config = $this->retrieveConfig($parts);
        if (!$config) {
            throw new \Exception("{$source} not found in configuration.");
        }

        switch ($sourceType) {
        case 'factories':
            $this->createSubclassInModule($parts[$partCount - 1], $target);
            $newConfig = $this->cloneFactory($config, $target);
            break;
        case 'invokables':
            $newConfig = $this->createSubclassInModule($config, $target);
            break;
        default:
            throw new \Exception('Reached unreachable code!');
        }
        $this->writeNewConfig($parts, $newConfig, $target);
        return true;
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
        // If the factory is a stand-alone class, it's simple to clone:
        if (class_exists($factory)) {
            return $this->createSubclassInModule($factory, $module);
        }

        // Make sure we can figure out how to handle the factory; it should
        // either be a [controller, method] array or a "controller::method"
        // string; anything else will cause a problem.
        $parts = is_string($factory) ? explode('::', $factory) : $factory;
        if (!is_array($parts) || count($parts) != 2 || !class_exists($parts[0])
            || !is_callable($parts)
        ) {
            throw new \Exception('Unexpected factory configuration format.');
        }
        list($factoryClass, $factoryMethod) = $parts;
        $newFactoryClass = $this->generateLocalClassName($factoryClass, $module);
        if (!class_exists($newFactoryClass)) {
            $this->createSubclassInModule($factoryClass, $module);
            $skipBackup = true;
        } else {
            $skipBackup = false;
        }

        $oldReflection = new ClassReflection($factoryClass);
        $newReflection = new ClassReflection($newFactoryClass);

        try {
            $newMethod = $newReflection->getMethod($factoryMethod);
            if ($newMethod->getDeclaringClass()->getName() == $newFactoryClass) {
                throw new \Exception(
                    "$newFactoryClass::$factoryMethod already exists."
                );
            }

            $generator = ClassGenerator::fromReflection($newReflection);
            $method = MethodGenerator::fromReflection(
                $oldReflection->getMethod($factoryMethod)
            );
            $this->updateFactory(
                $method, $oldReflection->getNamespaceName(), $module
            );
            $generator->addMethodFromGenerator($method);
            $this->writeClass($generator, $module, true, $skipBackup);
        } catch (\ReflectionException $e) {
            // If a parent factory has a __callStatic method, the method we are
            // trying to rewrite may not exist. In that case, we can just inherit
            // __callStatic and ignore the error. Any other exception should be
            // treated as a fatal error.
            if (method_exists($factoryClass, '__callStatic')) {
                Console::writeLine('Error: ' . $e->getMessage());
                Console::writeLine(
                    '__callStatic in parent factory; skipping method generation.'
                );
            } else {
                throw $e;
            }
        }

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
    protected function updateFactory(MethodGenerator $method,
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
        $newClass = $this->generateLocalClassName($fqClassName, $module);
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
     * @param string    $class      Name of class to create
     * @param string    $module     Module in which to create the new class
     * @param string    $parent     Parent class (null for no parent)
     * @param string[]  $interfaces Interfaces for class to implement
     * @param \Callable $callback   Callback to set up class generator
     *
     * @return void
     * @throws \Exception
     */
    protected function createClassInModule($class, $module, $parent = null,
        array $interfaces = [], $callback = null
    ) {
        $generator = new ClassGenerator($class, null, null, $parent, $interfaces);
        if (is_callable($callback)) {
            $callback($generator);
        }
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
        // TODO: this is a workaround for an apparent bug in Zend\Code which
        // omits the leading backslash on "extends" statements when rewriting
        // existing classes. Can we remove this after a future Zend\Code upgrade?
        $code = str_replace(
            'extends VuFind\\', 'extends \\VuFind\\', $generator->generate()
        );
        if (!file_put_contents($fullPath, $code)) {
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
    public function backUpFile($filename)
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
    public function getModuleConfigPath($module)
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
    public function writeModuleConfig($configPath, $config)
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
     * @param bool   $backup  Should we back up the existing config?
     *
     * @return void
     * @throws \Exception
     */
    protected function writeNewConfig($path, $setting, $module, $backup  = true)
    {
        // Create backup of configuration
        $configPath = $this->getModuleConfigPath($module);
        if ($backup) {
            $this->backUpFile($configPath);
        }

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
        $config = $this->config;
        foreach ($path as $part) {
            if (!isset($config[$part])) {
                return false;
            }
            $config = $config[$part];
        }
        return $config;
    }
}
