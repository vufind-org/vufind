<?php

/**
 * Record route generator
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Record
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Record;

use function count;
use function is_object;

/**
 * Record route generator
 *
 * @category VuFind
 * @package  Record
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class Router
{
    /**
     * VuFind configuration
     *
     * @var \Laminas\Config\Config
     */
    protected $config;

    /**
     * Constructor
     *
     * @param \Laminas\Config\Config $config VuFind configuration
     */
    public function __construct(\Laminas\Config\Config $config)
    {
        $this->config = $config;
    }

    /**
     * Get routing details for a controller action.
     *
     * @param \VuFind\RecordDriver\AbstractBase|string $driver Record driver
     * representing record to link to, or source|id pipe-delimited string
     * @param string                                   $action Action to access
     *
     * @return array
     */
    public function getActionRouteDetails($driver, $action)
    {
        return $this->getRouteDetails($driver, '-' . strtolower($action));
    }

    /**
     * Get routing details to display a particular tab.
     *
     * @param \VuFind\RecordDriver\AbstractBase|string $driver Record driver
     * representing record to link to, or source|id pipe-delimited string
     * @param string                                   $tab    Action to access
     * @param array                                    $query  Optional query params
     *
     * @return array
     */
    public function getTabRouteDetails($driver, $tab = null, $query = [])
    {
        $route = $this->getRouteDetails(
            $driver,
            '',
            empty($tab) ? [] : ['tab' => $tab]
        );
        // Add the options and query elements only if we need a query to avoid
        // an empty element in the route definition:
        if ($query) {
            $route['options']['query'] = $query;
        }

        // If collections are active and the record route was selected, we need
        // to check if the driver is actually a collection; if so, we should switch
        // routes.
        if ($this->config->Collections->collections ?? false) {
            $routeConfig = isset($this->config->Collections->route)
                ? $this->config->Collections->route->toArray() : [];
            $collectionRoutes
                = array_merge(
                    ['record' => 'collection',
                     'search2record' => 'search2collection'],
                    $routeConfig
                );
            $routeName = $route['route'];
            if ($collectionRoute = ($collectionRoutes[$routeName] ?? null)) {
                if (!is_object($driver)) {
                    // Avoid loading the driver. Set a flag so that if the link is
                    // used, record controller will check for redirection.
                    $route['options']['query']['checkRoute'] = 1;
                } elseif (true === $driver->tryMethod('isCollection')) {
                    $route['route'] = $collectionRoute;
                }
            }
        }
        return $route;
    }

    /**
     * Get routing details (route name and parameters array) to link to a record.
     *
     * @param \VuFind\RecordDriver\AbstractBase|string $driver      Record driver
     * representing record to link to, or source|id pipe-delimited string
     * @param string                                   $routeSuffix Suffix to add
     * to route name
     * @param array                                    $extraParams Extra parameters
     * for route
     *
     * @return array
     */
    public function getRouteDetails(
        $driver,
        $routeSuffix = '',
        $extraParams = []
    ) {
        // Extract source and ID from driver or string:
        if (is_object($driver)) {
            $source = $driver->getSourceIdentifier();
            $id = $driver->getUniqueId();
        } else {
            [$source, $id] = $this->extractSourceAndId($driver);
        }

        // Build URL parameters:
        $params = $extraParams;
        $params['id'] = $id;

        // Determine route based on naming convention (default VuFind route is
        // the exception to the rule):
        $routeBase = ($source == DEFAULT_SEARCH_BACKEND)
            ? 'record' : strtolower($source . 'record');

        // Disable path normalization since it can unencode e.g. encoded slashes in
        // record id's
        $options = [
            'normalize_path' => false,
        ];

        return [
            'params' => $params,
            'route' => $routeBase . $routeSuffix,
            'options' => $options,
        ];
    }

    /**
     * Extract source and ID from a pipe-delimited string, adding a default
     * source if appropriate.
     *
     * @param string $driver source|ID string
     *
     * @return array
     */
    protected function extractSourceAndId($driver)
    {
        $parts = explode('|', $driver, 2);
        if (count($parts) < 2) {
            $source = DEFAULT_SEARCH_BACKEND;
            $id = $parts[0];
        } else {
            $source = $parts[0];
            $id = $parts[1];
        }
        return [$source, $id];
    }
}
