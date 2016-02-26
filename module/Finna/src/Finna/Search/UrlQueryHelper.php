<?php
/**
 * Class to help build URLs and forms in the view based on search settings.
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
 * @package  Search
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace Finna\Search;

/**
 * Class to help build URLs and forms in the view based on search settings.
 *
 * @category VuFind
 * @package  Search
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class UrlQueryHelper extends \VuFind\Search\UrlQueryHelper
{
    /**
     * Copy constructor
     *
     * @return void
     */
    public function __clone()
    {
        $this->params = clone($this->params);
    }

    /**
     * Expose parent method since we need to use it from SearchTabs.
     *
     * @param array $a      Array of parameters to turn into a GET string
     * @param bool  $escape Should we escape the string for use in the view?
     *
     * @return string
     */
    public function buildQueryString($a, $escape = true)
    {
        return parent::buildQueryString($a, $escape);
    }

    /**
     * Remove any instance of the facet from the parameters and add a new one.
     *
     * @param string $field    Facet field
     * @param string $value    Facet value
     * @param string $operator Facet type to add (AND, OR, NOT)
     *
     * @return string
     */
    public function replaceFacet($field, $value, $operator = 'AND')
    {
        $newParams = clone($this->params);
        $newParams->removeAllFilters($field);
        $helper = new static($newParams);
        return $helper->addFacet($field, $value, $operator);
    }

    /**
     * Sets search id in the params.
     *
     * @param string $class Search class.
     *
     * @return void
     */
    public function removeSearchId($class)
    {
        $params = $this->defaultParams;
        if (!isset($params['search'])) {
            return;
        }
        $searches = [];
        foreach ($params['search'] as $search) {
            list($searchClass, $searchId) = explode(':', $search);
            if ($searchClass != $class) {
                $searches[] = $search;
            }
        }
        $this->setDefaultParameter('search', $searches);
    }

    /**
     * Sets search id in the params.
     *
     * @param string  $class  Search class.
     * @param int     $id     Search id or NULL if the current id for this
     *                        search class should be removed.
     * @param boolean $output Output query string?
     *
     * @return string
     */
    public function setSearchId($class, $id, $output = true)
    {
        $params = $this->defaultParams;
        $searches = isset($params['search']) ? $params['search'] : [];

        $res = [];
        if ($id !== null) {
            $res[] = "$class:$id";
        }

        foreach ($searches as $search) {
            list($searchClass, $searchId) = explode(':', $search);
            if ($searchClass !== $class) {
                $res[] = "$searchClass:$searchId";
            }
        }
        $this->setDefaultParameter('search', $res);

        if ($output) {
            $params = $this->getParamArray();
            return '?' . $this->buildQueryString($params, false);
        }
    }

    /**
     * Get an array of URL parameters.
     *
     * @return array
     */
    public function getParamArray()
    {
        $params = parent::getParamArray();
        $filter = $this->params->getSpatialDateRangeFilter();
        if ($filter && isset($filter['type']) && isset($filter['query'])) {
            $field = $this->params->getSpatialDateRangeField() . '_type';
            $params[$field] = $filter['type'];
        }
        if ($set = $this->params->getMetaLibSearchSet()) {
            $params['set'] = $set;
        }
        return $params;
    }
}
