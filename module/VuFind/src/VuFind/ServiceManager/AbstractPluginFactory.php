<?php

/**
 * VuFind Abstract Plugin Factory
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
use Laminas\ServiceManager\Factory\InvokableFactory;
use Psr\Container\ContainerInterface;
use VuFind\ServiceManager\Factory\AutowiringFactory;

/**
 * VuFind Abstract Plugin Factory
 *
 * @category VuFind
 * @package  ServiceManager
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
abstract class AbstractPluginFactory implements AbstractFactoryInterface
{
    use Factory\AutowireableTrait;

    /**
     * Default namespace for building class names (null to use class names as-is)
     *
     * @var ?string
     */
    protected $defaultNamespace = null;

    /**
     * Optional suffix to append to class names (ignored when defaultNamespace is null)
     *
     * @var string
     */
    protected $classSuffix = '';

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
     * Given a class name, find the best matching factory.
     *
     * @param string $class Class name
     *
     * @return string
     */
    protected function getFactoryForClass(string $class): string
    {
        if (class_exists($class . 'Factory')) {
            return $class . 'Factory';
        }
        $parentClass = get_parent_class($class);
        while ($parentClass) {
            if (class_exists($parentClass . 'Factory')) {
                return $parentClass . 'Factory';
            }
            $parentClass = get_parent_class($parentClass);
        }
        return $this->isAutowireable($class) ? AutowiringFactory::class : InvokableFactory::class;
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
        return class_exists($this->getClassName($requestedName));
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
        $factory = new $factoryName();
        return $factory($container, $class, $options);
    }
}
