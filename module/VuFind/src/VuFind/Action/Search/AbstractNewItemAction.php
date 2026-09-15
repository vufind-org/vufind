<?php

/**
 * Abstract base class for new item actions.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Action\Search;

use Laminas\Session\SessionManager;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\Auth\Manager as AuthManager;
use VuFind\Config\ConfigManager;
use VuFind\ContentBlock\BlockLoader;
use VuFind\Db\Service\PluginManager as DbServicePluginManager;
use VuFind\Db\Service\SearchServiceInterface;
use VuFind\Recommend\PluginManager as RecommendPluginManager;
use VuFind\Record\Router as RecordRouter;
use VuFind\Search\Base\Results;
use VuFind\Search\FacetCache\PluginManager as FacetCachePluginManager;
use VuFind\Search\History;
use VuFind\Search\History as SearchHistory;
use VuFind\Search\Memory as SearchMemory;
use VuFind\Search\NewItemsHelper;
use VuFind\Search\Options\PluginManager as SearchOptionsPluginManager;
use VuFind\Search\Results\PluginManager as ResultsPluginManager;
use VuFind\Search\ResultScroller;
use VuFind\Search\SearchNormalizer;
use VuFind\Search\SearchRunner;
use VuFind\Search\Solr\HierarchicalFacetHelper;
use VuFind\ServiceManager\Factory\Autowire;
use VuFind\View\Helper\Root\ResultFeed;
use VuFind\View\Helper\Root\SearchTabs;
use VuFindTheme\ThemeInfo;

use function intval;

/**
 * Abstract base class for new item actions.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
abstract class AbstractNewItemAction extends AbstractSearchAndResultsFacetingAction
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
     * @param NewItemsHelper             $newItemsHelper             New items helper
     * @param SearchTabs                 $searchTabsHelper           Search tabs view helper
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
        FacetCachePluginManager $facetCachePluginManager,
        HierarchicalFacetHelper $hierarchicalFacetHelper,
        protected NewItemsHelper $newItemsHelper,
        #[Autowire(container: 'ViewHelperManager')]
        protected SearchTabs $searchTabsHelper,
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
            $facetCachePluginManager,
            $hierarchicalFacetHelper,
        );
    }

    /**
     * Get new item parameters from the query and configuration.
     *
     * @return array
     */
    protected function getNewItemParameters(): array
    {
        // Retrieve new item list:
        $range = intval($this->getQueryParam('range', 0));

        // Validate the range parameter -- it should not exceed the greatest configured value:
        $maxAge = $this->newItemsHelper->getMaxAge();
        if ($maxAge > 0 && $range > $maxAge) {
            $range = $maxAge;
        }

        // Are there "new item" filter queries specified in the config file?
        // If so, load them now; we may add more values. These will be applied later after the whole list is collected.
        $hiddenFilters = $this->newItemsHelper->getHiddenFilters();

        return compact('range', 'hiddenFilters');
    }

    /**
     * Modify the current query parameters to reflect a new item search.
     *
     * @param ServerRequestInterface $request       Server request
     * @param array                  $newItemParams Parameters retrieved from getNewItemParameters()
     *
     * @return ServerRequestInterface
     */
    protected function setUpNewItemRequestParams(
        ServerRequestInterface $request,
        array $newItemParams
    ): ServerRequestInterface {
        $queryParams = $request->getQueryParams();

        // The facet list needs one extra parameter to generate appropriate links:
        $queryParams['searchAction'] = $this->routeHelper->getUrlFromRoute('search-newitem');

        // Use a Solr filter to show results:
        $queryParams['hiddenFilters'] = $newItemParams['hiddenFilters'] ?? [];
        $queryParams['hiddenFilters'][] = $this->newItemsHelper->getSolrFilter($newItemParams['range']);

        // Flag this as a specialized search to avoid bleeding defaults into the
        // standard search box:
        $queryParams['specializedSearch'] = true;

        return $request->withQueryParams($queryParams);
    }

    /**
     * Get template params for rendering search results.
     *
     * @param ServerRequestInterface $request       Server request
     * @param string                 $searchClassId Search class id
     * @param ?callable              $setupCallback Optional setup callback that overrides the default one
     *
     * @return array
     */
    protected function getSearchResultsTemplateParams(
        ServerRequestInterface $request,
        string $searchClassId,
        ?callable $setupCallback = null
    ): array {
        $templateParams = parent::getSearchResultsTemplateParams($request, $searchClassId, $setupCallback);

        // Customize the URL helper to make sure it builds proper new item URLs
        // (check it's set first -- RSS feed will return a response model rather
        // than a view model):
        if ($results = $templateParams['results'] ?? null) {
            $results->getOptions()->setFacetListAction('search-newitemfacetlist');
            $results->getUrlQuery()
                ->setDefaultParameter('range', $this->getNewItemParameters()['range'])
                ->disableHiddenFilters()
                ->setSuppressQuery(true);
        }

        // We don't want new items hidden filters to propagate to other searches:
        $templateParams['ignoreHiddenFiltersInRequest'] = true;

        return $templateParams;
    }
}
