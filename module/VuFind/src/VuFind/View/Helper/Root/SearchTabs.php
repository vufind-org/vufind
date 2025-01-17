<?php

/**
 * "Search tabs" view helper
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

namespace VuFind\View\Helper\Root;

use Laminas\Http\Request;
use Laminas\View\Helper\Url;
use VuFind\Search\Base\Results;
use VuFind\Search\Results\PluginManager;
use VuFind\Search\SearchTabsHelper;
use VuFind\Search\UrlQueryHelper;

/**
 * "Search tabs" view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class SearchTabs extends \Laminas\View\Helper\AbstractHelper
{
    /**
     * Search manager
     *
     * @var PluginManager
     */
    protected $results;

    /**
     * Request
     *
     * @var Request
     */
    protected $request;

    /**
     * Url
     *
     * @var Url
     */
    protected $url;

    /**
     * Search tab helper
     *
     * @var SearchTabsHelper
     */
    protected $helper;

    /**
     * Cached hidden filter url params
     *
     * @var array
     */
    protected $cachedHiddenFilterParams = [];

    /**
     * Should we force getCurrentHiddenFilterParams() to return an empty string?
     *
     * @var bool
     */
    protected $currentHiddenFilterParamsDisabled = false;

    /**
     * Constructor
     *
     * @param PluginManager    $results Search results plugin manager
     * @param Url              $url     URL helper
     * @param SearchTabsHelper $helper  Search tabs helper
     */
    public function __construct(
        PluginManager $results,
        Url $url,
        SearchTabsHelper $helper
    ) {
        $this->results = $results;
        $this->url = $url;
        $this->helper = $helper;
    }

    /**
     * Determine information about search tabs
     *
     * @param string $activeSearchClass The search class ID of the active search
     * @param string $query             The current search query
     * @param string $handler           The current search handler
     * @param string $type              The current search type (basic/advanced)
     * @param array  $hiddenFilters     The current hidden filters
     *
     * @return array
     */
    public function getTabConfig(
        $activeSearchClass,
        $query,
        $handler,
        $type = 'basic',
        $hiddenFilters = []
    ) {
        $retVal = ['tabs' => []];
        $allFilters = $this->helper->getTabFilterConfig();
        $allPermissions = $this->helper->getTabPermissionConfig();
        $allSettings = $this->helper->getSettings();
        $retVal['showCounts'] = $allSettings['show_result_counts'] ?? false;
        foreach ($this->helper->getTabConfig() as $key => $label) {
            $permissionName = null;
            if (isset($allPermissions[$key])) {
                $permissionName = $allPermissions[$key];
            }
            $class = $this->helper->extractClassName($key);
            $filters = isset($allFilters[$key]) ? (array)$allFilters[$key] : [];
            $selected = $class == $activeSearchClass && $this->helper->filtersMatch($class, $hiddenFilters, $filters);
            if ($type == 'basic') {
                if (!isset($activeOptions)) {
                    $activeOptions
                        = $this->results->get($activeSearchClass)->getOptions();
                }
                $url = $this->remapBasicSearch(
                    $activeOptions,
                    $class,
                    $query,
                    $handler,
                    $filters,
                );
            } elseif ($type == 'advanced') {
                $url = $this->getAdvancedTabUrl(
                    $class,
                    $filters,
                );
            } else {
                $url = $this->getHomeTabUrl(
                    $class,
                    $filters,
                );
            }
            $tab = [
                'id' => $key,
                'class' => $class,
                'label' => $label,
                'permission' => $permissionName,
                'selected' => $selected,
                'url' => $url,
            ];
            $retVal['tabs'][] = $tab;
            if ($selected) {
                $retVal['selected'] = $tab;
            }
        }

        return $retVal;
    }

    /**
     * Get the tab configuration
     *
     * @param \VuFind\Search\Base\Params $params Search parameters
     *
     * @return array
     */
    public function getTabConfigForParams($params)
    {
        $tabConfig = $this->getTabConfig(
            $params->getSearchClassId(),
            $params->getDisplayQuery(),
            $params->getSearchHandler(),
            $params->getSearchType(),
            $params->getHiddenFilters()
        );
        return $tabConfig['tabs'];
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
        return $this->helper->getHiddenFilters(
            $searchClassId,
            $returnDefaultsIfEmpty,
            $ignoreCurrentRequest
        );
    }

    /**
     * Get current hidden filters as a string suitable for search URLs
     *
     * @param string $searchClassId            Active search class
     * @param bool   $ignoreHiddenFilterMemory Whether to ignore hidden filters in search memory
     * @param string $prepend                  String to prepend to the hidden filters if they're not empty
     *
     * @return string
     */
    public function getCurrentHiddenFilterParams(
        $searchClassId,
        $ignoreHiddenFilterMemory = false,
        $prepend = '&amp;'
    ) {
        if ($this->currentHiddenFilterParamsDisabled) {
            return '';
        }
        if (!isset($this->cachedHiddenFilterParams[$searchClassId])) {
            $view = $this->getView();
            $hiddenFilters = $this->getHiddenFilters(
                $searchClassId,
                $ignoreHiddenFilterMemory
            );
            if (empty($hiddenFilters) && !$ignoreHiddenFilterMemory) {
                $hiddenFilters = $view->plugin('searchMemory')
                    ->getLastHiddenFilters($searchClassId);
                if (empty($hiddenFilters)) {
                    $hiddenFilters = $this->getHiddenFilters($searchClassId);
                }
            }

            $results = $this->results->get($searchClassId);
            $params = $results->getParams();
            foreach ($hiddenFilters as $field => $filter) {
                foreach ($filter as $value) {
                    $params->addHiddenFilterForField($field, $value);
                }
            }
            if ($hiddenFilters = $params->getHiddenFiltersAsQueryParams()) {
                $this->cachedHiddenFilterParams[$searchClassId]
                    = UrlQueryHelper::buildQueryString(
                        [
                            'hiddenFilters' => $hiddenFilters,
                        ]
                    );
            } else {
                $this->cachedHiddenFilterParams[$searchClassId] = '';
            }
        }
        if ('' !== ($filters = $this->cachedHiddenFilterParams[$searchClassId])) {
            return $prepend . $filters;
        }
        return '';
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
    protected function remapBasicSearch(
        $activeOptions,
        $targetClass,
        $query,
        $handler,
        $filters
    ) {
        // Set up results object for URL building:
        $results = $this->results->get($targetClass);
        $params = $results->getParams();
        foreach ($filters as $filter) {
            $params->addHiddenFilter($filter);
        }

        // Find matching handler for new query (and use default if no match):
        $options = $results->getOptions();
        $targetHandler = $options->getHandlerForLabel(
            $activeOptions->getLabelForBasicHandler($handler)
        );

        // Build new URL:
        $results->getParams()->setBasicSearch($query, $targetHandler);
        return ($this->url)($options->getSearchAction())
            . $results->getUrlQuery()->getParams(false);
    }

    /**
     * Get an url to "search home".
     *
     * @param string $class   Search class ID
     * @param array  $filters Tab filters
     *
     * @return string
     */
    protected function getHomeTabUrl($class, $filters)
    {
        // If an advanced search is available, link there; otherwise, just go
        // to the search home:
        $results = $this->results->get($class);
        $url = ($this->url)($results->getOptions()->getSearchHomeAction())
            . $this->buildUrlHiddenFilters($results, $filters);
        return $url;
    }

    /**
     * Get url for an advanced search tab.
     *
     * @param string $class   Search class ID
     * @param array  $filters Tab filters
     *
     * @return string
     */
    protected function getAdvancedTabUrl($class, $filters)
    {
        // If an advanced search is available, link there; otherwise, just go
        // to the search home:
        $results = $this->results->get($class);
        $options = $results->getOptions();
        $advSearch = $options->getAdvancedSearchAction();
        return ($this->url)($advSearch ?: $options->getSearchHomeAction())
            . $this->buildUrlHiddenFilters($results, $filters);
    }

    /**
     * Build a hidden filter query fragment from the given filters
     *
     * @param Results $results Search results
     * @param array   $filters Filters
     * @param string  $prepend String to prepend to the hidden filters if they're not
     * empty
     *
     * @return string Query parameters
     */
    protected function buildUrlHiddenFilters(
        Results $results,
        array $filters,
        string $prepend = '?'
    ): string {
        // Set up results object for URL building:
        $params = $results->getParams();
        foreach ($filters as $filter) {
            $params->addHiddenFilter($filter);
        }
        if ($hiddenFilters = $params->getHiddenFiltersAsQueryParams()) {
            return $prepend . UrlQueryHelper::buildQueryString(
                [
                    'hiddenFilters' => $hiddenFilters,
                ],
                false
            );
        }
        return '';
    }

    /**
     * Force getCurrentHiddenFilterParams() to return an empty string (used in contexts like
     * New Items where we don't want to persist hidden filters through links).
     *
     * @return void
     */
    public function disableCurrentHiddenFilterParams(): void
    {
        $this->currentHiddenFilterParamsDisabled = true;
    }
}
