<?php
/**
 * Primo Central Search Parameters
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2015.
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
 * @package  Search_Primo
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
namespace Finna\Search\Primo;

/**
 * Primo Central Search Parameters
 *
 * @category VuFind
 * @package  Search_Primo
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class Params extends \VuFind\Search\Primo\Params
{
    use \Finna\Search\FinnaParams;

    /**
     * Get a formatted list of checkbox filter values ($field => array of values).
     *
     * @return array
     */
    protected function getCheckboxFacetValues()
    {
        $list = [];
        foreach ($this->checkboxFacets as $facets) {
            foreach ($facets as $current) {
                list($field, $value) = $this->parseFilter($current['filter']);
                if (!isset($list[$field])) {
                    $list[$field] = [];
                }
                $list[$field][] = $value;
            }
        }
        return $list;
    }

    /**
     * Get information on the current state of the boolean checkbox facets.
     *
     * @param array $allowed List of checkbox filters to return (null for all)
     *
     * @return array
     */
    public function getCheckboxFacets(array $allowed = null)
    {
        // Build up an array of checkbox facets with status booleans and
        // toggle URLs.
        $res = [];
        foreach ($this->checkboxFacets as $facets) {
            foreach ($facets as $facet) {
                // If the current filter is not allowed, skip it (but accept
                // everything if the list of allowed facets is empty).
                if (!empty($allowed) && !in_array($facet['filter'], $allowed)) {
                    continue;
                }
                if ($this->hasHiddenFilter($facet['filter'])) {
                    continue;
                }
                $facet['selected'] = $this->hasFilter($facet['filter']);

                // Is this checkbox always visible, even if non-selected on the
                // "no results" screen?  By default, no (may be overridden by
                // child classes).
                $facet['alwaysVisible'] = false;

                $res[] = $facet;
            }
        }
        return $res;
    }

    /**
     * Return current date range filter.
     *
     * @return mixed false|array Filter
     */
    public function getDateRangeFilter()
    {
        $field = $this->getDateRangeSearchField();
        $filterList = $this->getFilterList();
        foreach ($filterList as $facet => $filters) {
            foreach ($filters as $filter) {
                if ($filter['field'] == $field) {
                    return $filter;
                }
            }
        }
        return false;
    }
}
