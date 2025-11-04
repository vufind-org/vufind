<?php

/**
 * Facet Helper
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2014-2016.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Search
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */

namespace Finna\Search\Solr;

use function array_slice;
use function count;
use function strlen;

/**
 * Functions for manipulating facets
 *
 * @category VuFind
 * @package  Search
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class HierarchicalFacetHelper extends \VuFind\Search\Solr\HierarchicalFacetHelper
{
    /**
     * Helper method for building hierarchical facets:
     * Convert facet list to a hierarchical array
     *
     * @param string    $facet     Facet name
     * @param array     $facetList Facet list
     * @param UrlHelper $urlHelper Query URL helper for building facet URLs
     * @param bool      $escape    Whether to escape URLs
     *
     * @return array Facet hierarchy
     */
    public function buildFacetArray(
        $facet,
        $facetList,
        $urlHelper = false,
        $escape = true
    ) {
        $result = parent::buildFacetArray($facet, $facetList, $urlHelper, $escape);

        if ($urlHelper !== false) {
            // Recreate href's for any facet items that have children/ancestors
            // applied to remove them from the filters
            $result = $this->removeAncestorAndChildFilters($facet, $result);
        }

        return $result;
    }

    /**
     * Check if a filter value is an ancestor of the given facet item
     *
     * @param array  $item   Facet item
     * @param string $filter Filter value
     *
     * @return bool
     */
    protected function isAncestor($item, $filter)
    {
        if (!preg_match('/^"(\d+)\/(.+)"$/', $filter, $matches)) {
            return false;
        }
        $level = $matches[1];
        if ($level >= $item['level']) {
            return false;
        }
        // Extract the ancestor level part from the item value
        $part = implode(
            '/',
            array_slice(explode('/', $item['value']), 1, $level + 1)
        ) . '/';

        // Extract value part from filter and compare with ancestor level part
        [, $filterValue] = explode('/', substr($filter, 1, -1), 2);
        return $part == $filterValue;
    }

    /**
     * Check if a filter value is a child of the given facet item
     *
     * @param array  $item   Facet item
     * @param string $filter Filter value
     *
     * @return bool
     */
    protected function isChild($item, $filter)
    {
        if (!preg_match('/^"(\d+)\/.+"$/', $filter, $matches)) {
            return false;
        }
        $level = $matches[1];
        if ($level <= $item['level']) {
            return false;
        }

        // Compare item value and filter value for the length of the item value
        [, $itemValue] = explode('/', $item['value'], 2);
        [, $filterValue] = explode('/', substr($filter, 1, -1), 2);
        return strncmp($filterValue, $itemValue, strlen($itemValue)) == 0;
    }

    /**
     * Check all facets for applied ancestors/children and change the href to remove
     * those filters
     *
     * @param string $facetName       Facet name
     * @param array  $facets          Hierarchical facet array
     * @param bool   $ancestorApplied Boolean indicating whether any ancestor is
     * applied
     *
     * @return array Modified facet array
     */
    protected function removeAncestorAndChildFilters(
        $facetName,
        $facets,
        $ancestorApplied = false
    ) {
        foreach ($facets as &$item) {
            if (!$item['isApplied'] && $item['hasAppliedChildren']) {
                $item['href'] = $this->removeFilters($facetName, $item, true);
            }
            if ($item['level'] > 0 && $ancestorApplied) {
                $item['href'] = $this->removeFilters($facetName, $item, false);
            }
            if ($item['children']) {
                $item['children'] = $this->removeAncestorAndChildFilters(
                    $facetName,
                    $item['children'],
                    $ancestorApplied || $item['isApplied']
                );
            }
        }
        return $facets;
    }

    /**
     * Remove any ancestor/child filters from the facet href. Would rather use
     * urlQueryHelper, but it doesn't allow removing multiple filters and adding a
     * new one.
     *
     * @param string $facet    Facet name
     * @param array  $item     Facet item
     * @param bool   $children Whether to remove children or ancestors
     *
     * @return string Modified href
     * @todo   New UrlQueryHelper might do the trick!
     */
    protected function removeFilters($facet, $item, $children)
    {
        $urlParts = explode('?', $item['href'], 2);
        if (!isset($urlParts[1])) {
            return $item['href'];
        }
        parse_str($urlParts[1], $params);

        if (!isset($params['filter'])) {
            return $item['href'];
        }
        $newFilters = [];
        foreach ($params['filter'] as $filter) {
            [$filterField, $filterValue] = explode(':', $filter, 2);
            if ($filterField == $facet || $filterField == "~$facet") {
                if (
                    (!$children && $this->isAncestor($item, $filterValue))
                    || ($children && $this->isChild($item, $filterValue))
                ) {
                    continue;
                }
            }
            $newFilters[] = $filter;
        }
        $params['filter'] = $newFilters;
        return $urlParts[0] . '?' . http_build_query($params);
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
        $results = [];
        $count = count($facetList);
        $i = 0;
        foreach ($facetList as $facetItem) {
            $tmpFacet = $facetItem;
            $children = !empty($tmpFacet['children'])
                ? $tmpFacet['children']
                : [];
            if ($children && $tmpFacet['level'] === '0') {
                $tmpFacet['opt_group_start'] = true;
            }
            unset($tmpFacet['children']);
            if (++$i === $count && (($tmpFacet['level'] ?? '0') !== '0' && !$children)) {
                $tmpFacet['opt_group_end'] = true;
                $i = 0;
            }
            $results[] = $tmpFacet;
            if ($children) {
                $results = array_merge(
                    $results,
                    $this->flattenFacetHierarchy($children)
                );
            }
        }

        return $results;
    }
}
