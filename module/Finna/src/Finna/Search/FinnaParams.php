<?php
/**
 * Additional functionality for Finna parameters.
 *
 * PHP version 7
 *
 * Copyright (C) The National Library 2015-2016.
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
 * @package  Search
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace Finna\Search;

use VuFind\Search\QueryAdapter;

/**
 * Additional functionality for Finna parameters.
 *
 * @category VuFind
 * @package  Search
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
trait FinnaParams
{
    /**
     * Build a string for onscreen display showing the
     *   query used in the search (not the filters).
     *
     * @return string user friendly version of 'query'
     */
    public function getDisplayQuery()
    {
        // Set up callbacks with no-op translator to keep the English
        // boolean operators:
        $translate = function ($str) {
            return $str;
        };
        $showField = [$this->getOptions(), 'getHumanReadableFieldName'];

        // Build display query:
        $result = QueryAdapter::display($this->getQuery(), $translate, $showField);

        // Hack to display WorkKeys search nicer
        $str = preg_quote($showField('WorkKeys'));
        $result = preg_replace("/$str:\"[^\)]+\"/", $str, $result);

        return $result;
    }

    /**
     * Get information on the current state of the boolean checkbox facets.
     *
     * @param array $whitelist Whitelist of checkbox filters to return (null for all)
     *
     * @return array
     */
    public function getCheckboxFacets(array $whitelist = null)
    {
        $facets = parent::getCheckboxFacets($whitelist);

        // Hide checkboxfacets that are
        // configured as SearchTabsFilters
        foreach ($facets as $field => $details) {
            if ($this->hasHiddenFilter($details['filter'])) {
                unset($facets[$field]);
            }
        }
        return $facets;
    }

    /**
     * Get the date range field from options, if available
     *
     * @return string
     */
    public function getDateRangeSearchField()
    {
        if (!is_callable([$this->getOptions(), 'getDateRangeSearchField'])) {
            return '';
        }
        return $this->getOptions()->getDateRangeSearchField();
    }

    /**
     * Get a user-friendly string to describe the provided facet field.
     *
     * @param string $field   Facet field name.
     * @param string $value   Facet value.
     * @param string $default Default field name (null for default behavior).
     *
     * @return string         Human-readable description of field.
     */
    public function getFacetLabel($field, $value = null, $default = null)
    {
        if ($this->isGeographicFilter($field)) {
            return 'Geographical Area';
        }
        return parent::getFacetLabel($field, $value, $default);
    }

    /**
     * Get geographic filters from the given list of filters.
     *
     * @param array $filterList Filters
     *
     * @return array
     */
    public function getGeographicFilters($filterList)
    {
        $results = [];
        foreach ($filterList as $filters) {
            foreach ($filters as $filter) {
                if ($this->isGeographicFilter($filter['field'])) {
                    $results[] = $filter['field'];
                }
            }
        }
        return $results;
    }

    /**
     * Check if the given filter is a geographic filter.
     *
     * @param string|array $filter Facet
     *
     * @return boolean
     */
    public function isGeographicFilter($filter)
    {
        $filter = $filter[0]['field'] ?? $filter;
        return strncmp($filter, '{!geofilt', 9) === 0;
    }

    /**
     * Does the object already contain the specified hidden filter?
     *
     * @param string $filter A filter string from url : "field:value"
     *
     * @return bool
     */
    public function hasHiddenFilter($filter)
    {
        // Extract field and value from URL string:
        list($field, $value) = $this->parseFilter($filter);

        if (isset($this->hiddenFilters[$field])
            && in_array($value, $this->hiddenFilters[$field])
        ) {
            return true;
        }

        return false;
    }

    /**
     * Remove date range filter from the given list of filters.
     *
     * @param array $filterList Filters
     *
     * @return array
     */
    public function removeDateRangeFilter($filterList)
    {
        $dateRangeField = $this->getDateRangeSearchField();
        if ($dateRangeField) {
            $label = $this->getFacetLabel($dateRangeField);
            if (isset($filterList[$label])) {
                unset($filterList[$label]);
            }
        }
        return $filterList;
    }

    /**
     * Remove geographic filters from the given list of filters.
     *
     * @param array $filterList Filters
     *
     * @return array
     */
    public function removeGeographicFilters($filterList)
    {
        foreach ($filterList as $key => $filters) {
            foreach ($filters as $filterKey => $filter) {
                if ($this->isGeographicFilter($filter['field'])) {
                    unset($filters[$filterKey]);
                }
            }
            if (empty($filters)) {
                unset($filterList[$key]);
            }
        }
        return $filterList;
    }

    /**
     * Remove all hidden filters.
     *
     * @return void
     */
    public function removeHiddenFilters()
    {
        $this->hiddenFilters = [];
    }

    /**
     * Parse "from" and "to" values out of a spatial date range
     * filter (or return false if the filter is not a range).
     *
     * @param string $filter Solr filter to parse.
     *
     * @return array|bool   Array with 'from', 'to' and 'type' (if available) values
     * extracted from the range or false if the provided query is not a range.
     */
    public function parseDateRangeFilter($filter)
    {
        // VuFind2 initialized date range:
        // search_daterange_mv:(Intersects|Within)|[1900 TO 2000]
        $regex = '/(\w+)\|\[([\d-]+|\*)\s+TO\s+([\d-]+|\*)\]/';
        if (preg_match($regex, $filter, $matches)) {
            return [
                'from' => $matches[2], 'to' => $matches[3], 'type' => $matches[1]
            ];
        }

        // VuFind2 uninitialized or generic date range:
        // search_daterange_mv:[1900 TO 2000]
        $regex = '/\[([\d-]+|\*)\s+TO\s+([\d-]+|\*)\]/';
        if (preg_match($regex, $filter, $matches)) {
            return [
                'from' => $matches[1], 'to' => $matches[2]
            ];
        }

        // VuFind1 overlap
        $regex
            = '/[\(\[]\"*[\d-]+\s+([\d-]+)\"*[\s\w]+\"*([\d-]+)\s+[\d-]+\"*[\)\]]/';

        if (preg_match($regex, $filter, $matches)) {
            $from = $matches[1];
            $to = $matches[2];
            $type = 'overlap';
        } else {
            // VuFind1 within
            $regex
    // @codingStandardsIgnoreLine - long regex
                = '/[\(\[]\"*([\d-]+\.?\d*)\s+[\d-]+\"*[\s\w]+\"*[\d-]+\s+([\d-]+\.?\d*)\"*[\)\]]/';

            if (!preg_match($regex, $filter, $matches)) {
                return false;
            }

            $from = $matches[1];
            $to = $matches[2];
            $type = 'within';
            // Adjust date range end points to match original search query
            $from += 0.5;
            $to -= 0.5;
        }
        $from = $from * 86400;
        $from = new \DateTime("@{$from}");
        $from = $from->format('Y');

        $to = $to * 86400;
        $to = new \DateTime("@{$to}");
        $to = $to->format('Y');

        return ['from' => $from, 'to' => $to, 'type' => $type];
    }

    /**
     * Format a single filter for use in getFilterList().
     *
     * @param string $field     Field name
     * @param string $value     Field value
     * @param string $operator  Operator (AND/OR/NOT)
     * @param bool   $translate Should we translate the label?
     *
     * @return array
     */
    protected function formatFilterListEntry($field, $value, $operator, $translate)
    {
        $res = parent::formatFilterListEntry($field, $value, $operator, $translate);
        if ($this->isDateRangeFilter($field)) {
            return $this->formatDateRangeFilterListEntry($res, $field, $value);
        }
        if ($this->isGeographicFilter($field)) {
            return $this->formatGeographicFilterListEntry($res, $field, $value);
        }
        return $res;
    }

    /**
     * Format a date range filter for use in getFilterList().
     *
     * @param array  $listEntry List entry
     * @param string $field     Field name
     * @param string $value     Field value
     *
     * @return array
     */
    protected function formatDateRangeFilterListEntry($listEntry, $field, $value)
    {
        $range = $this->parseDateRangeFilter($value);
        if ($range) {
            $display = '';
            $from = $range['from'];
            $to = $range['to'];

            if ($from != '*') {
                $display .= $from;
            }
            $ndash = html_entity_decode('&#x2013;', ENT_NOQUOTES, 'UTF-8');
            $display .= $ndash;
            if ($to != '*') {
                $display .= $to;
            }
            $listEntry['displayText'] = $display;
        }
        return $listEntry;
    }

    /**
     * Format a geographic filter for use in getFilterList().
     *
     * @param array  $listEntry List entry
     * @param string $field     Field name
     * @param string $value     Field value
     *
     * @return array
     */
    protected function formatGeographicFilterListEntry($listEntry, $field, $value)
    {
        return $listEntry;
    }

    /**
     * Check if the given filter is a date range filter
     *
     * @param string $field Filter field
     *
     * @return boolean
     */
    protected function isDateRangeFilter($field)
    {
        if (!($dateRangeField = $this->getDateRangeSearchField())) {
            return false;
        }
        return $field == $dateRangeField;
    }

    /**
     * Pull the page size parameter or set to default
     *
     * @param \Zend\StdLib\Parameters $request Parameter object representing user
     * request.
     *
     * @return void
     */
    protected function initLimit($request)
    {
        // Check for a limit parameter in the url.
        $options = $this->getOptions();
        $defaultLimit = is_callable([$options, 'getDefaultLimitByView'])
            ? $options->getDefaultLimitByView($this->view)
            : $options->getDefaultLimit();

        if (($limit = (int)$request->get('limit')) != $defaultLimit) {
            // make sure the url parameter is a valid limit -- either
            // one of the explicitly allowed values, or at least smaller
            // than the largest allowed. (This leniency is useful in
            // combination with combined search, where it is often useful
            // to reduce the size of result lists without actually enabling
            // the user's ability to select a reduced list size).
            $legalOptions = $this->getOptions()->getLimitOptions();
            if (in_array($limit, $legalOptions)
                || ($limit > 0 && $limit < max($legalOptions))
            ) {
                $this->limit = $limit;
                return;
            }
        }

        // Increase default limit for RSS mode:
        if ($this->getView() == 'rss' && $defaultLimit < 50) {
            $defaultLimit = 50;
        }

        // If we got this far, setting was missing or invalid; load the default
        $this->limit = $defaultLimit;
    }

    /**
     * Get view options list type setting
     *
     * @return bool
     */
    public function getViewOptionListType()
    {
        $options = $this->getOptions();
        $viewOptionsIcons = is_callable([$options, 'getViewOptionListType'])
            ? $options->getViewOptionListType() : false;
        return $viewOptionsIcons;
    }

    /**
     * Used to be used for activating all facets, but no longer needed
     *
     * @return void
     */
    public function activateAllFacets()
    {
        // NOOP for backwards-compatibility
    }
}
