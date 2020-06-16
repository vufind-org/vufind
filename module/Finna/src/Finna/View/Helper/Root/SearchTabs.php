<?php
/**
 * "Search tabs" view helper
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
 * @package  View_Helpers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace Finna\View\Helper\Root;

use Laminas\Session\SessionManager;
use Laminas\View\Helper\Url;
use VuFind\Db\Table\PluginManager as TableManager;
use VuFind\Search\Results\PluginManager as ResultsManager;
use VuFind\Search\SearchTabsHelper;

/**
 * "Search tabs" view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class SearchTabs extends \VuFind\View\Helper\Root\SearchTabs
{
    /**
     * Database manager
     *
     * @var TableManager
     */
    protected $table;

    /**
     * Session manager
     *
     * @var SessionManager
     */
    protected $session;

    /**
     * Active search class
     *
     * @var string
     */
    protected $activeSearchClass;

    /**
     * Constructor
     *
     * @param PluginManager    $results Search results plugin manager
     * @param Url              $url     URL helper
     * @param SearchTabsHelper $helper  Search tabs helper
     * @param SessionManager   $session Session manager
     * @param TableManager     $table   Database manager
     */
    public function __construct(ResultsManager $results, Url $url,
        SearchTabsHelper $helper, SessionManager $session,
        TableManager $table
    ) {
        parent::__construct($results, $url, $helper);
        $this->session = $session;
        $this->table = $table;
    }

    /**
     * Determine information about search tabs
     *
     * @param string $activeSearchClass The search class ID of the active search
     * @param string $query             The current search query
     * @param string $handler           The current search handler
     * @param string $type              The current search type (basic/advanced)
     * @param array  $hiddenFilters     The current hidden filters
     * @param array  $savedSearches     Saved search ids from all search tabs
     *
     * @return array
     */
    public function getTabConfig($activeSearchClass, $query, $handler,
        $type = 'basic', $hiddenFilters = [], $savedSearches = []
    ) {
        $this->activeSearchClass = $activeSearchClass;

        $tabConfig = parent::getTabConfig(
            $activeSearchClass, $query, $handler, $type, $hiddenFilters
        );
        $tabs = &$tabConfig['tabs'];
        if ($type == 'advanced') {
            $tabs = array_filter(
                $tabs,
                function ($tab) {
                    return strcasecmp($tab['class'], 'combined') != 0;
                }
            );
        }
        $searchTable = $this->table->get('Search');

        foreach ($tabs as $key => &$tab) {
            // Remove any disabled functions
            if (in_array($tab['class'], ['Combined', 'Primo'])) {
                $helper = $this->getView()->plugin($tab['class']);
                if (!$helper->isAvailable()) {
                    unset($tabs[$key]);
                    continue;
                }
            }

            if (isset($tab['url'])) {
                $parts = parse_url($tab['url']);
                $params = [];
                if (isset($parts['query'])) {
                    parse_str($parts['query'], $params);
                }

                // Remove search index specific URL parameters
                $dropParams = [
                   'page', 'set', 'sort'
                ];
                $resultParams = $this->results->get($this->activeSearchClass)
                    ->getParams();
                if (is_callable([$resultParams, 'getDateRangeSearchField'])) {
                    $dateRangeField = $resultParams->getDateRangeSearchField();
                    if ($dateRangeField) {
                        $dropParams[] = "{$dateRangeField}_type";
                    }
                }
                $params = array_diff_key($params, array_flip($dropParams));

                $filterQuery = false;

                $tabId = urlencode($tab['id']);
                if (isset($savedSearches[$tabId])) {
                    $helper = $this->getView()->results->getUrlQuery();
                    $searchId = $savedSearches[$tabId];
                    $searchSettings = $this->getSearchSettings($searchId);
                    $targetClass = $tab['id'];

                    // Make sure that tab url does not contain the
                    // search id for the same tab.
                    if (isset($searchSettings['params'])) {
                        $params = array_merge($params, $searchSettings['params']);
                    }

                    if (isset($params['search'])) {
                        $filtered = [];
                        foreach ($params['search'] as $search) {
                            list($searchClass, $searchId) = explode(':', $search);
                            if ($searchClass !== $targetClass) {
                                $filtered[] = $search;
                            }
                        }
                        if (!empty($filtered)) {
                            $params['search'] = $filtered;
                        } else {
                            unset($params['search']);
                        }
                    }

                    if (isset($searchSettings['filters'])) {
                        $filterQuery .= '&' .
                            $helper->buildQueryString(
                                ['filter' => $searchSettings['filters']], false
                            );
                    }
                }
                $url = $parts['path'];
                if (!empty($params)) {
                    $url .= '?' . http_build_query($params);
                }
                if ($filterQuery) {
                    if (strstr($url, '?') === false) {
                        $url .= '?';
                    }
                    $url .= $filterQuery;
                }
                $tab['url'] = $url;
            }
        }
        return $tabConfig;
    }

    /**
     * Map a search query from one class to another.
     *
     * @param \VuFind\Search\Base\Options $activeOptions Search options for source
     * @param string                      $targetClass   Search class ID for target
     * @param string                      $query         Search query to map
     * @param string                      $handler       Search handler to map
     * @param array                       $filters       Tab filters
     *
     * @return string
     */
    protected function remapBasicSearch($activeOptions, $targetClass, $query,
        $handler, $filters
    ) {
        $urlQueryFactory = new \Finna\Search\Factory\UrlQueryHelperFactory();

        // Set up results object for URL building
        $targetResults = $this->results->get($targetClass);
        $targetParams = $targetResults->getParams();
        foreach ($filters as $filter) {
            $targetParams->addHiddenFilter($filter);
        }
        $targetTabId
            = $this->getTabId($targetClass, $targetParams->getHiddenFilters());
        $targetOptions = $targetResults->getOptions();
        $targetParams->setBasicSearch($query, $handler);

        // Clone the active query so that we can remove active filters
        $currentResults = clone $this->getView()->results;
        $currentParams = $currentResults->getParams();

        // Remove current filters
        $oldFilters = $currentResults->getParams()->getRawFilters();
        $tabId = $this->getTabId(
            $this->activeSearchClass,
            $currentParams->getHiddenFilters()
        );
        if (is_callable([$currentParams, 'removeHiddenFilters'])) {
            $currentParams->removeHiddenFilters();
        }
        $currentParams->removeAllFilters();

        // Add filters to the new params
        foreach ($filters as $filter) {
            $currentParams->addHiddenFilter($filter);
        }

        $currentUrlQuery = $urlQueryFactory->fromParams($currentParams);

        // Remove any remembered search hash for this tab:
        if (method_exists($currentUrlQuery, 'removeSearchId')) {
            $currentUrlQuery->removeSearchId($targetTabId);
        }

        if (!empty($oldFilters)) {
            // Filters were active, include current search id in the url
            $searchId = $currentResults->getSearchId();
            if (method_exists($currentUrlQuery, 'setSearchId')) {
                $currentUrlQuery->setSearchId($tabId, $searchId);
            }
        }

        // Build new URL
        $url = $this->url->__invoke($targetOptions->getSearchAction())
            . $currentUrlQuery->getParams(false);

        return $url;
    }

    /**
     * Return filters for a saved search.
     *
     * @param int $id Search hash
     *
     * @return mixed array of filters or false if the given search has no filters.
     */
    protected function getSearchSettings($id)
    {
        $search
            = $this->table->get('Search')
            ->select(['id' => $id])->current();
        if (empty($search)) {
            return false;
        }

        $sessId = $this->session->getId();
        if ($search->session_id == $sessId) {
            $minSO = $search->getSearchObject();
            $savedSearch = $minSO->deminify($this->results);

            $params = $savedSearch->getUrlQuery()->getParamArray();

            $settings = [];
            if (isset($params['filter'])) {
                $settings['filters'] = $params['filter'];
            }

            return $settings;
        }
        return false;
    }

    /**
     * Find out the tab id with search class and hidden filters and return it
     * url-encoded to avoid it containing e.g. colon
     *
     * @param string $searchClass   Search class
     * @param array  $hiddenFilters Hidden filters
     *
     * @return string
     */
    protected function getTabId($searchClass, $hiddenFilters)
    {
        $tabConfig = $this->helper->getTabConfig();
        $filterConfig = $this->helper->getTabFilterConfig();
        foreach ($tabConfig as $key => $label) {
            $class = $this->helper->extractClassName($key);
            if ($searchClass == $class) {
                $filters = isset($filterConfig[$key])
                    ? (array)$filterConfig[$key] : [];
                if ($this->helper->filtersMatch($class, $hiddenFilters, $filters)) {
                    return urlencode($key);
                }
            }
        }
        return urlencode($searchClass);
    }
}
