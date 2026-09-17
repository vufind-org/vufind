<?php

/**
 * Abstract base class for search actions with facet-related methods.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2026.
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
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */

namespace VuFind\Action\Search;

use Laminas\Session\SessionManager;
use Laminas\Stdlib\Parameters;
use VuFind\Auth\Manager as AuthManager;
use VuFind\Config\ConfigManager;
use VuFind\ContentBlock\BlockLoader;
use VuFind\Db\Service\PluginManager as DbServicePluginManager;
use VuFind\Db\Service\SearchServiceInterface;
use VuFind\Recommend\PluginManager as RecommendPluginManager;
use VuFind\Record\Router as RecordRouter;
use VuFind\Search\Base\Results;
use VuFind\Search\FacetCache\PluginManager as FacetCachePluginManager;
use VuFind\Search\History as SearchHistory;
use VuFind\Search\Memory as SearchMemory;
use VuFind\Search\Options\PluginManager as SearchOptionsPluginManager;
use VuFind\Search\Results\PluginManager as ResultsPluginManager;
use VuFind\Search\ResultScroller;
use VuFind\Search\SearchNormalizer;
use VuFind\Search\SearchRunner;
use VuFind\Search\Solr\HierarchicalFacetHelper;
use VuFind\ServiceManager\Factory\Autowire;
use VuFind\View\Helper\Root\ResultFeed;
use VuFindTheme\ThemeInfo;

use function in_array;

