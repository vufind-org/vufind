<?php
/**
 * Record loader
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
 * @package  SearchObject
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace VuFind;

/**
 * Record loader
 *
 * @category VuFind2
 * @package  SearchObject
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class Record
{
    /**
     * Given a record source, return the search class that can load that type of
     * record.
     *
     * @param string $source Record source
     *
     * @throws Exception
     * @return string
     */
    protected static function getClassForSource($source)
    {
        // Special case -- the VuFind record source actually maps to Solr classes;
        // this is a legacy issue related to values inserted into the database by
        // VuFind 1.x:
        if ($source == 'VuFind') {
            $source = 'Solr';
        }

        // Use the appropriate Search class to load the requested record:
        $class = 'VuFind\\Search\\' . $source . '\\Results';

        // Throw an error if we can't find a loader class:
        if (!class_exists($class)) {
            throw new \Exception('Unrecognized data source: ' . $source);
        }

        return $class;
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
        return static::getRouteDetails(
            $driver, '', empty($tab) ? array() : array('tab' => $tab)
        );
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

    /**
     * Given an ID and record source, load the requested record object.
     *
     * @param string $id     Record ID
     * @param string $source Record source
     *
     * @throws \Exception
     * @return \VuFind\RecordDriver\AbstractBase
     */
    public static function load($id, $source = 'VuFind')
    {
        // Load the record:
        $class = self::getClassForSource($source);
        return call_user_func(array($class, 'getRecord'), $id);
    }

    /**
     * Given an array of associative arrays with id and source keys (or pipe-
     * separated source|id strings), load all of the requested records in the
     * requested order.
     *
     * @param array $ids Array of associative arrays with id/source keys or
     * strings in source|id format.  In associative array formats, there is
     * also an optional "extra_fields" key which can be used to pass in data
     * formatted as if it belongs to the Solr schema; this is used to create
     * a mock driver object if the real data source is unavailable.
     *
     * @throws Exception
     * @return array     Array of record drivers
     */
    public static function loadBatch($ids)
    {
        // Sort the IDs by source -- we'll create an associative array indexed by
        // source and record ID which points to the desired position of the indexed
        // record in the final return array:
        $idBySource = array();
        foreach ($ids as $i => $details) {
            // Convert source|id string to array if necessary:
            if (!is_array($details)) {
                $parts = explode('|', $details, 2);
                $ids[$i] = $details = array(
                    'source' => $parts[0], 'id' => $parts[1]
                );
            }
            $idBySource[$details['source']][$details['id']] = $i;
        }

        // Retrieve the records and put them back in order:
        $retVal = array();
        foreach ($idBySource as $source => $details) {
            $class = self::getClassForSource($source);
            $records
                = call_user_func(array($class, 'getRecords'), array_keys($details));
            foreach ($records as $current) {
                $id = $current->getUniqueId();
                $retVal[$details[$id]] = $current;
            }
        }

        // Check for missing records and fill gaps with VF_RecordDriver_Missing
        // objects:
        foreach ($ids as $i => $details) {
            if (!isset($retVal[$i]) || !is_object($retVal[$i])) {
                $fields = isset($details['extra_fields'])
                    ? $details['extra_fields'] : array();
                $fields['id'] = $details['id'];
                $retVal[$i]
                    = new \VuFind\RecordDriver\Missing($fields);
            }
        }

        // Send back the final array, with the keys in proper order:
        ksort($retVal);
        return $retVal;
    }
}
