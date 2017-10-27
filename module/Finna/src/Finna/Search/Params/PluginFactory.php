<?php
/**
 * Search params plugin factory
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2015.
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
 * @package  Search
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace Finna\Search\Params;

use Zend\ServiceManager\ServiceLocatorInterface;

/**
 * Search params plugin factory
 *
 * @category VuFind
 * @package  Search
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class PluginFactory extends \VuFind\Search\Params\PluginFactory
{
    /**
     * Create a service for the specified name.
     *
     * @param ServiceLocatorInterface $serviceLocator Service locator
     * @param string                  $name           Name of service
     * @param string                  $requestedName  Unfiltered name of service
     * @param array                   $extraParams    Extra constructor parameters
     * (to follow the Options object and config loader)
     *
     * @return object
     */
    public function createServiceWithName(ServiceLocatorInterface $serviceLocator,
        $name, $requestedName, array $extraParams = []
    ) {
        $options = $serviceLocator->getServiceLocator()
            ->get('VuFind\SearchOptionsPluginManager')->get($requestedName);

        $class = '\Finna\Search\\' . $requestedName . $this->classSuffix;
        if (!class_exists($class)) {
            $class = $this->getClassName($name, $requestedName);
        }

        $configLoader = $serviceLocator->getServiceLocator()->get('VuFind\Config');
        // Clone the options instance in case caller modifies it:
        return new $class(clone $options, $configLoader, ...$extraParams);
    }
}
