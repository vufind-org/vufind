<?php
/**
 * Facet Helper
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2014.
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
 * @package  Search
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace VuFind\Search\Solr;

use Zend\Mvc\Controller\AbstractActionController;

/**
 * Functions for manipulating facets
 *
 * @category VuFind2
 * @package  Search
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class HierarchicalFacetHelper
{
    /**
     * Helper method for building hierarchical facets:
     * Sort a facet list according to the given sort order
     *
     * @param array &$facetList Facet list returned from Solr
     * @param bool  $topLevel   Whether to sort only top level
     *
     * @return void
     */
    public function sortFacetList(&$facetList, $topLevel)
    {
        // Parse level from each facet value so that the sort function
        // can run faster
        foreach ($facetList as &$facetItem) {
            list($facetItem['level']) = explode('/', $facetItem['value'], 2);
        }
        // Avoid problems having the reference set further below
        unset($facetItem);
        $sortFunc = null;
        if ($topLevel) {
            $sortFunc = function($a, $b) {
                if ($a['level'] == 0 && $b['level'] == 0) {
                    $aText = $a['displayText'] == $a['value']
                        ? $this->formatDisplayText($a['displayText'])
                        : $a['displayText'];
                    $bText = $b['displayText'] == $b['value']
                        ? $this->formatDisplayText($b['displayText'])
                        : $b['displayText'];
                    return strcasecmp($aText, $bText);
                }
                return $a['level'] == $b['level']
                    ? $b['count'] - $a['count']
                    : $b['level'] - $a['level'];
            };
        } else {
            $sortFunc = function($a, $b) {
                if ($a['level'] == $b['level']) {
                    $aText = $a['displayText'] == $a['value']
                        ? $this->formatDisplayText($a['displayText'])
                        : $a['displayText'];
                    $bText = $b['displayText'] == $b['value']
                        ? $this->formatDisplayText($b['displayText'])
                        : $b['displayText'];
                    return strcasecmp($aText, $bText);
                }
                return $b['level'] - $a['level'];
            };
        }
        uasort($facetList, $sortFunc);
    }

    /**
     * Helper method for building hierarchical facets:
     * Convert facet list to a hierarchical array
     *
     * @param string    $facet            Facet name
     * @param array     $facetList        Facet list
     * @param array     $activeFilterList Array of active filters
     * @param UrlHelper $urlHelper        Query URL helper for building facet URLs
     *
     * @return array Facet hierarchy
     */
    public function buildFacetArray(
        $facet, $facetList, $activeFilterList = array(), $urlHelper = false
    ) {
        // First build associative arrays of currently active filters and
        // their parents
        $filterKeys = array();
        $parentFilterKeys = array();
        $this->buildFilterKeyArrays(
            $facet, $activeFilterList, $filterKeys, $parentFilterKeys
        );

        // Create a keyed (for conversion to hierarchical) array of facet data
        $keyedList = array();
        $paramArray = $urlHelper !== false ? $urlHelper->getParamArray() : null;
        foreach ($facetList as $item) {
            $href = '';
            $exclude = '';
            // Build URLs only if we were given an URL helper
            if ($urlHelper !== false) {
                if (isset($filterKeys[$item['value']])) {
                    $href = $urlHelper->removeFacet(
                        $facet, $item['value'], true, $item['operator'], $paramArray
                    );
                } else {
                    $href = $urlHelper->addFacet(
                        $facet, $item['value'], $item['operator'], $paramArray
                    );
                }
                $exclude = $urlHelper->addFacet(
                    $facet, $item['value'], 'NOT', $paramArray
                );
            }

            $displayText = $item['displayText'];
            if ($displayText == $item['value']) {
                // Only show the current level part
                $displayText = $this->formatDisplayText($displayText);
            }

            $facetItem = array(
                'value' => $item['value'],
                'displayText' => $displayText,
                'count' => $item['count'],
                'state' => array(
                    'opened' => isset($parentFilterKeys[$item['value']]),
                ),
                'selected' => isset($filterKeys[$item['value']]),
                'href' => $href,
                'exclude' => $exclude,
                'operator' => $item['operator'],
                'children' => array()
            );

            $keyedList[$item['value']] = $facetItem;
        }

        // Convert the keyed array to a hierarchical array
        $result = array();
        foreach ($keyedList as $key => &$item) {
            list($level, $value) = explode('/', $key, 2);
            $item['level'] = $level;
            if ($level > 0) {
                $parentId = ($level - 1) . '/' . implode(
                    '/',
                    array_slice(
                        explode('/', $value),
                        0,
                        $level
                    )
                ) . '/';
                $keyedList[$parentId]['children'][] = &$item;
            } else {
                $result[] = &$item;
            }
        }

        return $result;
    }

    /**
     * Helper method for building hierarchical facets:
     * Create two keyed arrays of currently active filter for quick lookup:
     * - filterKeys: currently active filters
     * - parentFilterKeys: all the parents of currently active filters
     *
     * @param string $facet             Facet name
     * @param array  $filterList        Active filters
     * @param array  &$filterKeys       Resulting array of active filters
     * @param array  &$parentFilterKeys Resulting array of active filter parents
     *
     * @return void
     */
    public function buildFilterKeyArrays(
        $facet, $filterList, &$filterKeys, &$parentFilterKeys
    ) {
        foreach ($filterList as $filters) {
            foreach ($filters as $filterItem) {
                if ($filterItem['field'] == $facet) {
                    $filterKeys[$filterItem['value']] = true;
                    list($filterLevel, $filterValue)
                        = explode('/', $filterItem['value'], 2);
                    for (; $filterLevel > 0; $filterLevel--) {
                        $parentKey = ($filterLevel - 1) . '/' . implode(
                            '/',
                            array_slice(
                                explode('/', $filterValue),
                                0,
                                $filterLevel
                            )
                        ) . '/';
                        $parentFilterKeys[$parentKey] = true;
                    }
                }
            }
        }
    }

    /**
     * Flatten a hierarchical facet list to a simple array
     *
     * @param array $facetList Facet list
     *
     * @return array Simple array of facets
     */
    public function flattenFacetHierarchy($facetList)
    {
        $results = array();
        foreach ($facetList as $facetItem) {
            $results[] = $facetItem;
            if (!empty($facetItem['children'])) {
                $results = array_merge(
                    $results, $this->flattenFacetHierarchy($facetItem['children'])
                );
            }
        }
        return $results;
    }

    /**
     * Format a facet display text for displaying
     *
     * @param string $displayText Display text
     * @param bool   $allLevels   Whether to display all levels or only
     * the current one
     * @param string $separator   Separator string displayed between levels
     *
     * @return string Formatted text
     */
    public function formatDisplayText(
        $displayText, $allLevels = false, $separator = '/'
    ) {
        $parts = explode('/', $displayText);
        if (count($parts) > 1 && is_numeric($parts[0])) {
            if (!$allLevels && isset($parts[$parts[0] + 1])) {
                return $parts[$parts[0] + 1];
            }
            array_shift($parts);
            array_pop($parts);
            return implode($separator, $parts);
        }
        return $displayText;
    }
}
