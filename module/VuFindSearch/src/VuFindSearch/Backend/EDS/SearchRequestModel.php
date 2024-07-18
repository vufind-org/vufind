<?php

/**
 * EBSCO EDS API Search Model
 *
 * PHP version 8
 *
 * Copyright (C) Serials Solutions 2011.
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
 * @category EBSCOIndustries
 * @package  EBSCO
 * @author   Michelle Milton <mmilton@epnet.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */

namespace VuFindSearch\Backend\EDS;

use function array_key_exists;
use function count;
use function intval;
use function strlen;

/**
 * EBSCO EDS API Search Model
 *
 * @category EBSCOIndustries
 * @package  EBSCO
 * @author   Michelle Milton <mmilton@epnet.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class SearchRequestModel
{
    /**
     * What to search for, formatted as [{boolean operator},][{field code}:]{term}
     *
     * @var array
     */
    protected $query = [];

    /**
     * Whether or not to return facets with the search results. valid values are
     * 'y' or 'n'
     *
     * @var string
     */
    protected $includeFacets;

    /**
     * Array of filters to apply to the search
     *
     * @var array
     */
    protected $facetFilters = [];

    /**
     * Array mapping a facet field to the AND/OR operator to use with it
     *
     * @var array
     */
    protected $facetOperators = [];

    /**
     * Sort option to apply
     *
     * @var string
     */
    protected $sort;

    /**
     * Options to limit the results by
     *
     * @var array
     */
    protected $limiters = [];

    /**
     * Mode to be effective in the search
     *
     * @var string
     */
    protected $searchMode;

    /**
     * Expanders to use. Comma separated.
     *
     * @var array
     */
    protected $expanders = [];

    /**
     * Requested level of detail to return the results with
     *
     * @var string
     */
    protected $view;

    /**
     * Number of records to return
     *
     * @var int
     */
    protected $resultsPerPage;

    /**
     * Page number of records to return. This is used in conjunction with the
     * {@link $resultsPerPage} to determine the set of records to return.
     *
     * @var int
     */
    protected $pageNumber;

    /**
     * Whether or not to highlight the search term in the results.
     *
     * @var bool
     */
    protected $highlight;

    /**
     * Collection of user actions to apply to current request
     *
     * @var array
     */
    protected $actions = [];

    /**
     * Constructor
     *
     * Sets up the EDS API Search Request model
     *
     * @param array $parameters parameters to populate request
     */
    public function __construct($parameters = [])
    {
        $this->setParameters($parameters);
    }

    /**
     * Format a date limiter
     *
     * @param string $filter Filter value
     *
     * @return string
     */
    protected function formatDateLimiter($filter)
    {
        // PublicationDate:[xxxx TO xxxx]
        $dates = substr($filter, 17);
        $dates = substr($dates, 0, strlen($dates) - 1);
        $parts = explode(' TO ', $dates, 2);
        $start = $end = null;
        if (count($parts) == 2) {
            $start = trim($parts[0]);
            $end = trim($parts[1]);
        }
        if ('*' == $start || null == $start) {
            $start = '0000';
        }
        if ('*' == $end || null == $end) {
            $end = date('Y') + 1;
        }
        return "DT1:$start-01/$end-12";
    }

    /**
     * Set properties from parameters
     *
     * @param array $parameters Parameters to set
     *
     * @return void
     */
    public function setParameters($parameters = [])
    {
        foreach ($parameters as $key => $values) {
            switch ($key) {
                case 'filters':
                    foreach ($values as $filter) {
                        if (str_starts_with($filter, 'LIMIT|')) {
                            $this->addLimiter(substr($filter, 6));
                        } elseif (str_starts_with($filter, 'EXPAND:')) {
                            $this->addExpander(substr($filter, 7));
                        } elseif (str_starts_with($filter, 'SEARCHMODE:')) {
                            $this->searchMode = substr($filter, 11, null);
                        } elseif (str_starts_with($filter, 'PublicationDate')) {
                            $this->addLimiter($this->formatDateLimiter($filter));
                        } else {
                            $this->addFilter($filter);
                        }
                    }
                    break;
                default:
                    if (property_exists($this, $key)) {
                        $this->$key = $values;
                    }
            }
        }
    }

    /**
     * Converts properties to a querystring to send to the EdsAPI
     *
     * @return string
     */
    public function convertToQueryString()
    {
        return http_build_query($this->convertToQueryStringParameterArray());
    }

    /**
     * Converts properties to a querystring to send to the EdsAPI
     *
     * @return string
     */
    public function convertToQueryStringParameterArray()
    {
        $qs = [];
        if (isset($this->query) && 0 < count($this->query)) {
            $formatQuery = function ($json) {
                $query = json_decode($json, true);
                $queryString = empty($query['bool'])
                    ? '' : ($query['bool'] . ',');
                if (!empty($query['field'])) {
                    $queryString .= $query['field'] . ':';
                }
                $queryString .= static::escapeSpecialCharacters($query['term']);
                return $queryString;
            };
            $qs['query-x'] = array_map($formatQuery, $this->query);
        }

        if (isset($this->facetFilters) && 0 < count($this->facetFilters)) {
            $filterId = 1;
            $qs['facetfilter'] = [];
            foreach ($this->facetFilters as $field => $values) {
                $values = array_map(fn ($value) => static::escapeSpecialCharacters($value), $values);
                $operator = $this->facetOperators[$field];
                if ('OR' == $operator) {
                    $valuesString = implode(',', array_map(fn ($value) => "{$field}:{$value}", $values));
                    $qs['facetfilter'][] = "{$filterId},{$valuesString}";
                    $filterId++;
                } else {
                    foreach ($values as $value) {
                        $qs['facetfilter'][] = "{$filterId},{$field}:{$value}";
                        $filterId++;
                    }
                }
            }
        }

        if (isset($this->limiters) && 0 < count($this->limiters)) {
            $qs['limiter'] = $this->limiters;
        }

        if (isset($this->actions) && 0 < count($this->actions)) {
            $qs['action-x'] = $this->actions;
        }

        if (isset($this->includeFacets)) {
            $qs['includefacets'] = $this->includeFacets;
        }

        if (isset($this->sort)) {
            $qs['sort'] = $this->sort;
        }

        if (isset($this->searchMode)) {
            $qs['searchmode'] = $this->searchMode;
        }

        if (isset($this->expanders) && 0 < count($this->expanders)) {
            $qs['expander'] = implode(',', $this->expanders);
        }

        if (isset($this->view)) {
            $qs['view'] = $this->view;
        }

        if (isset($this->resultsPerPage)) {
            $qs['resultsperpage'] = $this->resultsPerPage;
        }

        if (isset($this->pageNumber)) {
            $qs['pagenumber'] = $this->pageNumber;
        }

        $highlightVal = isset($this->highlight) && $this->highlight ? 'y' : 'n';
        $qs['highlight'] = $highlightVal;

        return $qs;
    }

    /**
     * Converts properties to a search request JSON document to send to the EdsAPI
     *
     * @return string
     */
    public function convertToSearchRequestJSON()
    {
        $json = new \stdClass();
        $json->SearchCriteria = new \stdClass();
        $json->RetrievalCriteria = new \stdClass();
        $json->Actions = null;
        if (isset($this->query) && 0 < count($this->query)) {
            $json->SearchCriteria->Queries = [];
            foreach ($this->query as $queryJson) {
                $query = json_decode($queryJson, true);
                $queryObj = new \stdClass();
                if (!empty($query['bool'])) {
                    $queryObj->BooleanOperator = $query['bool'];
                }
                if (!empty($query['field'])) {
                    $queryObj->FieldCode = $query['field'];
                }
                $queryObj->Term = $query['term'];
                $json->SearchCriteria->Queries[] = $queryObj;
            }
        }

        if (isset($this->facetFilters) && 0 < count($this->facetFilters)) {
            $json->SearchCriteria->FacetFilters = [];
            $id = 1;
            foreach ($this->facetFilters as $field => $values) {
                if ('OR' == $this->facetOperators[$field]) {
                    $filterObj = new \stdClass();
                    $filterObj->FilterId = $id++;
                    $filterObj->FacetValues = [];
                    foreach ($values as $value) {
                        $valueObj = new \stdClass();
                        $valueObj->Id = $field;
                        $valueObj->Value = $value;
                        $filterObj->FacetValues[] = $valueObj;
                    }
                    $json->SearchCriteria->FacetFilters[] = $filterObj;
                } else {
                    foreach ($values as $value) {
                        $filterObj = new \stdClass();
                        $filterObj->FilterId = $id++;
                        $valueObj = new \stdClass();
                        $valueObj->Id = $field;
                        $valueObj->Value = $value;
                        $filterObj->FacetValues = [$valueObj];
                        $json->SearchCriteria->FacetFilters[] = $filterObj;
                    }
                }
            }
        }

        if (isset($this->limiters) && 0 < count($this->limiters)) {
            $json->SearchCriteria->Limiters = [];
            foreach ($this->limiters as $field => $values) {
                // All EDS limiter values are combined as 'OR'.
                // There is no alternate 'AND' syntax as with filters.
                $limiterObj = new \stdClass();
                $limiterObj->Id = $field;
                $limiterObj->Values = $values;
                $json->SearchCriteria->Limiters[] = $limiterObj;
            }
        }

        if (isset($this->actions) && 0 < count($this->actions)) {
            $json->Actions = $this->actions;
        }

        $json->SearchCriteria->IncludeFacets = $this->includeFacets ?? 'y';

        if (isset($this->sort)) {
            $json->SearchCriteria->Sort = $this->sort;
        }

        if (isset($this->searchMode)) {
            $json->SearchCriteria->SearchMode = $this->searchMode;
        }

        if (isset($this->expanders) && 0 < count($this->expanders)) {
            $json->SearchCriteria->Expanders = $this->expanders;
        }

        if (isset($this->view)) {
            $json->RetrievalCriteria->View = $this->view;
        }

        if (isset($this->resultsPerPage)) {
            $json->RetrievalCriteria->ResultsPerPage = intval($this->resultsPerPage);
        }

        if (isset($this->pageNumber)) {
            $json->RetrievalCriteria->PageNumber = intval($this->pageNumber);
        }

        $highlightVal = isset($this->highlight) && $this->highlight ? 'y' : 'n';
        $json->RetrievalCriteria->Highlight = $highlightVal;

        return json_encode($json, JSON_PRETTY_PRINT);
    }

    /**
     * Determines whether or not a querystring parameter is indexed
     *
     * @param string $value parameter key to check
     *
     * @return bool
     */
    public static function isParameterIndexed($value)
    {
        // Indexed parameter names end with '-x'
        return str_ends_with($value, '-x');
    }

    /**
     * Get the querystring parameter name of an indexed parameter to send to the Eds
     * Api
     *
     * @param string $value Indexed parameter name
     *
     * @return string
     */
    public static function getIndexedParameterName($value)
    {
        // Indexed parameter names end with '-x'
        return substr($value, 0, -2);
    }

    /**
     * Add a new action
     *
     * @param string $action Action to add to the existing collection of actions
     *
     * @return void
     */
    public function addAction($action)
    {
        $this->actions[] = $action;
    }

    /**
     * Add a new query expression
     *
     * @param string $query Query expression to add
     *
     * @return void
     */
    public function addQuery($query)
    {
        $this->query[] = $query;
    }

    /**
     * Add a new limiter
     *
     * @param string $limiter Limiter to add
     *
     * @return void
     */
    public function addLimiter($limiter)
    {
        [$field, $value] = explode(':', $limiter);
        if (!array_key_exists($field, $this->limiters)) {
            $this->limiters[$field] = [];
        }
        $this->limiters[$field][] = $value;
    }

    /**
     * Add a new expander
     *
     * @param string $expander Expander to add
     *
     * @return void
     */
    public function addExpander($expander)
    {
        $this->expanders[] = $expander;
    }

    /**
     * Add a new facet filter
     *
     * @param string $facetFilter Facet Filter to add
     *
     * @return void
     */
    public function addfilter($facetFilter)
    {
        $filterComponents = explode(':', $facetFilter, 3);
        if (count($filterComponents) < 3) {
            [$field, $value] = $filterComponents;
            // Default to AND, since it's already the default in EDS.ini.
            $operator = 'AND';
        } else {
            [$field, $operator, $value] = $filterComponents;
        }
        if (str_starts_with($field, '~')) {
            $field = substr($field, 1);
            $operator = 'OR';
        }
        if (!array_key_exists($field, $this->facetFilters)) {
            $this->facetFilters[$field] = [];
        }
        $this->facetFilters[$field][] = $value;
        $this->facetOperators[$field] = $operator;
    }

    /**
     * Escape characters that may be present in the parameter syntax
     *
     * @param string $value The value to escape
     *
     * @return string       The value with special characters escaped
     */
    public static function escapeSpecialCharacters($value)
    {
        return addcslashes($value, ':,');
    }

    /**
     * Escape characters that may be present in the action parameter syntax
     *
     * @param string $value The value to escape
     *
     * @return string       The value with special characters escaped
     */
    public static function escapeSpecialCharactersForActions($value)
    {
        return addcslashes($value, ':,()');
    }

    /**
     * Magic getter
     *
     * @param string $property Property to retrieve
     *
     * @return mixed
     */
    public function __get($property)
    {
        if (property_exists($this, $property)) {
            return $this->$property;
        }
    }

    /**
     * Magic setter
     *
     * @param string $property Property to set
     * @param mixed  $value    Value to set
     *
     * @return SearchRequestModel
     */
    public function __set($property, $value)
    {
        if (property_exists($this, $property)) {
            $this->$property = $value;
        }

        return $this;
    }
}
