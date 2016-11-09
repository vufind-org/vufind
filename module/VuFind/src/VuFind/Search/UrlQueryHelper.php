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
    protected $urlParams = [];

    /**
     * Current query object
     *
     * @var AbstractQuery
     */
    protected $queryObject;

    /**
     * Constructor
     *
     * @param array         $urlParams             Array of URL query parameters.
     * @param AbstractQuery $query                 Query object to use to update
     * URL query.
     * @param array         $options               Configuration options for the
     * object.
     * @param bool          $regenerateQueryParams Should we add parameters based
     * on the contents of $query to $urlParams (true) or are they already there
     * (false)?
     */
    public function __construct(array $urlParams, AbstractQuery $query,
        array $options = [], $regenerateQueryParams = true
    ) {
        $this->config = $options;
        $this->urlParams = $urlParams;
        $this->queryObject = $query;
        if ($regenerateQueryParams) {
            $this->regenerateSearchQueryParams();
        }
    }

    /**
     * Get the name of the basic search param.
     *
     * @return string
     */
    protected function getBasicSearchParam()
    {
        return isset($this->config['basicSearchParam'])
            ? $this->config['basicSearchParam'] : 'lookfor';
    }

    /**
     * Reset search-related parameters in the internal array.
     *
     * @return void
     */
    protected function clearSearchQueryParams()
    {
        unset($this->urlParams[$this->getBasicSearchParam()]);
        unset($this->urlParams['join']);
        unset($this->urlParams['type']);
        $searchParams = ['bool', 'lookfor', 'type', 'op'];
        foreach (array_keys($this->urlParams) as $key) {
            if (preg_match('/(' . implode('|', $searchParams) . ')[0-9]+/', $key)) {
                unset($this->urlParams[$key]);
            }
        }
    }

    /**
     * Adjust the internal query array based on the query object.
     *
     * @return void
     */
    protected function regenerateSearchQueryParams()
    {
        $this->clearSearchQueryParams();
        if ($this->isQuerySuppressed()) {
            return;
        }
        if ($this->queryObject instanceof QueryGroup) {
            $this->urlParams['join'] = $this->queryObject->getOperator();
            foreach ($this->queryObject->getQueries() as $i => $current) {
                if ($current instanceof QueryGroup) {
                    $operator = $current->isNegated()
                        ? 'NOT' : $current->getOperator();
                    $this->urlParams['bool' . $i] = [$operator];
                    foreach ($current->getQueries() as $inner) {
                        if (!isset($this->urlParams['lookfor' . $i])) {
                            $this->urlParams['lookfor' . $i] = [];
                        }
                        if (!isset($this->urlParams['type' . $i])) {
                            $this->urlParams['type' . $i] = [];
                        }
                        $this->urlParams['lookfor' . $i][] = $inner->getString();
                        $this->urlParams['type' . $i][] = $inner->getHandler();
                        if (null !== ($op = $inner->getOperator())) {
                            $this->urlParams['op' . $i][] = $op;
                        }
                    }
                }
            }
        } else if ($this->queryObject instanceof Query) {
            $search = $this->queryObject->getString();
            if (!empty($search)) {
                $this->urlParams[$this->getBasicSearchParam()] = $search;
            }
            $type = $this->queryObject->getHandler();
            if (!empty($type)) {
                $this->urlParams['type'] = $type;
            }
        }
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
     * Add a parameter to the object.
     *
     * @param string $name  Name of parameter
     * @param string $value Value of parameter
     *
     * @return UrlQueryHelper
     */
    public function setDefaultParameter($name, $value)
    {
        $this->urlParams[$name] = $value;
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
        $this->config['suppressQuery'] = $suppress;
        $this->regenerateSearchQueryParams();
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
        return $this->urlParams;
    }

    /**
     * Magic method: behavior when this object is treated as a string.
     *
     * @return string
     */
    public function __toString()
    {
        $escape = isset($this->config['escape']) ? $this->config['escape'] : true;
        return $this->getParams($escape);
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
        return new static($this->urlParams, $query, $this->config);
    }

    /**
     * Add a facet to the parameters.
     *
     * @param string $field      Facet field
     * @param string $value      Facet value
     * @param string $operator   Facet type to add (AND, OR, NOT)
     * @param array  $paramArray Optional array of parameters to use instead of
     * internally stored values.
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
     * internally stored values.
     *
     * @return UrlQueryHelper
     */
    public function addFilter($filter, $paramArray = null)
    {
        $params = (null === $paramArray) ? $this->urlParams : $paramArray;

        // Add the filter:
        if (!isset($params['filter'])) {
            $params['filter'] = [];
        }
        $params['filter'][] = $filter;

        // Clear page:
        unset($params['page']);

        return new static($params, $this->queryObject, $this->config, false);
    }

    /**
     * Remove all filters.
     *
     * @return string
     */
    public function removeAllFilters()
    {
        $params = $this->urlParams;
        // Clear page:
        unset($params['filter']);

        return new static($params, $this->queryObject, $this->config, false);
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
        return '?' . $this->buildQueryString($this->urlParams, $escape);
    }

    /**
     * Parse apart the field and value from a URL filter string.
     *
     * @param string $filter A filter string from url : "field:value"
     *
     * @return array         Array with elements 0 = field, 1 = value.
     */
    protected function parseFilter($filter)
    {
        // Simplistic explode/trim behavior if no callback is provided:
        if (!isset($this->config['parseFilterCallback'])
            || !is_callable($this->config['parseFilterCallback'])
        ) {
            $parts = explode(':', $filter, 2);
            $parts[1] = trim($parts[1], '"');
            return $parts;
        }
        return call_user_func($this->config['parseFilterCallback'], $filter);
    }

    /**
     * Given a facet field, return an array containing all aliases of that
     * field.
     *
     * @param string $field Field to look up
     *
     * @return array
     */
    protected function getAliasesForFacetField($field)
    {
        // If no callback is provided, aliases are unsupported:
        if (!isset($this->config['getAliasesForFacetFieldCallback'])
            || !is_callable($this->config['getAliasesForFacetFieldCallback'])
        ) {
            return [$field];
        }
        return call_user_func(
            $this->config['getAliasesForFacetFieldCallback'], $field
        );
    }

    /**
     * Remove a facet from the parameters.
     *
     * @param string $field      Facet field
     * @param string $value      Facet value
     * @param bool   $escape     Should we escape the string for use in the view?
     * @param string $operator   Facet type to add (AND, OR, NOT)
     * @param array  $paramArray Optional array of parameters to use instead of
     * internally stored values.
     *
     * @return string
     */
    public function removeFacet($field, $value, $escape = true, $operator = 'AND',
        $paramArray = null
    ) {
        $params = (null === $paramArray) ? $this->urlParams : $paramArray;

        // Account for operators:
        if ($operator == 'NOT') {
            $field = '-' . $field;
        } else if ($operator == 'OR') {
            $field = '~' . $field;
        }

        $fieldAliases = $this->getAliasesForFacetField($field);

        // Remove the filter:
        $newFilter = [];
        if (isset($params['filter']) && is_array($params['filter'])) {
            foreach ($params['filter'] as $current) {
                list($currentField, $currentValue)
                    = $this->parseFilter($current);
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

        $config = $this->config;
        $config['escape'] = $escape;
        return new static($params, $this->queryObject, $config, false);
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
        list($field, $value) = $this->parseFilter($filter);
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
        $query = clone($this->queryObject);
        // We can only set the handler on basic queries:
        if ($query instanceof Query) {
            $query->setHandler($handler);
        }
        return new static($this->urlParams, $query, $this->config);
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
        return new static($this->urlParams, $query, $this->config);
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
        foreach ($this->urlParams as $paramName => $paramValue) {
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
        $params = $this->urlParams;
        if (null === $value || $value == $default) {
            unset($params[$field]);
        } else {
            $params[$field] = $value;
        }
        if ($clearPage && isset($params['page'])) {
            unset($params['page']);
        }
        $config = $this->config;
        $config['escape'] = $escape;
        return new static($params, $this->queryObject, $config, false);
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