/**
 * Abstract base class for search actions.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
abstract class AbstractSearchAndResultsFacetingAction extends AbstractSearchAndResultsAction
{
    /**
     * Constructor.
     *
     * @param SearchRunner               $searchRunner               Search runner
     * @param ResultsPluginManager       $resultsPluginManager       Search results plugin manager
     * @param ResultScroller             $resultScroller             Result scroller
     * @param RecommendPluginManager     $recommendPluginManager     Recommendation plugin manager
     * @param SearchMemory               $searchMemory               Search memoy
     * @param BlockLoader                $blockLoader                Content block loader
     * @param ConfigManager              $configManager              Configuration manager
     * @param RecordRouter               $recordRouter               Record router
     * @param SessionManager             $sessionManager             Session manager
     * @param SearchServiceInterface     $searchService              Search service
     * @param AuthManager                $authManager                Authentication manager
     * @param SearchNormalizer           $searchNormalizer           Search normalize
     * @param ResultFeed                 $resultFeedHelper           Result feed view helper
     * @param ThemeInfo                  $themeInfo                  Theme info
     * @param SearchHistory              $searchHistory              Search history
     * @param SearchOptionsPluginManager $searchOptionsPluginManager Search options plugin manager
     * @param FacetCachePluginManager    $facetCachePluginManager    Facet cache plugin manager
     * @param HierarchicalFacetHelper    $hierarchicalFacetHelper    Hierarchical facet helper
     */
    public function __construct(
        SearchRunner $searchRunner,
        ResultsPluginManager $resultsPluginManager,
        ResultScroller $resultScroller,
        RecommendPluginManager $recommendPluginManager,
        SearchMemory $searchMemory,
        BlockLoader $blockLoader,
        ConfigManager $configManager,
        RecordRouter $recordRouter,
        SessionManager $sessionManager,
        #[Autowire(container: DbServicePluginManager::class)]
        SearchServiceInterface $searchService,
        AuthManager $authManager,
        SearchNormalizer $searchNormalizer,
        #[Autowire(container: 'ViewHelperManager')]
        ResultFeed $resultFeedHelper,
        ThemeInfo $themeInfo,
        SearchHistory $searchHistory,
        SearchOptionsPluginManager $searchOptionsPluginManager,
        protected FacetCachePluginManager $facetCachePluginManager,
        protected HierarchicalFacetHelper $hierarchicalFacetHelper,
    ) {
        parent::__construct(
            $searchRunner,
            $resultsPluginManager,
            $resultScroller,
            $recommendPluginManager,
            $searchMemory,
            $blockLoader,
            $configManager,
            $recordRouter,
            $sessionManager,
            $searchService,
            $authManager,
            $searchNormalizer,
            $resultFeedHelper,
            $themeInfo,
            $searchHistory,
            $searchOptionsPluginManager,
        );
    }

    /**
     * Get an array of hierarchical facets.
     *
     * @param string $config Name of facet configuration file to load.
     *
     * @return array Facets
     */
    protected function getHierarchicalFacets($config)
    {
        $facetConfig = $this->configManager->getConfigArray($config);
        return $facetConfig['SpecialFacets']['hierarchical'] ?? [];
    }

    /**
     * Get an array of hierarchical facet sort options for Advanced search.
     *
     * @param string $config Name of facet configuration file to load.
     *
     * @return array
     */
    protected function getAdvancedHierarchicalFacetsSortOptions($config)
    {
        $facetConfig = $this->configManager->getConfigArray($config);
        $baseConfig = $facetConfig['SpecialFacets']['hierarchicalFacetSortOptions'] ?? [];
        $advancedConfig = $facetConfig['Advanced_Settings']['hierarchicalFacetSortOptions'] ?? [];
        return array_merge($baseConfig, $advancedConfig);
    }

    /**
     * Set up facet details in the template parameters (for use in advanced search and similar).
     *
     * @param array  $templateParams Template params
     * @param string $list           Name of facet list to retrieve
     *
     * @return array
     */
    protected function addFacetDetails(array $templateParams, $list = 'Advanced'): array
    {
        if (!$this->facetCachePluginManager->has($this->getBackendId())) {
            return $templateParams;
        }
        $facets = $this->facetCachePluginManager
            ->get($this->getBackendId())
            ->getList($list);
        $facetsIni = $templateParams['options']->getFacetsIni();
        $templateParams['hierarchicalFacets'] = $this->getHierarchicalFacets($facetsIni);
        $templateParams['hierarchicalFacetsSortOptions'] = $this->getAdvancedHierarchicalFacetsSortOptions($facetsIni);
        $templateParams['facetList'] = $this->processAdvancedFacets(
            $facets,
            ($templateParams['saved'] ?? null) ?: null,
            $templateParams['hierarchicalFacets'],
            $templateParams['hierarchicalFacetsSortOptions']
        );
        return $templateParams;
    }

    /**
     * Process the facets to be used as limits on the Advanced Search screen.
     *
     * @param array    $facetList                     The advanced facet values
     * @param ?Results $searchObject                  Saved search object, or null if none
     * @param array    $hierarchicalFacets            Hierarchical facet list (if any)
     * @param array    $hierarchicalFacetsSortOptions Hierarchical facet sort options
     *                                                (if any)
     *
     * @return array Sorted facets, with selected values flagged.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function processAdvancedFacets(
        array $facetList,
        ?Results $searchObject = null,
        array $hierarchicalFacets = [],
        array $hierarchicalFacetsSortOptions = []
    ): array {
        $options = null;
        foreach ($facetList as $facet => &$list) {
            // Hierarchical facets: format display texts and sort facets
            // to a flat array according to the hierarchy
            if (in_array($facet, $hierarchicalFacets)) {
                // Process the facets
                if (!$options) {
                    $options = $this->getOptionsForClass();
                }

                $tmpList = $list['list'];
                if ($options->getFilterHierarchicalFacetsInAdvanced()) {
                    $tmpList = $this->hierarchicalFacetHelper->filterFacets(
                        $facet,
                        $tmpList,
                        $options
                    );
                }
                $list['list'] = $this->hierarchicalFacetHelper->flattenFacetHierarchy($tmpList);
            }

            foreach ($list['list'] as $key => $value) {
                // Build the filter string for the URL:
                $fullFilter = ($value['operator'] == 'OR' ? '~' : '')
                    . $facet . ':"' . $value['value'] . '"';

                // If we haven't already found a selected facet and the current
                // facet has been applied to the search, we should store it as
                // the selected facet for the current control.
                if ($searchObject?->getParams()->hasFilter($fullFilter)) {
                    $list['list'][$key]['selected'] = true;
                    // Remove the filter from the search object -- we don't want
                    // it to show up in the "applied filters" sidebar since it
                    // will already be accounted for by being selected in the
                    // filter select list!
                    $searchObject->getParams()->removeFilter($fullFilter);
                }
            }
        }
        return $facetList;
    }
}
