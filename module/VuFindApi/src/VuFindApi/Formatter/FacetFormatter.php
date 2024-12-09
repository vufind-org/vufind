<?php

/**
 * Facet formatter for API responses
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2015-2016.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.    See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  API_Formatter
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */

namespace VuFindApi\Formatter;

use VuFind\Search\Base\Results;

use function in_array;

/**
 * Facet formatter for API responses
 *
 * @category VuFind
 * @package  API_Formatter
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
class FacetFormatter extends BaseFormatter
{
    /**
     * Build an array of facet filters from the request params
     *
     * @param array $request Request params
     *
     * @return array
     */
    protected function buildFacetFilters($request)
    {
        $facetFilters = [];
        if (isset($request['facetFilter'])) {
            foreach ($request['facetFilter'] as $filter) {
                [$facetField, $regex] = explode(':', $filter, 2);
                $regex = trim($regex);
                if (str_starts_with($regex, '"')) {
                    $regex = substr($regex, 1);
                }
                if (str_ends_with($regex, '"')) {
                    $regex = substr($regex, 0, -1);
                }
                $facetFilters[$facetField][] = $regex;
            }
        }
        return $facetFilters;
    }

    /**
     * Match a facet item with the filters.
     *
     * @param array $facet   Facet
     * @param array $filters Facet filters
     *
     * @return boolean
     */
    protected function matchFacetItem($facet, $filters)
    {
        $discard = true;
        array_walk_recursive(
            $facet,
            function ($item, $key) use (&$discard, $filters) {
                if ($discard && $key == 'value') {
                    foreach ($filters as $filter) {
                        $pattern = '/' . addcslashes($filter, '/') . '/';
                        if (preg_match($pattern, $item) === 1) {
                            $discard = false;
                            break;
                        }
                    }
                }
            }
        );
        return !$discard;
    }

    /**
     * Recursive function to create a facet value list for a single facet
     *
     * @param array $list    Facet items
     * @param array $filters Facet filters
     *
     * @return array
     */
    protected function buildFacetValues($list, $filters = false)
    {
        $result = [];
        $fields = [
            'value', 'displayText', 'count',
            'children', 'href', 'isApplied',
        ];
        foreach ($list as $value) {
            $resultValue = [];
            if ($filters && !$this->matchFacetItem($value, $filters)) {
                continue;
            }

            foreach ($value as $key => $item) {
                if (!in_array($key, $fields)) {
                    continue;
                }
                if ($key == 'children') {
                    if (!empty($item)) {
                        $resultValue[$key]
                            = $this->buildFacetValues(
                                $item,
                                $filters
                            );
                    }
                } else {
                    if ($key == 'displayText') {
                        $key = 'translated';
                    }
                    $resultValue[$key] = $item;
                }
            }
            $result[] = $resultValue;
        }
        return $result;
    }

    /**
     * Create the result facet list
     *
     * @param array   $request               Request parameters
     * @param Results $results               Search results
     * @param array   $hierarchicalFacetData Hierarchical facet data
     *
     * @return array
     */
    public function format($request, Results $results, $hierarchicalFacetData)
    {
        if ($results->getResultTotal() <= 0 || empty($request['facet'])) {
            return [];
        }

        $filters = $this->buildFacetFilters($request);
        $facets = $results->getFacetList();

        // Format hierarchical facets, if any
        if ($hierarchicalFacetData) {
            foreach ($hierarchicalFacetData as $facet => $data) {
                $facets[$facet]['list'] = $data;
            }
        }

        // Add "missing" fields to non-hierarchical facets to make them similar
        // to hierarchical facets for easier consumption.
        $urlHelper = $results->getUrlQuery();
        foreach ($facets as $facetKey => &$facetItems) {
            if (isset($hierarchicalFacetData[$facetKey])) {
                continue;
            }

            foreach ($facetItems['list'] as &$item) {
                $href = !$item['isApplied']
                    ? $urlHelper->addFacet(
                        $facetKey,
                        $item['value'],
                        $item['operator']
                    )->getParams(false) : $urlHelper->getParams(false);
                $item['href'] = $href;
            }
        }
        $this->filterArrayValues($facets);

        $result = [];
        foreach ($facets as $facetName => $facetData) {
            $result[$facetName] = $this->buildFacetValues(
                $facetData['list'],
                !empty($filters[$facetName]) ? $filters[$facetName] : false
            );
        }
        return $result;
    }
}
