<?php

/**
 * "Search tabs" helper
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2015-2016.
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
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\Search;

use Laminas\Http\Request;
use VuFind\Search\Results\PluginManager;

/**
 * "Search tabs" helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class SearchTabsHelper extends \Laminas\View\Helper\AbstractHelper
{
    /**
     * Search manager
     *
     * @var PluginManager
     */
    protected $results;

    /**
     * Tab configuration
     *
     * @var array
     */
    protected $tabConfig;

    /**
     * Tab filter configuration
     *
     * @var array
     */
    protected $filterConfig;

    /**
     * Tab permission configuration
     *
     * @var array
     */
    protected $permissionConfig;

    /**
     * Tab settings
     *
     * @var array
     */
    protected $settings;

    /**
     * Request
     *
     * @var Request
     */
    protected $request;

    /**
     * Constructor
     *
     * @param PluginManager $results      Search results plugin manager
     * @param array         $tabConfig    Tab configuration
     * @param array         $filterConfig Tab filter configuration
     * @param Request       $request      Request
     * @param array         $permConfig   Tab permission configuration
     * @param array         $settings     Tab settings
     */
    public function __construct(
        PluginManager $results,
        array $tabConfig,
        array $filterConfig,
        Request $request,
        array $permConfig = [],
        array $settings = []
    ) {
        $this->results = $results;
        $this->tabConfig = $tabConfig;
        $this->filterConfig = $filterConfig;
        $this->request = $request;
        $this->permissionConfig = $permConfig;
        $this->settings = $settings;
    }

    /**
     * Get an array of hidden filters
     *
     * @param string $searchClassId         Active search class
     * @param bool   $returnDefaultsIfEmpty Whether to return default tab filters if
     * no filters are currently active
     * @param bool   $ignoreCurrentRequest  Whether to ignore hidden filters in
     * the current request
     *
     * @return array
     */
    public function getHiddenFilters(
        $searchClassId,
        $returnDefaultsIfEmpty = true,
        $ignoreCurrentRequest = false
    ) {
        $filters = $ignoreCurrentRequest
            ? null : $this->request->getQuery('hiddenFilters');
        if (null === $filters && $returnDefaultsIfEmpty) {
            $filters = $this->getDefaultTabHiddenFilters($searchClassId);
        }
        return null === $filters
            ? [] : $this->parseFilters($searchClassId, (array)$filters);
    }

    /**
     * Get the tab configuration
     *
     * @return array
     */
    public function getTabConfig()
    {
        return $this->tabConfig;
    }

    /**
     * Get the tab filters
     *
     * @return array
     */
    public function getTabFilterConfig()
    {
        return $this->filterConfig;
    }

    /**
     * Get the tab permissions
     *
     * @return array
     */
    public function getTabPermissionConfig()
    {
        return $this->permissionConfig;
    }

    /**
     * Get the tab details
     *
     * @return array
     */
    public function getSettings()
    {
        return $this->settings;
    }

    /**
     * Extract search class name from a tab id
     *
     * @param string $tabId Tab id as defined in config.ini
     *
     * @return string
     */
    public function extractClassName($tabId)
    {
        [$class] = explode(':', $tabId, 2);
        return $class;
    }

    /**
     * Check if given hidden filters match with the hidden filters from configuration
     *
     * @param string $class         Search class ID
     * @param array  $hiddenFilters Hidden filters
     * @param array  $configFilters Filters from filter configuration
     *
     * @return bool
     */
    public function filtersMatch($class, $hiddenFilters, $configFilters)
    {
        return $hiddenFilters == $this->parseFilters($class, $configFilters);
    }

    /**
     * Get an array of hidden filters for the default tab of the given search class
     *
     * @param string $searchClassId Search class
     *
     * @return null|array
     */
    protected function getDefaultTabHiddenFilters($searchClassId)
    {
        if (empty($this->tabConfig)) {
            return null;
        }

        $firstTab = null;
        foreach (array_keys($this->tabConfig) as $key) {
            $class = $this->extractClassName($key);
            if ($class == $searchClassId) {
                if (null === $firstTab) {
                    $firstTab = $key;
                }
                if (empty($this->filterConfig[$key])) {
                    return null;
                }
            }
        }
        if (null === $firstTab || empty($this->filterConfig[$firstTab])) {
            return null;
        }

        return (array)$this->filterConfig[$firstTab];
    }

    /**
     * Parse a simple filter array to a keyed array
     *
     * @param string $class   Search class ID
     * @param array  $filters Filters to parse
     *
     * @return array
     */
    protected function parseFilters($class, $filters)
    {
        $results = $this->results->get($class);
        $params = $results->getParams();
        $result = [];
        foreach ($filters as $filter) {
            [$field, $value] = $params->parseFilter($filter);
            $result[$field][] = $value;
        }
        return $result;
    }
}
