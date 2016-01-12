<?php
/**
 * Additional functionality for Finna parameters.
 *
 * PHP version 5
 *
 * Copyright (C) The National Library 2015.
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
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
namespace Finna\Search;
use Finna\Solr\Utils;

/**
 * Additional functionality for Finna parameters.
 *
 * @category VuFind2
 * @package  Search
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
*/
trait FinnaParams
{
    /**
     * Current data range filter
     *
     * @var array
     */
    protected $spatialDateRangeFilter = null;

    /**
     * MetaLib search set
     *
     * @var string
     */
    protected $metalibSearchSet = null;

    /**
     * Add filters to the object based on values found in the request object.
     *
     * @param \Zend\StdLib\Parameters $request Parameter object representing user
     * request.
     *
     * @return void
     */
    public function initDateFilters($request)
    {
        return parent::initDateFilters($request);
    }

    /**
     * Return the current filters as an array of strings ['field:filter']
     *
     * @return array $filterQuery
     */
    public function getFilterSettings()
    {
        $filters = parent::getFilterSettings();
        if ($this->spatialDateRangeFilter) {
            foreach ($filters as &$filter) {
                $regex = '/[}]*' . self::SPATIAL_DATERANGE_FIELD . ':.*$/';
                if (preg_match($regex, $filter)) {
                    $from = $this->spatialDateRangeFilter['from'];
                    $to = $this->spatialDateRangeFilter['to'];
                    $type = $this->spatialDateRangeFilter['type'];
                    $field = $this->spatialDateRangeFilter['field'];
                    $filter
                        = Utils::buildSpatialDateRangeQuery(
                            $from, $to, $type, $field
                        );
                    break;
                }
            }
        }
        return $filters;
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
        if ($this->spatialDateRangeFilter
            && isset($this->spatialDateRangeFilter['field'])
            && $this->spatialDateRangeFilter['field'] == $field
        ) {
            $filter = $this->spatialDateRangeFilter['val'];
            $type = isset($this->spatialDateRangeFilter['type'])
                ? $this->spatialDateRangeFilter['type']
                : null
            ;

            $range = Utils::parseSpatialDateRange($filter, $type, true);
            if ($range) {
                $display = '';
                $from = $range['from'];
                $to = $range['to'];

                if ($from != '*') {
                    $display .= $from;
                }
                $display .= '–';
                if ($to != '*') {
                    $display .= $to;
                }
                $res['displayText'] = $display;
            }
        }
        return $res;
    }

    /**
     * Return index field name used in date range searches.
     *
     * @return string
     */
    public function getSpatialDateRangeField()
    {
        return self::SPATIAL_DATERANGE_FIELD;
    }

    /**
     * Return current MetaLib search set
     *
     * @return string
     */
    public function getMetaLibSearchSet()
    {
        return $this->metalibSearchSet;
    }

    /**
     * Return current date range filter.
     *
     * @param boolean $fromFilterList False if date range filter is already
     * inited from the request object (see initSpatialDateRangeFilter).
     * True if date range filter should be resolved from the list of active filters.
     *
     * @return mixed false|array Filter
     */
    public function getSpatialDateRangeFilter($fromFilterList = false)
    {
        if ($fromFilterList) {
            $daterangeField = $this->getSpatialDateRangeField();
            $filterList = $this->getFilterList();
            foreach ($filterList as $facet => $filters) {
                foreach ($filters as $filter) {
                    if ($filter['field'] ==  $daterangeField) {
                        if ($current = $this->getSpatialDateRangeFilter()) {
                            $filter['type'] = $current['type'];
                        }
                        return $filter;
                    }
                }
            }
            return false;
        } else {
            return $this->spatialDateRangeFilter;
        }
    }

    /**
     * Remove data range filter from the given list of filters.
     *
     * @param array $filters Filters
     *
     * @return array
     */
    public function removeDatarangeFilter($filters)
    {
        $daterangeField = $this->getSpatialDateRangeField();
        $filterList = $this->getFilterList();
        foreach ($filterList as $field => &$filters) {
            $filters
                = array_filter(
                    $filters,
                    function ($f) use ($daterangeField) {
                        return $f['field'] != $daterangeField;
                    }
                );
        }
        $filterList
            = array_filter(
                $filterList,
                function ($filters) {
                    return count($filters) > 0;
                }
            );
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
}
