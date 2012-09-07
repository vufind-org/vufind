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
 * @package  Record
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace VuFind\Record;

/**
 * Record loader
 *
 * @category VuFind2
 * @package  Record
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class Loader
{
    /**
     * Given a record source, return the search class that can load that type of
     * record.
     *
     * @param string $source Record source
     *
     * @throws \Exception
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
        $class = 'VuFind\Search\\' . $source . '\Results';

        // Throw an error if we can't find a loader class:
        if (!class_exists($class)) {
            throw new \Exception('Unrecognized data source: ' . $source);
        }

        return $class;
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
     * @throws \Exception
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

        // Check for missing records and fill gaps with \VuFind\RecordDriver\Missing
        // objects:
        foreach ($ids as $i => $details) {
            if (!isset($retVal[$i]) || !is_object($retVal[$i])) {
                $fields = isset($details['extra_fields'])
                    ? $details['extra_fields'] : array();
                $fields['id'] = $details['id'];
                $retVal[$i] = new \VuFind\RecordDriver\Missing();
                $retVal[$i]->setRawData($fields);
            }
        }

        // Send back the final array, with the keys in proper order:
        ksort($retVal);
        return $retVal;
    }
}
