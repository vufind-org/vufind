<?php
/**
 * Facet Helper
 *
 * PHP version 5
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Search
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace Finna\Search\Solr;

/**
 * Functions for manipulating facets
 *
 * @category VuFind2
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
     *
     * @return array Facet hierarchy
     */
    public function buildFacetArray($facet, $facetList, $urlHelper = false)
    {
        $result = parent::buildFacetArray($facet, $facetList, $urlHelper);

        if ($urlHelper !== false) {
            // Recreate href's for any facet items that have children/ancestors
            // applied to remove them from the filters
            $result = $this->removeAncestorAndChildFilters($facet, $result);
        }

        return $result;
    }

    /**
     * Filter hierarchical facets
     *
     * @param array $facets         Facet list
     * @param array $filters        Facet filters
     * @param array $excludeFilters Exclusion filters
     *
     * @return array
     */
    public function filterFacets($facets, $filters, $excludeFilters)
    {
        if (!empty($filters)) {
            foreach ($facets as $key => &$facet) {
                $value = $facet['value'];
                list($level) = explode('/', $value);
                $match = false;
                $levelSpecified = false;
                foreach ($filters as $filterItem) {
                    list($filterLevel) = explode('/', $filterItem);
                    if ($level == $filterLevel) {
                        $levelSpecified = true;
                    }
                    if (strncmp($value, $filterItem, strlen($filterItem)) == 0) {
                        $match = true;
                    }
                }
                if (!$match && $levelSpecified) {
                    unset($facets[$key]);
                } elseif (!empty($facet['children'])) {
                    $facet['children'] = $this->filterFacets(
                        $facet['children'], $filters, $excludeFilters
                    );
                }
            }
        }

        if (!empty($excludeFilters)) {
            foreach ($facets as $key => &$facet) {
                $value = $facet['value'];
                $match = false;
                foreach ($excludeFilters as $filterItem) {
                    if (strncmp($value, $filterItem, strlen($filterItem)) == 0) {
                        unset($facets[$key]);
                        continue 2;
                    }
                }
                if (!empty($facet['children'])) {
                    $facet['children'] = $this->filterFacets(
                        $facet['children'], $filters, $excludeFilters
                    );
                }
            }
        }

        return array_values($facets);
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
            '/', array_slice(explode('/', $item['value']), 1, $level + 1)
        ) . '/';

        // Extract value part from filter and compare with ancestor level part
        list(, $filterValue) = explode('/', substr($filter, 1, -1), 2);
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
        list(, $itemValue) = explode('/', $item['value'], 2);
        list(, $filterValue) = explode('/', substr($filter, 1, -1), 2);
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
        $facetName, $facets, $ancestorApplied = false
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
                    $facetName, $item['children'],
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
     */
    protected function removeFilters($facet, $item, $children)
    {
        $urlParts = explode('?', $item['href'], 2);
        if (!isset($urlParts[1])) {
            return $item['href'];
        }
        parse_str(htmlspecialchars_decode($urlParts[1]), $params);

        if (!isset($params['filter'])) {
            return $item['href'];
        }
        $newFilters = [];
        foreach ($params['filter'] as $filter) {
            list($filterField, $filterValue) = explode(':', $filter, 2);
            if ($filterField == $facet || $filterField == "~$facet") {
                if ((!$children && $this->isAncestor($item, $filterValue))
                    || ($children && $this->isChild($item, $filterValue))
                ) {
                    continue;
                }
            }
            $newFilters[] = $filter;
        }
        $params['filter'] = $newFilters;
        return $urlParts[0] . '?' . htmlspecialchars(http_build_query($params));
    }
}
