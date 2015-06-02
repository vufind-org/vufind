<?php
/**
 * Hierarchy Tree Data Formatter (abstract base)
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
 * @package  HierarchyTree_DataSource
 * @author   Luke O'Sullivan <l.osullivan@swansea.ac.uk>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:hierarchy_components Wiki
 */
namespace VuFind\Hierarchy\TreeDataFormatter;

/**
 * Hierarchy Tree Data Formatter (abstract base)
 *
 * @category VuFind2
 * @package  HierarchyTree_DataSource
 * @author   Luke O'Sullivan <l.osullivan@swansea.ac.uk>
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
     * How many nodes have we formatted?
     *
     * @var int;
     */
    protected $count = 0;

    /**
     * Constructor
     *
     * @param object $topNode  Full record for top node
     * @param array  $childMap Data map from index
     * @param bool   $sort     Is sorting enabled?
     */
    public function  __construct($topNode, $childMap, $sort = false)
    {
        $this->topNode = $topNode;
        $this->childMap = $childMap;
        $this->sort = $sort;
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
     * Get the extension to use for caching the metadata.
     *
     * @return string
     */
    abstract public static function getCacheExtension();

    /**
     * Get the formatted metadata.
     *
     * @return string
     */
    abstract public function getData();

    /**
     * Get the positions of this item within parent collections.  Returns an array
     * of parent ID => sequence number.
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
     * Get the titles of this item within parent collections.  Returns an array
     * of parent ID => sequence number.
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