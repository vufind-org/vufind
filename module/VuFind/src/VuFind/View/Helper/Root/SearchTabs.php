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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\View\Helper\Root;

use Psr\Log\LoggerAwareInterface;
use VuFind\Log\LoggerAwareTrait;
use VuFind\Search\Base\Results;
use VuFind\Search\Results\PluginManager;
use VuFind\Search\SearchTabsHelper;
use VuFind\Search\UrlQueryHelper;
use VuFind\ServiceManager\Factory\Autowire;

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
class SearchTabs implements LoggerAwareInterface
{
    use LoggerAwareTrait;

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
     * @param PluginManager    $results      Search results plugin manager
     * @param Url              $url          URL helper
     * @param SearchTabsHelper $helper       Search tabs helper
     * @param SearchMemory     $searchMemory Search memory view helper
     */
    public function __construct(
        #[Autowire(service: PluginManager::class)]
        protected PluginManager $results,
        #[Autowire(container: 'ViewHelperManager')]
        protected Url $url,
        protected SearchTabsHelper $helper,
        #[Autowire(container: 'ViewHelperManager')]
        protected SearchMemory $searchMemory
    ) {
    }

    /**
     * Invoke the helper.
     *
     * @return SearchTabs
     */
    public function __invoke()
    {
        return $this;
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
            $permissionName = $allPermissions[$key] ?? null;
            $class = $this->helper->extractClassName($key);
            $filters = isset($allFilters[$key]) ? (array)$allFilters[$key] : [];
            $selected = $class == $activeSearchClass
                && $this->helper->filtersMatch($class, $hiddenFilters, $filters);
            try {
                if ($type == 'basic') {
                    if (!isset($activeOptions)) {
                        $activeOptions = $this->results->get($activeSearchClass)->getOptions();
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
            } catch (\Exception $e) {
                // Log the error and just don't add tabs that we couldn't get the data for
                $baseMsg = "Could not add tab for {$key}.";
                $shortDetails = $e->getMessage();
                $fullDetails = (string)$e;
                $this->logError(
                    $baseMsg,
                    [
                        'details' => [
                            1 => "$baseMsg $shortDetails",
                            2 => "$baseMsg $shortDetails",
                            3 => "$baseMsg $shortDetails",
                            4 => "$baseMsg $fullDetails",
                            5 => "$baseMsg $fullDetails",
                        ],
                    ]
                );
                continue;
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
            $hiddenFilters = $this->getHiddenFilters(
                $searchClassId,
                $ignoreHiddenFilterMemory
            );
            if (empty($hiddenFilters) && !$ignoreHiddenFilterMemory) {
                $hiddenFilters = $this->searchMemory->getLastHiddenFilters($searchClassId);
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
        $params->setBasicSearch($query, $targetHandler);
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
        $results = $this->results->get($class);
        return ($this->url)($results->getOptions()->getSearchHomeAction())
            . $this->buildUrlHiddenFilters($results, $filters);
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
