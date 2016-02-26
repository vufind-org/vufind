<?php
/**
 * "Search tabs" view helper
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
 * @package  View_Helpers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace Finna\View\Helper\Root;
use Finna\Search\Solr\Params as SolrParams,
    Finna\Search\Primo\Params as PrimoParams,
    Finna\Search\Results\PluginManager;
use VuFind\Search\SearchTabsHelper;
use Zend\View\Helper\Url;

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
     * @var PluginManager
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
     * @param PluginManager    $table   Database manager
     */
    public function __construct(PluginManager $results, Url $url,
        SearchTabsHelper $helper,
        \Zend\Session\SessionManager $session,
        \VuFind\Db\Table\PluginManager $table
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

        $tabs = parent::getTabConfig(
            $activeSearchClass, $query, $handler, $type, $hiddenFilters
        );
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
            if (in_array($tab['class'], ['Combined', 'MetaLib', 'Primo'])) {
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
                   SolrParams::SPATIAL_DATERANGE_FIELD . '_type',
                   'page', 'set', 'sort'
                ];
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
        return count($tabs) > 1 ? $tabs : [];
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
        // Set up results object for URL building:
        $targetResults = $this->results->get($targetClass);
        $targetParams = $targetResults->getParams();
        $targetUrlQuery = $targetResults->getUrlQuery();
        foreach ($filters as $filter) {
            $targetParams->addHiddenFilter($filter);
        }

        // Remove any remembered search hash for this tab:
        $targetTabId
            = $this->getTabId($targetClass, $targetParams->getHiddenFilters());
        if (method_exists($targetUrlQuery, 'removeSearchId')) {
            $targetUrlQuery->removeSearchId($targetTabId);
        }

        $targetOptions = $targetResults->getOptions();
        $targetParams->setBasicSearch($query, $handler);

        // Clone the active query so that we can remove active filters
        $currentResults = clone($this->getView()->results);
        $urlQuery = $currentResults->getUrlQuery();

        // Remove current filters
        $oldFilters = $currentResults->getParams()->getFilters();
        $tabId = $this->getTabId(
            $this->activeSearchClass,
            $currentResults->getParams()->getHiddenFilters()
        );
        $currentResults->getParams()->removeHiddenFilters();
        $currentResults->getParams()->removeAllFilters();

        $queryString = null;
        if (!empty($oldFilters)) {
            // Filters were active, include current search id in the url
            if (method_exists($currentResults, 'getSearchHash')) {
                $searchId = $currentResults->getSearchHash();
                if (method_exists($urlQuery, 'setSearchId')) {
                    $queryString = $urlQuery->setSearchId($tabId, $searchId);
                }
            }
        }
        if (null === $queryString) {
            $queryString = $urlQuery->getParams(false);
        }

        // Build new URL:
        $hiddenFilterQuery
            = substr($targetResults->getUrlQuery()->getParams(false), 1);
        $url = $this->url->__invoke($targetOptions->getSearchAction())
            . $queryString;
        if ($hiddenFilterQuery) {
            $url .= "&$hiddenFilterQuery";
        }
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
                ->select(['finna_search_id' => $id])->current();
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
                $params = $savedSearch->getParams();
                if ($daterange = $params->getSpatialDateRangeFilter()) {
                    $daterangeField = $params->getSpatialDateRangeField();
                    foreach ($settings['filters'] as $filter) {
                        list($field, $val) = explode(':', $filter, 2);
                        if ($field == $daterangeField) {
                            $type = $daterange['type'];
                            $settings['params']
                                = ["{$daterangeField}_type" => $type];
                            break;
                        }
                    }
                }
            }
            $params = $savedSearch->getParams();
            if ($set = $params->getMetaLibSearchSet()) {
                $settings['params'] = ['set' => $set];
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
