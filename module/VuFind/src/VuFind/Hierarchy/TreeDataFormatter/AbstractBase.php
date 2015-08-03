<?php
/**
 * Hierarchy Tree Data Formatter (abstract base)
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2015.
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
 * @package  HierarchyTree_DataFormatter
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:hierarchy_components Wiki
 */
namespace VuFind\Hierarchy\TreeDataFormatter;

/**
 * Hierarchy Tree Data Formatter (abstract base)
 *
 * @category VuFind2
 * @package  HierarchyTree_DataFormatter
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:hierarchy_components Wiki
 */
abstract class AbstractBase
{
    /**
     * Top-level record from index
     *
     * @var object
     */
    protected $topNode;

    /**
     * Child data map from index
     *
     * @var array
     */
    protected $childMap;

    /**
     * Is sorting enabled?
     *
     * @var bool
     */
    protected $sort;

    /**
     * Collection mode
     *
     * @var string
     */
    protected $collectionType;

    /**
     * How many nodes have we formatted?
     *
     * @var int;
     */
    protected $count = 0;

    /**
     * Set raw data.
     *
     * @param object $topNode  Full record for top node
     * @param array  $childMap Data map from index
     * @param bool   $sort     Is sorting enabled?
     * @param string $cType    Collection type
     *
     * @return void
     */
    public function setRawData($topNode, $childMap, $sort = false, $cType = 'All')
    {
        $this->topNode = $topNode;
        $this->childMap = $childMap;
        $this->sort = $sort;
        $this->collectionType = $cType;
    }

    /**
     * Get number of nodes formatted.
     *
     * @return int;
     */
    public function getCount()
    {
        return $this->count;
    }

    /**
     * Get the formatted metadata.
     *
     * @return string
     */
    abstract public function getData();

    /**
     * Get the positions of this item within parent collections. Returns an array
     * of parent ID => sequence number.
     *
     * @param object $fields Solr fields
     *
     * @return array
     */
    protected function getHierarchyPositionsInParents($fields)
    {
        $retVal = [];
        if (isset($fields->hierarchy_parent_id)
            && isset($fields->hierarchy_sequence)
        ) {
            foreach ($fields->hierarchy_parent_id as $key => $val) {
                $retVal[$val] = $fields->hierarchy_sequence[$key];
            }
        }
        return $retVal;
    }

     /**
     * Get the titles of this item within parent collections. Returns an array
     * of parent ID => sequence number.
     *
     * @param object $fields Solr fields
     *
     * @return Array
     */
    protected function getTitlesInHierarchy($fields)
    {
        $retVal = [];
        if (isset($fields->title_in_hierarchy)
            && is_array($fields->title_in_hierarchy)
        ) {
            $titles = $fields->title_in_hierarchy;
            $parentIDs = $fields->hierarchy_parent_id;
            if (count($titles) === count($parentIDs)) {
                foreach ($parentIDs as $key => $val) {
                    $retVal[$val] = $titles[$key];
                }
            }
        }
        return $retVal;
    }

    /**
     * Identify whether the provided record is a collection.
     *
     * NOTE: \VuFind\RecordDriver\SolrDefault::isCollection() duplicates some of\
     * this logic.
     *
     * @param object $fields Solr fields
     *
     * @return bool
     */
    protected function isCollection($fields)
    {
        // Check config setting for what constitutes a collection
        switch ($this->collectionType) {
        case 'All':
            return (isset($fields->is_hierarchy_id));
        case 'Top':
            return isset($fields->is_hierarchy_id)
                && in_array($fields->is_hierarchy_id, $fields->hierarchy_top_id);
        default:
            // Default to not be a collection level record
            return false;
        }
    }

    /**
     * Choose a title for the record.
     *
     * @param object $record   Solr record to format
     * @param string $parentID The starting point for the current recursion
     * (equivalent to Solr field hierarchy_parent_id)
     *
     * @return string
     */
    protected function pickTitle($record, $parentID)
    {
        $titles = $this->getTitlesInHierarchy($record);
        // TODO: handle missing titles more gracefully (title not available?)
        $title = isset($record->title) ? $record->title : $record->id;
        return null != $parentID && isset($titles[$parentID])
            ? $titles[$parentID] : $title;
    }

    /**
     * Sort Nodes
     * Convert an unsorted array of [ key, value ] pairs into a sorted array
     * of values.
     *
     * @param array $array The array of arrays to sort
     *
     * @return array
     */
    protected function sortNodes($array)
    {
        // Sort arrays based on first element
        $sorter = function ($a, $b) {
            return strcmp($a[0], $b[0]);
        };
        usort($array, $sorter);

        // Collapse array to remove sort values
        $mapper = function ($i) {
            return $i[1];
        };
        return array_map($mapper, $array);
    }
}
