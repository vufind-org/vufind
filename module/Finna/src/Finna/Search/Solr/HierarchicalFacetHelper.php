<?php
/**
 * Facet Helper
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2014-2015.
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
}
