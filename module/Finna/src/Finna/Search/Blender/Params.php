<?php

/**
 * Blender Search Parameters
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2022.
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
 * @package  Search_Blender
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace Finna\Search\Blender;

use Finna\Search\Solr\AuthorityHelper;
use VuFind\Config\ConfigManagerInterface;
use VuFind\Search\Solr\HierarchicalFacetHelper;
use VuFindSearch\ParamBag;

use function in_array;

/**
 * Blender Search Parameters
 *
 * @category VuFind
 * @package  Search_Blender
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class Params extends \VuFind\Search\Blender\Params
{
    use \Finna\Search\Solr\ParamsSharedTrait;

    /**
     * Helper for formatting authority id filter display texts.
     *
     * @var AuthorityHelper
     */
    protected $authorityHelper = null;

    /**
     * Constructor
     *
     * @param \VuFind\Search\Base\Options $options       Options to use
     * @param ConfigManagerInterface      $configManager Config manager
     * @param HierarchicalFacetHelper     $facetHelper   Hierarchical facet helper
     * @param array                       $searchParams  Search params for backends
     * @param \VuFind\Config\Config       $blenderConfig Blender configuration
     * @param array                       $mappings      Blender mappings
     * @param AuthorityHelper             $authHelper    Authority helper
     */
    public function __construct(
        \VuFind\Search\Base\Options $options,
        ConfigManagerInterface $configManager,
        HierarchicalFacetHelper $facetHelper,
        array $searchParams,
        \VuFind\Config\Config $blenderConfig,
        array $mappings,
        protected AuthorityHelper $authHelper
    ) {
        parent::__construct(
            $options,
            $configManager,
            $facetHelper,
            $searchParams,
            $blenderConfig,
            $mappings
        );
    }

    /**
     * Get the date range field from options, if available
     *
     * @return string
     */
    public function getDateRangeSearchField()
    {
        return $this->getOptions()->getDateRangeSearchField();
    }

    /**
     * Return current date range filter.
     *
     * @return mixed false|array Filter
     */
    public function getDateRangeFilter()
    {
        $filterList = $this->getFilterList();
        foreach ($filterList as $filters) {
            foreach ($filters as $filter) {
                if ($this->isDateRangeFilter($filter['field'])) {
                    return $filter;
                }
            }
        }
        return false;
    }

    /**
     * Create search backend parameters for advanced features.
     *
     * @return ParamBag
     */
    public function getBackendParameters(): ParamBag
    {
        $result = parent::getBackendParameters();

        if ($primoParams = $result->get('params_Primo')) {
            $filterList = $primoParams[0]->get('filterList');
            if (isset($filterList['pcAvailability'])) {
                // We have a pcAvailability filter so tell the connector to ignore
                // any hidden filter:
                $primoParams[0]->set('ignorePcAvailabilityHiddenFilter', true);
            }
        }
        return $result;
    }

    /**
     * Add filters to the object based on values found in the request object.
     *
     * @param \Laminas\Stdlib\Parameters $request Parameter object representing user
     * request.
     *
     * @return void
     */
    protected function initFilters($request)
    {
        parent::initFilters($request);
        foreach ($this->searchParams as $params) {
            if ($params instanceof \Finna\Search\Solr\Params) {
                $params->initSpatialDateRangeFilter($request);
            }
        }
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
        if (
            $translate
            && in_array($field, $this->getOptions()->getHierarchicalFacets())
        ) {
            return $this->translateHierarchicalFacetFilter(
                $field,
                $value,
                $operator
            );
        }
        $result = parent::formatFilterListEntry(
            $field,
            $value,
            $operator,
            $translate
        );

        if ($this->isDateRangeFilter($field)) {
            return $this->formatDateRangeFilterListEntry(
                $result,
                $field,
                $value
            );
        }
        if ($this->isGeographicFilter($field)) {
            return $this->formatGeographicFilterListEntry(
                $result,
                $field,
                $value
            );
        }

        return $this->formatAuthorIdFilterListEntry($result, $field, $value);
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
                'from' => $matches[2], 'to' => $matches[3], 'type' => $matches[1],
            ];
        }

        // VuFind2 uninitialized or generic date range:
        // search_daterange_mv:[1900 TO 2000]
        $regex = '/\[([\d-]+|\*)\s+TO\s+([\d-]+|\*)\]/';
        if (preg_match($regex, $filter, $matches)) {
            return [
                'from' => $matches[1], 'to' => $matches[2], 'type' => 'overlap',
            ];
        }

        return false;
    }

    /**
     * Remove all hidden filters.
     *
     * Used in the search tabs filter handling thingy.
     *
     * @return void
     */
    public function removeHiddenFilters()
    {
        $this->hiddenFilters = [];
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
}
