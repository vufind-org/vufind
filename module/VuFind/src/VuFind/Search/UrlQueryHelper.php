<?php

/**
 * Class to help build URLs and forms in the view based on search settings.
 *
 * PHP version 8
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

use VuFindSearch\Query\AbstractQuery;
use VuFindSearch\Query\Query;
use VuFindSearch\Query\QueryGroup;
use VuFindSearch\Query\WorkKeysQuery;

use function call_user_func;
use function count;
use function in_array;
use function is_array;
use function is_callable;

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
     * Note that the constructor is final here, because this class relies on
     * "new static()" to build instances, and we must ensure that child classes
     * have consistent constructor signatures.
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
    final public function __construct(
        array $urlParams,
        AbstractQuery $query,
        array $options = [],
        $regenerateQueryParams = true
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
        return $this->config['basicSearchParam'] ?? 'lookfor';
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
                            // We want the op and lookfor parameters to align
                            // with each other; let's backfill empty op values
                            // if there aren't enough in place already.
                            $expectedOps
                                = count($this->urlParams['lookfor' . $i]) - 1;
                            while (
                                count($this->urlParams['op' . $i] ?? [])
                                < $expectedOps
                            ) {
                                $this->urlParams['op' . $i][] = '';
                            }
                            $this->urlParams['op' . $i][] = $op;
                        }
                    }
                }
            }
        } elseif ($this->queryObject instanceof Query) {
            $search = $this->queryObject->getString();
            if (!empty($search)) {
                $this->urlParams[$this->getBasicSearchParam()] = $search;
            }
            $type = $this->queryObject->getHandler();
            if (!empty($type)) {
                $this->urlParams['type'] = $type;
            }
        } elseif ($this->queryObject instanceof WorkKeysQuery) {
            $this->urlParams['id'] = $this->queryObject->getId();
            $this->urlParams['search'] = 'versions';
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
        return $this->config['defaults'][$key] ?? null;
    }

    /**
     * Set the default value of a parameter, and add that parameter to the object
     * if it is not already defined.
     *
     * @param string $name          Name of parameter
     * @param string $value         Value of parameter
     * @param bool   $forceOverride Force an override of the existing value, even if
     * it was set in the incoming $urlParams in the constructor (defaults to false)
     *
     * @return UrlQueryHelper
     */
    public function setDefaultParameter($name, $value, $forceOverride = false)
    {
        // Add the new default to the configuration, and apply it to the query
        // if no existing value has already been set in this position (or if an
        // override has been forced).
        $this->config['defaults'][$name] = $value;
        if (!isset($this->urlParams[$name]) || $forceOverride) {
            $this->urlParams[$name] = $value;
        }
        return $this;
    }

    /**
     * Get an array of field names with configured defaults; this is a useful way
     * to identify custom query parameters added through setDefaultParameter().
     *
     * @return array
     */
    public function getParamsWithConfiguredDefaults()
    {
        return array_keys($this->config['defaults'] ?? []);
    }

    /**
     * Disable hidden filters
     *
     * @return UrlQueryHelper
     */
    public function disableHiddenFilters()
    {
        unset($this->urlParams['hiddenFilters']);
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
        $escape = $this->config['escape'] ?? true;
        return $this->getParams($escape);
    }

    /**
     * Replace a term in the search query (used for spelling replacement)
     *
     * @param string   $from       Search term to find
     * @param string   $to         Search term to insert
     * @param callable $normalizer Function to normalize text strings (null for
     * no normalization)
     *
     * @return UrlQueryHelper
     */
    public function replaceTerm($from, $to, $normalizer = null)
    {
        $query = clone $this->queryObject;
        $query->replaceTerm($from, $to, $normalizer);
        return new static($this->urlParams, $query, $this->config);
    }

    /**
     * Add a facet to the parameters.
     *
     * @param string $field    Facet field
     * @param string $value    Facet value
     * @param string $operator Facet type to add (AND, OR, NOT)
     *
     * @return UrlQueryHelper
     */
    public function addFacet($field, $value, $operator = 'AND')
    {
        // Facets are just a special case of filters:
        $prefix = ($operator == 'NOT') ? '-' : ($operator == 'OR' ? '~' : '');
        return $this->addFilter($prefix . $field . ':"' . $value . '"');
    }

    /**
     * Add a filter to the parameters.
     *
     * @param string $filter Filter to add
     *
     * @return UrlQueryHelper
     */
    public function addFilter($filter)
    {
        $params = $this->urlParams;

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
     * @return UrlQueryHelper
     */
    public function removeAllFilters()
    {
        $params = $this->urlParams;
        // Clear page:
        unset($params['filter']);

        return new static($params, $this->queryObject, $this->config, false);
    }

    /**
     * Reset default filter state.
     *
     * @return UrlQueryHelper
     */
    public function resetDefaultFilters()
    {
        $params = $this->urlParams;
        // Clear page:
        unset($params['dfApplied']);

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
        return '?' . static::buildQueryString($this->urlParams, $escape);
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
        if (
            !isset($this->config['parseFilterCallback'])
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
        if (
            !isset($this->config['getAliasesForFacetFieldCallback'])
            || !is_callable($this->config['getAliasesForFacetFieldCallback'])
        ) {
            return [$field];
        }
        return call_user_func(
            $this->config['getAliasesForFacetFieldCallback'],
            $field
        );
    }

    /**
     * Remove a facet from the parameters.
     *
     * @param string $field    Facet field
     * @param string $value    Facet value
     * @param string $operator Facet type to add (AND, OR, NOT)
     *
     * @return UrlQueryHelper
     */
    public function removeFacet($field, $value, $operator = 'AND')
    {
        $params = $this->urlParams;

        // Account for operators:
        if ($operator == 'NOT') {
            $field = '-' . $field;
        } elseif ($operator == 'OR') {
            $field = '~' . $field;
        }

        $fieldAliases = $this->getAliasesForFacetField($field);

        // Remove the filter:
        $newFilter = [];
        if (isset($params['filter']) && is_array($params['filter'])) {
            foreach ($params['filter'] as $current) {
                [$currentField, $currentValue]
                    = $this->parseFilter($current);
                if (
                    !in_array($currentField, $fieldAliases)
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

        return new static($params, $this->queryObject, $this->config, false);
    }

    /**
     * Remove a filter from the parameters.
     *
     * @param string $filter Filter to add
     *
     * @return UrlQueryHelper
     */
    public function removeFilter($filter)
    {
        // Treat this as a special case of removeFacet:
        [$field, $value] = $this->parseFilter($filter);
        return $this->removeFacet($field, $value);
    }

    /**
     * Return HTTP parameters to render a different page of results.
     *
     * @param string $p New page parameter (null for NO page parameter)
     *
     * @return UrlQueryHelper
     */
    public function setPage($p)
    {
        return $this->updateQueryString('page', $p, 1);
    }

    /**
     * Return HTTP parameters to render the current page with a different sort
     * parameter.
     *
     * @param string $s New sort parameter (null for NO sort parameter)
     *
     * @return UrlQueryHelper
     */
    public function setSort($s)
    {
        return $this->updateQueryString(
            'sort',
            $s,
            $this->getDefault('sort'),
            true
        );
    }

    /**
     * Return HTTP parameters to render the current page with a different search
     * handler.
     *
     * @param string $handler new Handler.
     *
     * @return UrlQueryHelper
     */
    public function setHandler($handler)
    {
        $query = clone $this->queryObject;
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
     * with the \Laminas\View\Helper\AbstractHelper interface.
     *
     * @param string $v New sort parameter (null for NO view parameter)
     *
     * @return UrlQueryHelper
     */
    public function setViewParam($v)
    {
        // Because of the way view settings are stored in the session, we always
        // want an explicit value here (hence null rather than default view in
        // third parameter below):
        return $this->updateQueryString('view', $v, null);
    }

    /**
     * Return HTTP parameters to render the current page with a different limit
     * parameter.
     *
     * @param string $l New limit parameter (null for NO limit parameter)
     *
     * @return UrlQueryHelper
     */
    public function setLimit($l)
    {
        return $this->updateQueryString(
            'limit',
            $l,
            $this->getDefault('limit'),
            true
        );
    }

    /**
     * Return HTTP parameters to render the current page with a different jumpto
     * parameter.
     *
     * @param null|false|int $jumpto If results page is skipped when a search has only one hit
     *
     * @return UrlQueryHelper
     */
    public function setJumpto(null|false|int $jumpto): UrlQueryHelper
    {
        return $this->updateQueryString(
            'jumpto',
            $jumpto
        );
    }

    /**
     * Return HTTP parameters to render the current page with a different set
     * of search terms.
     *
     * @param string $lookfor New search terms
     *
     * @return UrlQueryHelper
     */
    public function setSearchTerms($lookfor)
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
                            htmlspecialchars($paramValue2 ?? '') . '">';
                    }
                }
            } else {
                if (!$this->filtered($paramName, $paramValue, $filter)) {
                    $retVal .= '<input type="hidden" name="' .
                        htmlspecialchars($paramName) . '" value="' .
                        htmlspecialchars($paramValue ?? '') . '">';
                }
            }
        }
        return $retVal;
    }

    /**
     * Turn an array into a properly URL-encoded query string. This is
     * equivalent to the built-in PHP http_build_query function, but it handles
     * arrays in a more compact way and ensures that ampersands don't get
     * messed up based on server-specific settings.
     *
     * @param array $a      Array of parameters to turn into a GET string
     * @param bool  $escape Should we escape the string for use in the view?
     *
     * @return string
     */
    public static function buildQueryString($a, $escape = true)
    {
        $parts = [];
        foreach ($a as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $current) {
                    $parts[] = urlencode($key . '[]') . '=' . urlencode($current ?? '');
                }
            } else {
                $parts[] = urlencode($key) . '=' . urlencode($value ?? '');
            }
        }
        $retVal = implode('&', $parts);
        return $escape ? htmlspecialchars($retVal) : $retVal;
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
        return isset($filter[$field]) && preg_match($filter[$field], $value);
    }

    /**
     * Generic case of parameter rebuilding.
     *
     * @param string $field     Field to update
     * @param string $value     Value to use (null to skip field entirely)
     * @param string $default   Default value (skip field if $value matches; null
     *                          for no default).
     * @param bool   $clearPage Should we clear the page number, if any?
     *
     * @return UrlQueryHelper
     */
    protected function updateQueryString(
        $field,
        $value,
        $default = null,
        $clearPage = false
    ) {
        $params = $this->urlParams;
        if (null === $value || $value === $default) {
            unset($params[$field]);
        } else {
            $params[$field] = $value;
        }
        if ($clearPage && isset($params['page'])) {
            unset($params['page']);
        }
        return new static($params, $this->queryObject, $this->config, false);
    }
}
