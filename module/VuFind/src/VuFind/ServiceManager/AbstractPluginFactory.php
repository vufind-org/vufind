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
     * Factory detector.
     *
     * @var FactoryDetector
     */
    protected ?FactoryDetector $factoryDetector = null;

    /**
     * Get factory detector.
     *
     * @return FactoryDetector
     */
    protected function getFactoryDetector(): FactoryDetector
    {
        $this->factoryDetector ??= new FactoryDetector();
        return $this->factoryDetector;
    }

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
     * Given a class name, find the best matching factory. Return null if none can be found.
     *
     * @param string $class Class name
     *
     * @return ?string
     */
    protected function getFactoryForClass(string $class): ?string
    {
        if (!isset($this->factoryForClass[$class])) {
            $this->factoryForClass[$class] = $this->getFactoryDetector()->detectFactoryForClass($class)
                ?? $this->defaultFactory;
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
