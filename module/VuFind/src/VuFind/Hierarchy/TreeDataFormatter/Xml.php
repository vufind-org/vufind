<?php
/**
 * Hierarchy Tree Data Formatter (XML)
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
 * Hierarchy Tree Data Formatter (XML)
 *
 * @category VuFind2
 * @package  HierarchyTree_DataFormatter
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:hierarchy_components Wiki
 */
class Xml extends AbstractBase
{
    /**
     * Get the formatted metadata.
     *
     * @return string
     */
    public function getData()
    {
        return '<root>'
            . $this->formatNode($this->topNode)
            . '</root>';
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
        $isCollection = $this->isCollection($record) ? "true" : "false";
        return '<item id="' . htmlspecialchars($record->id)
            . '" isCollection="' . $isCollection . '">'
            . '<content><name>'
            . htmlspecialchars($this->pickTitle($record, $parentID))
            . '</name></content>'
            . $this->mapChildren($record->id)
            . '</item>';
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
        if (!isset($this->childMap[$parentID])) {
            return '';
        }
        $parts = [];
        foreach ($this->childMap[$parentID] as $current) {
            ++$this->count;

            $childNode = $this->formatNode($current, $parentID);

            // If we're in sorting mode, we need to create key-value arrays;
            // otherwise, we can just collect flat values.
            if ($this->sort) {
                $positions = $this->getHierarchyPositionsInParents($current);
                $sequence = isset($positions[$parentID]) ? $positions[$parentID] : 0;
                $parts[] = [$sequence, $childNode];
            } else {
                $parts[] = $childNode;
            }
        }

        return implode('', $this->sort ? $this->sortNodes($parts) : $parts);
    }
}
