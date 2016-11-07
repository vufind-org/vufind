<?php
/**
 * Class to help build URLs and forms in the view based on search settings.
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
namespace VuFind\Search;
use VuFind\Search\Base\Options;
use VuFind\Search\Base\Params;
use VuFindSearch\Query\AbstractQuery;
use VuFindSearch\Query\Query;
use VuFindSearch\Query\QueryGroup;

/**
 * Class to help build URLs and forms in the view based on search settings.
 *
 * @category VuFind
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class UrlQueryHelper
{
    /**
     * Configuration for this helper.
     *
     * @var array
     */
    protected $config;

    /**
     * URL query parameters
     *
     * @var array
     */
    protected $query = [];

    /**
     * Current query object
     *
     * @var AbstractQuery
     */
    protected $queryObject;

    /**
     * The raw parameters object that initiated this query.
     *
     * @var Params
     */
    protected $rawParams;

    /**
     * URL search param
     *
     * @var string
     */
    protected $basicSearchParam = 'lookfor';

    /**
     * Should we HTML-escape the parameters by default when rendering as a string?
     *
     * @var bool
     */
    protected $escape = true;

    /**
     * Constructor
     *
     * @param Params        $rawParams VuFind search params object.
     * @param array         $overrides Array of override parameters to load instead
     * of defaults based on $rawParams.
     * @param AbstractQuery $query     Optional query object to override default
     * query settings found in $rawParams.
     * @param array         $options   Configuration options for the object.
     */
    public function __construct($rawParams, array $overrides = null,
        AbstractQuery $query = null, array $options = []
    ) {
        $this->initConfig($options);
        $this->rawParams = $rawParams;
        if (null !== $overrides) {
            $this->query = $overrides;
        } else {
            $this->loadParams($rawParams);
        }
        if (null !== $query) {
            $this->loadQuery($query);
        }
    }

    /**
     * Set up the internal configuration based on an options array.
     *
     * @param array $options Configuration options for the object.
     *
     * @return void
     */
    protected function initConfig(array $options)
    {
        $this->config = $options;
        if (isset($options['basicSearchParam'])) {
            $this->basicSearchParam = $options['basicSearchParam'];
        }
    }

    /**
     * Reset search-related parameters in the internal array.
     *
     * @return void
     */
    protected function clearSearchQueryParams()
    {
        unset($this->query[$this->basicSearchParam]);
        unset($this->query['join']);
        unset($this->query['type']);
        $searchParams = ['bool', 'lookfor', 'type', 'op'];
        foreach (array_keys($this->query) as $key) {
            if (preg_match('/(' . implode('|', $searchParams) . ')[0-9]+/', $key)) {
                unset($this->query[$key]);
            }
        }
    }

    /**
     * Adjust the internal query array based on a query object.
     *
     * @param AbstractQuery $query Query object
     *
     * @return void
     */
    protected function loadQuery(AbstractQuery $query)
    {
        $this->queryObject = $query;
        $this->clearSearchQueryParams();
        if (!empty($this->config['suppressQuery'])) {
            return;
        }
        if ($query instanceof QueryGroup) {
            $this->query['join'] = $query->getOperator();
            foreach ($query->getQueries() as $i => $current) {
                if ($current instanceof QueryGroup) {
                    $operator = $current->isNegated()
                        ? 'NOT' : $current->getOperator();
                    $this->query['bool' . $i] = [$operator];
                    foreach ($current->getQueries() as $inner) {
                        if (!isset($this->query['lookfor' . $i])) {
                            $this->query['lookfor' . $i] = [];
                        }
                        if (!isset($this->query['type' . $i])) {
                            $this->query['type' . $i] = [];
                        }
                        $this->query['lookfor' . $i][] = $inner->getString();
                        $this->query['type' . $i][] = $inner->getHandler();
                        if (null !== ($op = $inner->getOperator())) {
                            $this->query['op' . $i][] = $op;
                        }
                    }
                }
            }
        } else if ($query instanceof Query) {
            $search = $query->getString();
            if (!empty($search)) {
                $this->query[$this->basicSearchParam] = $search;
            }
            $type = $query->getHandler();
            if (!empty($type)) {
                $this->query['type'] = $type;
            }
        }
    }

    /**
     * Set up defaults based on a parameters object.
     *
     * @param Params $params VuFind search params object.
     *
     * @return void
     */
    protected function loadDefaults(Params $params)
    {
        $options = $params->getOptions();
        $this->config['defaults'] = [
            'handler' => $options->getDefaultHandler(),
            'limit' => $options->getDefaultLimit(),
            'selectedShards' => $options->getDefaultSelectedShards(),
            'sort' => $params->getDefaultSort(),
            'view' => $options->getDefaultView(),
            
        ];
    }

    /**
     * Look up a default value in the internal configuration array.
     *
     * @param string $key Name of default to load
     *
     * @return mixed
     */
    protected function getDefault($key)
    {
        return isset($this->config['defaults'][$key])
            ? $this->config['defaults'][$key] : null;
    }

    /**
     * Set up the internal query parameter array based on a Params object.
     *
     * @param Params $params VuFind search params object.
     *
     * @return void
     */
    protected function loadParams(Params $params)
    {
        // Build all the URL parameters based on search object settings:
        $this->loadQuery($params->getQuery());
        $this->loadDefaults($params);
        $sort = $params->getSort();
        if (null !== $sort && $sort != $this->getDefault('sort')) {
            $this->query['sort'] = $sort;
        }
        $limit = $params->getLimit();
        if (null !== $limit && $limit != $this->getDefault('limit')) {
            $this->query['limit'] = $limit;
        }
        $view = $params->getView();
        if (null !== $view && $view != $this->getDefault('view')) {
            $this->query['view'] = $view;
        }
        if ($params->getPage() != 1) {
            $this->query['page'] = $params->getPage();
        }
        $filters = $params->getFilters();
        if (!empty($filters)) {
            $this->query['filter'] = [];
            foreach ($filters as $field => $values) {
                foreach ($values as $current) {
                    $this->query['filter'][] = $field . ':"' . $current . '"';
                }
            }
        }
        $hiddenFilters = $params->getHiddenFilters();
        if (!empty($hiddenFilters)) {
            foreach ($hiddenFilters as $field => $values) {
                foreach ($values as $current) {
                    $this->query['hiddenFilters'][] = $field . ':"' . $current . '"';
                }
            }
        }
        $shards = $params->getSelectedShards();
        if (!empty($shards)) {
            sort($shards);
            $defaultShards = $this->getDefault('selectedShards');
            sort($defaultShards);
            if (implode(':::', $shards) != implode(':::', $defaultShards)) {
                $this->query['shard'] = $shards;
            }
        }
        if ($params->hasDefaultsApplied()) {
            $this->query['dfApplied'] = 1;
        }
    }

    /**
     * Add a parameter to the object.
     *
     * @param string $name  Name of parameter
     * @param string $value Value of parameter
     *
     * @return UrlQueryHelper
     */
    public function setDefaultParameter($name, $value)
    {
        $this->query[$name] = $value;
        return $this;
    }

    /**
     * Control query suppression
     *
     * @param bool $suppress Should we suppress queries?
     *
     * @return UrlQueryHelper
     */
    public function setSuppressQuery($suppress)
    {
        $this->clearSearchQueryParams();
        $this->config['suppressQuery'] = $suppress;
        return $this;
    }

    /**
     * Is query suppressed?
     *
     * @return bool
     */
    public function isQuerySuppressed()
    {
        return isset($this->config['suppressQuery'])
            ? (bool)$this->config['suppressQuery'] : false;
    }

    /**
     * Get an array of URL parameters.
     *
     * @return array
     */
    public function getParamArray()
    {
        return $this->query;
    }

    /**
     * Magic method: behavior when this object is treated as a string.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->getParams($this->escape);
    }

    /**
     * Replace a term in the search query (used for spelling replacement)
     *
     * @param string $from Search term to find
     * @param string $to   Search term to insert
     *
     * @return UrlQueryHelper
     */
    public function replaceTerm($from, $to)
    {
        $query = clone($this->queryObject);
        $query->replaceTerm($from, $to);
        return new static($this->rawParams, $this->query, $query, $this->config);
    }

    /**
     * Add a facet to the parameters.
     *
     * @param string $field      Facet field
     * @param string $value      Facet value
     * @param string $operator   Facet type to add (AND, OR, NOT)
     * @param array  $paramArray Optional array of parameters to use instead of
     * getParamArray()
     *
     * @return UrlQueryHelper
     */
    public function addFacet($field, $value, $operator = 'AND', $paramArray = null)
    {
        // Facets are just a special case of filters:
        $prefix = ($operator == 'NOT') ? '-' : ($operator == 'OR' ? '~' : '');
        return $this->addFilter($prefix . $field . ':"' . $value . '"', $paramArray);
    }

    /**
     * Add a filter to the parameters.
     *
     * @param string $filter     Filter to add
     * @param array  $paramArray Optional array of parameters to use instead of
     * getParamArray()
     *
     * @return UrlQueryHelper
     */
    public function addFilter($filter, $paramArray = null)
    {
        $params = (null === $paramArray) ? $this->query : $paramArray;

        // Add the filter:
        if (!isset($params['filter'])) {
            $params['filter'] = [];
        }
        $params['filter'][] = $filter;

        // Clear page:
        unset($params['page']);

        return new static(
            $this->rawParams, $params, $this->queryObject, $this->config
        );
    }

    /**
     * Remove all filters.
     *
     * @return string
     */
    public function removeAllFilters()
    {
        $params = $this->query;
        // Clear page:
        unset($params['filter']);

        return new static(
            $this->rawParams, $params, $this->queryObject, $this->config
        );
    }

    /**
     * Get the current search parameters as a GET query.
     *
     * @param bool $escape Should we escape the string for use in the view?
     *
     * @return string
     */
    public function getParams($escape = true)
    {
        return '?' . $this->buildQueryString($this->getParamArray(), $escape);
    }

    /**
     * Remove a facet from the parameters.
     *
     * @param string $field      Facet field
     * @param string $value      Facet value
     * @param bool   $escape     Should we escape the string for use in the view?
     * @param string $operator   Facet type to add (AND, OR, NOT)
     * @param array  $paramArray Optional array of parameters to use instead of
     * getParamArray()
     *
     * @return string
     */
    public function removeFacet($field, $value, $escape = true, $operator = 'AND',
        $paramArray = null
    ) {
        $params = (null === $paramArray) ? $this->query : $paramArray;

        // Account for operators:
        if ($operator == 'NOT') {
            $field = '-' . $field;
        } else if ($operator == 'OR') {
            $field = '~' . $field;
        }

        $fieldAliases = $this->rawParams->getAliasesForFacetField($field);

        // Remove the filter:
        $newFilter = [];
        if (isset($params['filter']) && is_array($params['filter'])) {
            foreach ($params['filter'] as $current) {
                list($currentField, $currentValue)
                    = $this->rawParams->parseFilter($current);
                if (!in_array($currentField, $fieldAliases)
                    || $currentValue != $value
                ) {
                    $newFilter[] = $current;
                }
            }
        }
        if (empty($newFilter)) {
            unset($params['filter']);
        } else {
            $params['filter'] = $newFilter;
        }

        // Clear page:
        unset($params['page']);

        $this->escape = $escape;
        return new static(
            $this->rawParams, $params, $this->queryObject, $this->config
        );
    }

    /**
     * Remove a filter from the parameters.
     *
     * @param string $filter Filter to add
     * @param bool   $escape Should we escape the string for use in the view?
     *
     * @return string
     */
    public function removeFilter($filter, $escape = true)
    {
        // Treat this as a special case of removeFacet:
        list($field, $value) = $this->rawParams->parseFilter($filter);
        return $this->removeFacet($field, $value, $escape);
    }

    /**
     * Return HTTP parameters to render a different page of results.
     *
     * @param string $p      New page parameter (null for NO page parameter)
     * @param bool   $escape Should we escape the string for use in the view?
     *
     * @return string
     */
    public function setPage($p, $escape = true)
    {
        return $this->updateQueryString('page', $p, 1, $escape);
    }

    /**
     * Return HTTP parameters to render the current page with a different sort
     * parameter.
     *
     * @param string $s      New sort parameter (null for NO sort parameter)
     * @param bool   $escape Should we escape the string for use in the view?
     *
     * @return string
     */
    public function setSort($s, $escape = true)
    {
        return $this->updateQueryString(
            'sort', $s, $this->getDefault('sort'), $escape, true
        );
    }

    /**
     * Return HTTP parameters to render the current page with a different search
     * handler.
     *
     * @param string $handler new Handler.
     * @param bool   $escape  Should we escape the string for use in the view?
     *
     * @return string
     */
    public function setHandler($handler, $escape = true)
    {
        return $this->updateQueryString(
            'type', $handler, $this->getDefault('handler'),
            $escape
        );
    }

    /**
     * Return HTTP parameters to render the current page with a different view
     * parameter.
     *
     * Note: This is called setViewParam rather than setView to avoid confusion
     * with the \Zend\View\Helper\AbstractHelper interface.
     *
     * @param string $v      New sort parameter (null for NO view parameter)
     * @param bool   $escape Should we escape the string for use in the view?
     *
     * @return string
     */
    public function setViewParam($v, $escape = true)
    {
        // Because of the way view settings are stored in the session, we always
        // want an explicit value here (hence null rather than default view in
        // third parameter below):
        return $this->updateQueryString('view', $v, null, $escape);
    }

    /**
     * Return HTTP parameters to render the current page with a different limit
     * parameter.
     *
     * @param string $l      New limit parameter (null for NO limit parameter)
     * @param bool   $escape Should we escape the string for use in the view?
     *
     * @return string
     */
    public function setLimit($l, $escape = true)
    {
        return $this->updateQueryString(
            'limit', $l, $this->getDefault('limit'), $escape, true
        );
    }

    /**
     * Return HTTP parameters to render the current page with a different set
     * of search terms.
     *
     * @param string $lookfor New search terms
     * @param bool   $escape  Should we escape the string for use in the view?
     *
     * @return string
     */
    public function setSearchTerms($lookfor, $escape = true)
    {
        $query = new Query($lookfor);
        return new static($this->rawParams, $this->query, $query, $this->config);
    }

    /**
     * Turn the current GET parameters into a set of hidden form fields.
     *
     * @param array $filter Array of parameters to exclude -- key = field name,
     * value = regular expression to exclude.
     *
     * @return string
     */
    public function asHiddenFields($filter = [])
    {
        $retVal = '';
        foreach ($this->getParamArray() as $paramName => $paramValue) {
            if (is_array($paramValue)) {
                foreach ($paramValue as $paramValue2) {
                    if (!$this->filtered($paramName, $paramValue2, $filter)) {
                        $retVal .= '<input type="hidden" name="' .
                            htmlspecialchars($paramName) . '[]" value="' .
                            htmlspecialchars($paramValue2) . '" />';
                    }
                }
            } else {
                if (!$this->filtered($paramName, $paramValue, $filter)) {
                    $retVal .= '<input type="hidden" name="' .
                        htmlspecialchars($paramName) . '" value="' .
                        htmlspecialchars($paramValue) . '" />';
                }
            }
        }
        return $retVal;
    }

    /**
     * Support method for asHiddenFields -- are the provided field and value
     * excluded by the provided filter?
     *
     * @param string $field  Field to check
     * @param string $value  Regular expression to check
     * @param array  $filter Filter provided to asHiddenFields() above
     *
     * @return bool
     */
    protected function filtered($field, $value, $filter)
    {
        return (isset($filter[$field]) && preg_match($filter[$field], $value));
    }

    /**
     * Generic case of parameter rebuilding.
     *
     * @param string $field     Field to update
     * @param string $value     Value to use (null to skip field entirely)
     * @param string $default   Default value (skip field if $value matches; null
     *                          for no default).
     * @param bool   $escape    Should we escape the string for use in the view?
     * @param bool   $clearPage Should we clear the page number, if any?
     *
     * @return string
     */
    protected function updateQueryString($field, $value, $default = null,
        $escape = true, $clearPage = false
    ) {
        $params = $this->getParamArray();
        if (null !== $value || $value == $default) {
            unset($params[$field]);
        } else {
            $params[$field] = $value;
        }
        if ($clearPage && isset($params['page'])) {
            unset($params['page']);
        }
        $this->escape = $escape;
        return new static(
            $this->rawParams, $params, $this->queryObject, $this->config
        );
    }

    /**
     * Turn an array into a properly URL-encoded query string.  This is
     * equivalent to the built-in PHP http_build_query function, but it handles
     * arrays in a more compact way and ensures that ampersands don't get
     * messed up based on server-specific settings.
     *
     * @param array $a      Array of parameters to turn into a GET string
     * @param bool  $escape Should we escape the string for use in the view?
     *
     * @return string
     */
    protected function buildQueryString($a, $escape = true)
    {
        $parts = [];
        foreach ($a as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $current) {
                    $parts[] = urlencode($key . '[]') . '=' . urlencode($current);
                }
            } else {
                $parts[] = urlencode($key) . '=' . urlencode($value);
            }
        }
        $retVal = implode('&', $parts);
        return $escape ? htmlspecialchars($retVal) : $retVal;
    }
}
