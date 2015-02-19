<?php
/**
 * Record route generator
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
 * @package  Record
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace VuFind\Record;

/**
 * Record route generator
 *
 * @category VuFind2
 * @package  Record
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class Router
{
    /**
     * Record loader
     *
     * @var \VuFind\Record\Loader
     */
    protected $loader;

    /**
     * VuFind configuration
     *
     * @var \Zend\Config\Config
     */
    protected $config;

    /**
     * Constructor
     *
     * @param \VuFind\Record\Loader $loader Record loader
     * @param \Zend\Config\Config   $config VuFind configuration
     */
    public function __construct(\VuFind\Record\Loader $loader,
        \Zend\Config\Config $config
    ) {
        $this->loader = $loader;
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
     *
     * @return array
     */
    public function getTabRouteDetails($driver, $tab = null)
    {
        $route = $this->getRouteDetails(
            $driver, '', empty($tab) ? [] : ['tab' => $tab]
        );

        // If collections are active and the record route was selected, we need
        // to check if the driver is actually a collection; if so, we should switch
        // routes.
        if ('record' == $route['route']) {
            if (isset($this->config->Collections->collections)
                && $this->config->Collections->collections
            ) {
                if (!is_object($driver)) {
                    list($source, $id) = $this->extractSourceAndId($driver);
                    $driver = $this->loader->load($id, $source);
                }
                if (true === $driver->tryMethod('isCollection')) {
                    $route['route'] = 'collection';
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
    public function getRouteDetails($driver, $routeSuffix = '',
        $extraParams = []
    ) {
        // Extract source and ID from driver or string:
        if (is_object($driver)) {
            $source = $driver->getResourceSource();
            $id = $driver->getUniqueId();
        } else {
            list($source, $id) = $this->extractSourceAndId($driver);
        }

        // Build URL parameters:
        $params = $extraParams;
        $params['id'] = $id;

        // Determine route based on naming convention (default VuFind route is
        // the exception to the rule):
        $routeBase = ($source == 'VuFind')
            ? 'record' : strtolower($source . 'record');

        return [
            'params' => $params, 'route' => $routeBase . $routeSuffix
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
            $source = 'VuFind';
            $id = $parts[0];
        } else {
            $source = $parts[0];
            $id = $parts[1];
        }
        return [$source, $id];
    }
}
