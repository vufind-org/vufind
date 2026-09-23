<?php

/**
 * Search record versions action.
 *
 * PHP version 8
 *
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
use VuFind\Recommend\PluginManager as RecommendPluginManager;
use VuFind\Record\Loader as RecordLoader;
use VuFind\Record\Router as RecordRouter;
use VuFind\Search\Base\Results;
use VuFind\Search\History;
use VuFind\Search\History as SearchHistory;
use VuFind\Search\Memory as SearchMemory;
use VuFind\Search\Options\PluginManager as SearchOptionsPluginManager;
use VuFind\Search\Results\PluginManager as ResultsPluginManager;
use VuFind\Search\ResultScroller;
use VuFind\Search\SearchNormalizer;
use VuFind\Search\SearchRunner;
use VuFind\ServiceManager\Factory\Autowire;
use VuFind\View\Helper\Root\ResultFeed;
use VuFindSearch\Query\WorkKeysQuery;
use VuFindTheme\ThemeInfo;

use function is_callable;

/**
 * Search record versions action.
 *
 * @category VuFind
 * @package  Action
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class VersionsAction extends AbstractSearchAndResultsAction
{
    /**
     * Current record ID.
     *
     * @var ?string
     */
    protected ?string $recordId = null;

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
     * @param RecordLoader               $recordLoader               Record loader
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
        protected RecordLoader $recordLoader,
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
     * Display results of versions search.
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
        // Don't save to history -- history page doesn't handle correctly:
        $this->saveToHistory = false;

        $callback = function ($runner, $params, $searchId) use ($request): void {
            $query = $params->getQuery();
            if ($query instanceof WorkKeysQuery) {
                $this->recordId = $query->getId();
            }
            $defaultCallback = is_callable([$this, 'getSearchSetupCallback'])
                ? $this->getSearchSetupCallback($request) : null;
            if (is_callable($defaultCallback)) {
                $defaultCallback($runner, $params, $searchId);
            }
            $options = $params->getOptions();
            $options->disableHighlighting();
            $options->spellcheckEnabled(false);
        };

        return $this->renderSearchResults($request, $response, $callback);
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
        if (null !== $this->recordId) {
            $templateParams['driver'] = $this->recordLoader->load($this->recordId, $this->getSearchClassId());
        }
        return $templateParams;
    }
}
