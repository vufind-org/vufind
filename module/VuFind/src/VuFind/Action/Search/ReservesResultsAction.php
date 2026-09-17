<?php

/**
 * Search results action for course reserves.
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
use VuFind\ActionHelper\FlashMessagesHelper;
use VuFind\Auth\Manager as AuthManager;
use VuFind\Config\ConfigManager;
use VuFind\ContentBlock\BlockLoader;
use VuFind\Db\Service\PluginManager as DbServicePluginManager;
use VuFind\Db\Service\SearchServiceInterface;
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

use function array_slice;
use function count;

/**
 * Search results action for course reserves.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class ReservesResultsAction extends AbstractSearchAndResultsAction
{
    /**
     * Results for reserves search.
     *
     * @var array
     */
    protected array $resultReserves = [];

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
     * Display reserves results.
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
        // Retrieve course reserves item list:
        $course = $this->getQueryParam('course');
        $inst = $this->getQueryParam('inst');
        $dept = $this->getQueryParam('dept');
        $this->resultReserves = $this->reservesHelper->findReserves($course, $inst, $dept);

        // Build a list of unique IDs
        $callback = function ($i) {
            return $i['BIB_ID'];
        };
        $bibIDs = array_unique(array_map($callback, $this->resultReserves));

        // Truncate the list if it is too long:
        $limit = $this->resultsPluginManager->get($this->getBackendId())->getParams()->getQueryIDLimit();
        if (count($bibIDs) > $limit) {
            $bibIDs = array_slice($bibIDs, 0, $limit);
            $this->getHelper(FlashMessagesHelper::class)->addInfoMessage('too_many_reserves');
        }

        // Don't save to history -- history page doesn't handle correctly:
        $this->saveToHistory = false;

        // Set up RSS feed title just in case:
        $this->resultFeedHelper->setOverrideTitle('Reserves Search Results');

        // Use standard search action with override parameter to show results:
        $this->request = $request = $request->withQueryParams(
            [
                'overrideIds' => $bibIDs,
            ] + $request->getQueryParams()
        );

        return $this->renderSearchResults($request, $response);
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

        // Pass some key values to the template, if found:
        if ($instructor = $this->resultReserves[0]['instructor'] ?? null) {
            $templateParams['instructor'] = $instructor;
        }
        if ($course = $this->resultReserves[0]['course'] ?? null) {
            $templateParams['course'] = $course;
        }

        // Customize the URL helper to make sure it builds proper reserves URLs (but only do this if we have access to a
        // results object, which we won't in RSS mode):
        if ($results = $templateParams['results'] ?? null) {
            $results->getUrlQuery()
                ->setDefaultParameter('course', $this->getQueryParam('course'))
                ->setDefaultParameter('inst', $this->getQueryParam('inst'))
                ->setDefaultParameter('dept', $this->getQueryParam('dept'))
                ->setSuppressQuery(true);
        }

        return $templateParams;
    }
}
