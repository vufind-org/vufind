<?php

/**
 * VuFind Abstract Plugin Factory.
 *
 * PHP version 8
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  ServiceManager
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\ServiceManager;

use Laminas\ServiceManager\Factory\AbstractFactoryInterface;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use VuFind\ServiceManager\Factory\AutowiringFactory;
use VuFind\ServiceManager\Factory\DefaultFactory;

/**
 * VuFind Abstract Plugin Factory.
 *
 * @category VuFind
 * @package  ServiceManager
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class AbstractPluginFactory implements AbstractFactoryInterface
{
    use Factory\AutowireableTrait;

    /**
     * Default namespace for building class names (null to use class names as-is).
     *
     * @var ?string
     */
    protected $defaultNamespace = null;

    /**
     * Optional suffix to append to class names (ignored when defaultNamespace is null).
     *
     * @var string
     */
    protected $classSuffix = '';

    /**
     * Lookup table of factories for classes.
     *
     * @var array
     */
    protected $factoryForClass = [];

    /**
     * Default factory to use when autodetection fails (null for none).
     *
     * @var ?string
     */
    protected ?string $defaultFactory = null;

    /**
     * Get the name of a class for a given plugin name.
     *
     * @param string $requestedName Name of service
     *
     * @return string               Fully qualified class name
     */
    protected function getClassName($requestedName)
    {
        // If class name generation is disabled or we have a FQCN that refers to an existing class, return it as-is:
        if (null === $this->defaultNamespace || (str_contains($requestedName, '\\') && class_exists($requestedName))) {
            return $requestedName;
        }
        // First try the raw service name, then try a normalized version:
        $finalName = $this->defaultNamespace . '\\' . $requestedName . $this->classSuffix;
        if (!class_exists($finalName)) {
            $finalName = $this->defaultNamespace . '\\' . ucwords(strtolower($requestedName)) . $this->classSuffix;
        }
        return $finalName;
    }

    /**
     * Given the name of a class, check if it has default factory behavior assigned; return an array
     * containing ?string $name and bool $autodetect, as per the DefaultFactory attribute.
     *
     * @param string $class Class name
     *
     * @return array
     */
    protected function getdefaultFactorySettings(string $class): array
    {
        $reflectionClass = new ReflectionClass($class);
        $matches = $reflectionClass->getAttributes(DefaultFactory::class);
        return isset($matches[0]) ? $matches[0]->getArguments() : ['name' => null, 'autodetect' => true];
    }

    /**
     * Given a class name, detect the best matching factory. Return null if none can be found.
     * This is a support method for getFactoryForClass and should not be called directly; use
     * getFactoryForClass to take advantage of internal caching.
     *
     * @param string $class Class name
     *
     * @return ?string
     */
    protected function detectFactoryForClass(string $class): ?string
    {
        $defaultFactorySettings = $this->getdefaultFactorySettings($class);
        if ($defaultFactorySettings['name']) {
            return $defaultFactorySettings['name'];
        }
        // If the class has an explicit factory, use that:
        if ($defaultFactorySettings['autodetect'] && class_exists($class . 'Factory')) {
            return $class . 'Factory';
        }
        // If the class is autowireable, take advantage of that:
        if ($this->isAutowireable($class)) {
            return AutowiringFactory::class;
        }
        // Check if parent classes have factories:
        $parentClass = get_parent_class($class);
        while ($defaultFactorySettings['autodetect'] && $parentClass) {
            if (class_exists($parentClass . 'Factory')) {
                return $parentClass . 'Factory';
            }
            $parentClass = get_parent_class($parentClass);
        }
        // If we got this far, we'll fall back on the default factory for lack of a better option:
        return $this->defaultFactory;
    }

    /**
     * Given a class name, find the best matching factory. Return null if none can be found.
     *
     * @param string $class Class name
     *
     * @return ?string
     */
    protected function getFactoryForClass(string $class): ?string
    {
        if (!isset($this->factoryForClass[$class])) {
            $this->factoryForClass[$class] = $this->detectFactoryForClass($class);
        }
        return $this->factoryForClass[$class];
    }

    /**
     * Can we create a service for the specified name?
     *
     * @param ContainerInterface $container     Service container
     * @param string             $requestedName Name of service
     *
     * @return bool
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function canCreate(ContainerInterface $container, $requestedName)
    {
        $className = $this->getClassName($requestedName);
        return class_exists($className) && null !== $this->getFactoryForClass($className);
    }

    /**
     * Create a service for the specified name.
     *
     * @param ContainerInterface $container     Service container
     * @param string             $requestedName Name of service
     * @param array              $options       Options (unused)
     *
     * @return object
     */
    public function __invoke(
        ContainerInterface $container,
        $requestedName,
        ?array $options = null
    ) {
        $class = $this->getClassName($requestedName);
        $factoryName = $this->getFactoryForClass($class);
        if (!$factoryName) {
            throw new \Exception("Cannot determine factory for $class");
        }
        $factory = new $factoryName();
        return $factory($container, $class, $options);
    }
}
