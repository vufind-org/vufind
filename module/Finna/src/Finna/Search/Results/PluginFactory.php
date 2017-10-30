<?php
/**
 * Search results plugin factory
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2015-2017.
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
 * @link     https://vufind.org Main Page
 */
namespace Finna\Search\Results;

use Zend\ServiceManager\ServiceLocatorInterface;

/**
 * Search results plugin factory
 *
 * @category VuFind
 * @package  Search
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class PluginFactory extends \VuFind\Search\Results\PluginFactory
{
    /**
     * Create a service for the specified name.
     *
     * @param ServiceLocatorInterface $serviceLocator Service locator
     * @param string                  $name           Name of service
     * @param string                  $requestedName  Unfiltered name of service
     * @param array                   $extraParams    Extra constructor parameters
     * (to follow the Params object)
     *
     * @return object
     */
    public function createServiceWithName(ServiceLocatorInterface $serviceLocator,
        $name, $requestedName, array $extraParams = []
    ) {
        $params = $serviceLocator->getServiceLocator()
            ->get('VuFind\SearchParamsPluginManager')->get($requestedName);
        $searchService = $serviceLocator->getServiceLocator()
            ->get('VuFind\Search');
        $recordLoader = $serviceLocator->getServiceLocator()
            ->get('VuFind\RecordLoader');

        $class = '\Finna\Search\\' . $requestedName . $this->classSuffix;
        if (!class_exists($class)) {
            $class = $this->getClassName($name, $requestedName);
        }
        return new $class($params, $searchService, $recordLoader, ...$extraParams);
    }
}
