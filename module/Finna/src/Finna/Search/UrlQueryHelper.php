<?php
/**
 * Class to help build URLs and forms in the view based on search settings.
 *
 * PHP version 7
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Search
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace Finna\Search;

use Finna\Search\Solr\AuthorityHelper;

/**
 * Class to help build URLs and forms in the view based on search settings.
 *
 * @category VuFind
 * @package  Search
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class UrlQueryHelper extends \VuFind\Search\UrlQueryHelper
{
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
     * Remove a parameter from the object.
     *
     * @param string $name Name of parameter
     *
     * @return UrlQueryHelper
     */
    public function removeDefaultParameter($name)
    {
        unset($this->urlParams[$name]);
        return $this;
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
        // Remove any previous filter:
        $paramArray = $this->getParamArray();
        $newFilter = [];
        $prefix = ($operator == 'NOT') ? '-' : ($operator == 'OR' ? '~' : '');
        if (isset($paramArray['filter']) && is_array($paramArray['filter'])) {
            foreach ($paramArray['filter'] as $current) {
                list($currentField, $currentValue) = $this->parseFilter($current);
                if ($currentField !== $prefix . $field) {
                    $newFilter[] = $current;
                }
            }
        }

        $paramArray['filter'] = $newFilter;
        $paramArray['filter'][] = $prefix . $field . ':"' . $value . '"';
        unset($paramArray['page']);

        return new static($paramArray, $this->queryObject, $this->config, false);
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
        $params = $this->getParamArray();
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
        $params = $this->getParamArray();
        $searches = $params['search'] ?? [];

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
            return $this->getParams(false);
        }
    }

    /**
     * Get the current search parameters without page param as a GET query.
     *
     * @param bool $escape Should we escape the string for use in the view?
     *
     * @return string
     */
    public function getParamsWithoutPage($escape = true)
    {
        $params = $this->urlParams;
        unset($params['page']);
        return '?' . $this->buildQueryString($params, $escape);
    }

    /**
     * Get the current search parameters with an author id-role filter.
     *
     * @param string $idWithRole Author id with role
     *
     * @return string
     */
    public function setAuthorIdWithRole($idWithRole)
    {
        $separator = AuthorityHelper::AUTHOR_ID_ROLE_SEPARATOR;
        list($id, $role) = explode($separator, $idWithRole);

        $params = $this->urlParams;
        $filters = $params['filter'] ?? [];
        $filters[]
            = AuthorityHelper::AUTHOR_ID_ROLE_FACET . ":{$id}{$separator}{$role}";
        $params['filter'] = $filters;
        return '?' . $this->buildQueryString($params, true);
    }

    /**
     * Get the current search parameters with an author id filter.
     *
     * @param string $id Author id
     *
     * @return string
     */
    public function setAuthorId($id)
    {
        $filters = $this->urlParams['filter'] ?? [];
        $filters[]
            = AuthorityHelper::AUTHOR2_ID_FACET . ":{$id}";
        $params = $this->urlParams;
        $params['filter'] = $filters;
        return '?' . $this->buildQueryString($params, true);
    }
}
