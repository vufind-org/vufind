<?php

/**
 * Hierarchy Tree Data Formatter (JSON)
 *
 * PHP version 8
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  HierarchyTree_DataFormatter
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 */

namespace VuFind\Hierarchy\TreeDataFormatter;

/**
 * Hierarchy Tree Data Formatter (JSON)
 *
 * @category VuFind
 * @package  HierarchyTree_DataFormatter
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 */
class Json extends AbstractBase
{
    /**
     * Get the formatted metadata.
     *
     * @return string
     */
    public function getData()
    {
        return json_encode($this->formatNode($this->topNode));
    }

    /**
     * Get Solr Children for JSON
     *
     * @param object $record   Solr record to format
     * @param string $parentID The starting point for the current recursion
     * (equivalent to Solr field hierarchy_parent_id)
     *
     * @return string
     */
    protected function formatNode($record, $parentID = null)
    {
        $raw = [
            'id' => $record->id,
            'type' => $this->isCollection($record) ? 'collection' : 'record',
            'title' => $this->pickTitle($record, $parentID),
        ];

        if (isset($this->childMap[$record->id])) {
            $children = $this->mapChildren($record->id);
            if (!empty($children)) {
                $raw['children'] = $children;
            }
        }

        return (object)$raw;
    }

    /**
     * Get Solr Children for JSON
     *
     * @param string $parentID The starting point for the current recursion
     * (equivalent to Solr field hierarchy_parent_id)
     *
     * @return string
     */
    protected function mapChildren($parentID)
    {
        $json = [];
        foreach ($this->childMap[$parentID] as $current) {
            ++$this->count;

            $childNode = $this->formatNode($current, $parentID);

            // If we're in sorting mode, we need to create key-value arrays;
            // otherwise, we can just collect flat values.
            if ($this->sort) {
                $positions = $this->getHierarchyPositionsInParents($current);
                $sequence = $positions[$parentID] ?? 0;
                $json[] = [$sequence, $childNode];
            } else {
                $json[] = $childNode;
            }
        }

        return $this->sort ? $this->sortNodes($json) : $json;
    }
}
