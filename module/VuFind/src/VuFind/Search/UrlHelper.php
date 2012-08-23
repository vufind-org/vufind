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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace VuFind\Search;

/**
 * Class to help build URLs and forms in the view based on search settings.
 *
 * @category VuFind2
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class UrlHelper
{
    protected $results;
    protected $basicSearchParam = 'lookfor';
    protected $defaultParams = array();

    /**
     * Constructor
     *
     * @param \VuFind\Search\Base\Results $results VuFind search results object.
     */
    public function __construct($results)
    {
        $this->results = $results;
    }
    
    /**
     * Set the name of the parameter used for basic search terms.
     *
     * @param string $param Parameter name to set.
     *
     * @return void
     */
    public function setBasicSearchParam($param)
    {
        $this->basicSearchParam = $param;
    }

    /**
     * Add a parameter to the object.
     *
     * @param string $name  Name of parameter
     * @param string $value Value of parameter
     *
     * @return void
     */
    public function setDefaultParameter($name, $value)
    {
        $this->defaultParams[$name] = $value;
    }

    /**
     * Get an array of URL parameters.
     *
     * @return array
     */
    protected function getParamArray()
    {
        $params = $this->defaultParams;

        // Build all the URL parameters based on search object settings:
        if ($this->results->getSearchType() == 'advanced') {
            $terms = $this->results->getSearchTerms();
            if (isset($terms[0]['join'])) {
                $params['join'] = $terms[0]['join'];
            }
            for ($i = 0; $i < count($terms); $i++) {
                if (isset($terms[$i]['group'])) {
                    $params['bool' . $i] = array($terms[$i]['group'][0]['bool']);
                    for ($j = 0; $j < count($terms[$i]['group']); $j++) {
                        if (!isset($params['lookfor' . $i])) {
                            $params['lookfor' . $i] = array();
                        }
                        if (!isset($params['type' . $i])) {
                            $params['type' . $i] = array();
                        }
                        $params['lookfor'.$i][] = $terms[$i]['group'][$j]['lookfor'];
                        $params['type' . $i][] = $terms[$i]['group'][$j]['field'];
                    }
                }
            }
        } else {
            $search = $this->results->getDisplayQuery();
            if (!empty($search)) {
                $params[$this->basicSearchParam] = $search;
            }
            $type = $this->results->getSearchHandler();
            if (!empty($type)) {
                $params['type'] = $type;
            }
        }
        $sort = $this->results->getSort();
        if (!is_null($sort) && $sort != $this->results->getDefaultSort()) {
            $params['sort'] = $sort;
        }
        $limit = $this->results->getLimit();
        if (!is_null($limit) && $limit != $this->results->getDefaultLimit()) {
            $params['limit'] = $limit;
        }
        $view = $this->results->getView();
        if (!is_null($view) && $view != $this->results->getDefaultView()) {
            $params['view'] = $view;
        }
        if ($this->results->getPage() != 1) {
            $params['page'] = $this->results->getPage();
        }
        $filters = $this->results->getFilters();
        if (!empty($filters)) {
            $params['filter'] = array();
            foreach ($filters as $field => $values) {
                foreach ($values as $current) {
                    $params['filter'][] = $field . ':"' . $current . '"';
                }
            }
        }
        $shards = $this->results->getSelectedShards();
        if (!empty($shards)) {
            sort($shards);
            $key = implode(':::', $shards);
            $defaultShards = $this->results->getDefaultSelectedShards();
            sort($defaultShards);
            if (implode(':::', $shards) != implode(':::', $defaultShards)) {
                $params['shard'] = $shards;
            }
        }

        return $params;
    }

    /**
     * Replace a term in the search query (used for spelling replacement)
     *
     * @param string $from Search term to find
     * @param string $to   Search term to insert
     *
     * @return string
     */
    public function replaceTerm($from, $to)
    {
        $newResults = clone($this->results);
        $newResults->replaceSearchTerm($from, $to);
        $myClass = get_class($this);
        $helper = new $myClass($newResults);
        return $helper->getParams();
    }

    /**
     * Add a facet to the parameters.
     *
     * @param string $field Facet field
     * @param string $value Facet value
     *
     * @return string
     */
    public function addFacet($field, $value)
    {
        // Facets are just a special case of filters:
        return $this->addFilter($field . ':"' . $value . '"');
    }

    /**
     * Add a filter to the parameters.
     *
     * @param string $filter Filter to add
     *
     * @return string
     */
    public function addFilter($filter)
    {
        $params = $this->getParamArray();

        // Add the filter:
        if (!isset($params['filter'])) {
            $params['filter'] = array();
        }
        $params['filter'][] = $filter;

        // Clear page:
        unset($params['page']);

        return '?' . $this->buildQueryString($params);
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
     * @param string $field  Facet field
     * @param string $value  Facet value
     * @param bool   $escape Should we escape the string for use in the view?
     *
     * @return string
     */
    public function removeFacet($field, $value, $escape = true)
    {
        $params = $this->getParamArray();

        // Remove the filter:
        $newFilter = array();
        if (isset($params['filter']) && is_array($params['filter'])) {
            foreach ($params['filter'] as $current) {
                list($currentField, $currentValue)
                    = $this->results->parseFilter($current);
                if ($currentField != $field || $currentValue != $value) {
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

        return '?' . $this->buildQueryString($params, $escape);
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
        list($field, $value) = $this->results->parseFilter($filter);
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
            'sort', $s, $this->results->getDefaultSort(), $escape
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
            'type', $handler, $this->results->getDefaultHandler(), $escape
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
            'limit', $l, $this->results->getDefaultLimit(), $escape
        );
    }

    /**
     * Turn the current GET parameters into a set of hidden form fields.
     *
     * @param array $filter Array of parameters to exclude -- key = field name,
     * value = regular expression to exclude.
     *
     * @return string
     */
    public function asHiddenFields($filter = array())
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
     * @param string $field   Field to update
     * @param string $value   Value to use (null to skip field entirely)
     * @param string $default Default value (skip field if $value matches; null
     *                        for no default).
     * @param bool   $escape  Should we escape the string for use in the view?
     *
     * @return string
     */
    protected function updateQueryString($field, $value, $default = null,
        $escape = true
    ) {
        $params = $this->getParamArray();
        if (is_null($value) || $value == $default) {
            unset($params[$field]);
        } else {
            $params[$field] = $value;
        }
        return '?' . $this->buildQueryString($params, $escape);
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
        $parts = array();
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