<?php

/**
 * Search form action for Solr-driven course reserves.
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
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\Auth\Manager as AuthManager;
use VuFind\Config\ConfigManager;
use VuFind\ContentBlock\BlockLoader;
use VuFind\Db\Service\PluginManager as DbServicePluginManager;
use VuFind\Db\Service\SearchServiceInterface;
use VuFind\ILS\Connection;
use VuFind\Recommend\PluginManager as RecommendPluginManager;
use VuFind\Record\Router as RecordRouter;
use VuFind\Search\Base\Results;
use VuFind\Search\History as SearchHistory;
use VuFind\Search\Memory as SearchMemory;
use VuFind\Search\Options\PluginManager as SearchOptionsPluginManager;
use VuFind\Search\ReservesHelper;
use VuFind\Search\Results\PluginManager as ResultsPluginManager;
use VuFind\Search\ResultScroller;
use VuFind\Search\SearchNormalizer;
use VuFind\Search\SearchRunner;
use VuFind\ServiceManager\Factory\Autowire;
use VuFind\View\Helper\Root\ResultFeed;
use VuFindTheme\ThemeInfo;

/**
 * Search form action for Solr-driven course reserves.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class ReservesSearchAction extends AbstractSearchAndResultsAction
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
     * @param ReservesHelper             $reservesHelper             Reserves helper
     * @param Connection                 $ilsConnection              ILS connection
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
        protected ReservesHelper $reservesHelper,
        protected Connection $ilsConnection,
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
     * Display reserves search form for Solr-driven reserves.
     *
     * @param ServerRequestInterface $request  Server request
     * @param ResponseInterface      $response Response
     *
     * @return ResponseInterface
     */
    public function action(
        ServerRequestInterface $request,
        ResponseInterface $response,
    ): ResponseInterface {
        $templateParams = $this->getSearchResultsTemplateParams($request, $this->getBackendId(), null);
        $templateParams['params'] = $templateParams['results']->getParams();
        return $this->renderTemplate($request, $response, $templateParams);
    }
}
