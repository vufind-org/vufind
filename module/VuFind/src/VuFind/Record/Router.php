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
     * Get routing details for a controller action.
     *
     * @param \VuFind\RecordDriver\AbstractBase|string $driver Record driver
     * representing record to link to, or source|id pipe-delimited string
     * @param string                                   $action Action to access
     *
     * @return array
     */
    public static function getActionRouteDetails($driver, $action)
    {
        return static::getRouteDetails($driver, '-' . strtolower($action));
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
    public static function getTabRouteDetails($driver, $tab = null)
    {
        $route = static::getRouteDetails(
            $driver, '', empty($tab) ? array() : array('tab' => $tab)
        );

        // If collections are active and the record route was selected, we need
        // to check if the driver is actually a collection; if so, we should switch
        // routes.
        if ('record' == $route['route']) {
            $config = \VuFind\Config\Reader::getConfig();
            if (isset($config->Collections->collections)
                && $config->Collections->collections
            ) {
                if (is_object($driver)
                    && true === $driver->tryMethod('isCollection')
                ) {
                    $route['route'] = 'collection';
                }
                // TODO: make routing work correctly in non-object $driver case
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
    public static function getRouteDetails($driver, $routeSuffix,
        $extraParams = array()
    ) {
        // Extract source and ID from driver or string:
        if (is_object($driver)) {
            $source = $driver->getResourceSource();
            $id = $driver->getUniqueId();
        } else {
            $parts = explode('|', $driver, 2);
            if (count($parts) < 2) {
                $source = 'VuFind';
                $id = $parts[0];
            } else {
                $source = $parts[0];
                $id = $parts[1];
            }
        }

        // Build URL parameters:
        $params = $extraParams;
        $params['id'] = $id;
        if (!empty($action)) {
            $params['action'] = $action;
        }

        // Determine route based on naming convention (default VuFind route is
        // the exception to the rule):
        $routeBase = ($source == 'VuFind')
            ? 'record' : strtolower($source . 'record');

        return array(
            'params' => $params, 'route' => $routeBase . $routeSuffix
        );
    }
}
