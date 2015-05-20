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
 * @category VuFind2
 * @package  Search
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace Finna\Search;

/**
 * Class to help build URLs and forms in the view based on search settings.
 *
 * @category VuFind2
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
     * Remove all filters.
     *
     * @return void
     */
    public function removeAllFilters()
    {
        $this->params->removeAllFilters();
    }

    /**
     * Expose parent method since we need to use from SearchTabs.
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
     * Sets search id in the params and returns resulting query string.
     *
     * @param string $class Search class.
     * @param int    $id    Search id.
     *
     * @return string
     */
    public function setSearchId($class, $id)
    {
        $params = $this->getParamArray();
        $searches = isset($params['search']) ? $params['search'] : [];
        $res = [];
        $res[] = "$class:$id";

        foreach ($searches as $search) {
            list($searchClass, $searchId) = explode(':', $search);
            if ($searchClass !== $class) {
                $res[] = "$searchClass:$searchId";
            }
        }
        $params['search'] = $res;

        return '?' . $this->buildQueryString($params, false);
    }
}
