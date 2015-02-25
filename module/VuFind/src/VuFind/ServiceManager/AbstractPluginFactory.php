<?php
/**
 * VuFind Abstract Plugin Factory
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
 * @package  ServiceManager
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace VuFind\ServiceManager;
use Zend\ServiceManager\AbstractFactoryInterface,
    Zend\ServiceManager\ServiceLocatorInterface;

/**
 * VuFind Abstract Plugin Factory
 *
 * @category VuFind2
 * @package  ServiceManager
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
abstract class AbstractPluginFactory implements AbstractFactoryInterface
{
    /**
     * Default namespace for building class names
     *
     * @var string
     */
    protected $defaultNamespace;

    /**
     * Optional suffix to append to class names
     *
     * @var string
     */
    protected $classSuffix = '';

    /**
     * Get the name of a class for a given plugin name.
     *
     * @param string $name          Name of service
     * @param string $requestedName Unfiltered name of service
     *
     * @return string               Fully qualified class name
     */
    protected function getClassName($name, $requestedName)
    {
        // If we have a FQCN that refers to an existing class, return it as-is:
        if (strpos($requestedName, '\\') !== false && class_exists($requestedName)) {
            return $requestedName;
        }
        // First try the raw service name, then try a normalized version:
        $finalName = $this->defaultNamespace . '\\' . $requestedName
            . $this->classSuffix;
        if (!class_exists($finalName)) {
            $finalName = $this->defaultNamespace . '\\' . ucwords(strtolower($name))
                . $this->classSuffix;
        }
        return $finalName;
    }

    /**
     * Can we create a service for the specified name?
     *
     * @param ServiceLocatorInterface $serviceLocator Service locator
     * @param string                  $name           Name of service
     * @param string                  $requestedName  Unfiltered name of service
     *
     * @return bool
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function canCreateServiceWithName(ServiceLocatorInterface $serviceLocator,
        $name, $requestedName
    ) {
        $className = $this->getClassName($name, $requestedName);
        return class_exists($className);
    }

    /**
     * Create a service for the specified name.
     *
     * @param ServiceLocatorInterface $serviceLocator Service locator
     * @param string                  $name           Name of service
     * @param string                  $requestedName  Unfiltered name of service
     *
     * @return object
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function createServiceWithName(ServiceLocatorInterface $serviceLocator,
        $name, $requestedName
    ) {
        $class = $this->getClassName($name, $requestedName);
        return new $class();
    }
}
