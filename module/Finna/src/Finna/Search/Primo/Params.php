<?php
/**
 * Primo Central Search Parameters
 *
 * PHP version 5
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
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
     * Add a checkbox facet.  When the checkbox is checked, the specified filter
     * will be applied to the search.  When the checkbox is not checked, no filter
     * will be applied.
     *
     * @param string $filter [field]:[value] pair to associate with checkbox
     * @param string $desc   Description to associate with the checkbox
     *
     * @return void
     */
    public function addCheckboxFacet($filter, $desc)
    {
        // Extract the facet field name from the filter, then add the
        // relevant information to the array.
        list($fieldName) = explode(':', $filter);
        if (!isset($this->checkboxFacets[$fieldName])) {
            $this->checkboxFacets[$fieldName] = [];
        }
        $this->checkboxFacets[$fieldName][]
            = ['desc' => $desc, 'filter' => $filter];
    }

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
     * @return array
     */
    public function getCheckboxFacets()
    {
        // Build up an array of checkbox facets with status booleans and
        // toggle URLs.
        $res = [];
        foreach ($this->checkboxFacets as $field => $facets) {
            foreach ($facets as $facet) {
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
