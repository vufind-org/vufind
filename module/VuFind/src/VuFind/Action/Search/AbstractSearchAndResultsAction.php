<?php

/**
 * Abstract base class for search actions.
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

use Exception;
use Laminas\Session\SessionManager;
use Laminas\Stdlib\Parameters;
use Psr\Http\Message\ResponseInterface;
use VuFind\Action\AbstractTemplateRenderingAction;
use VuFind\ActionHelper\RedirectHelper;
use VuFind\Auth\Manager as AuthManager;
use VuFind\Config\ConfigManager;
use VuFind\ContentBlock\BlockLoader;
use VuFind\Db\Entity\SearchEntityInterface;
use VuFind\Db\Service\PluginManager as DbServicePluginManager;
use VuFind\Db\Service\SearchServiceInterface;
use VuFind\Recommend\PluginManager as RecommendPluginManager;
use VuFind\Record\Router as RecordRouter;
use VuFind\Search\Base\Results;
use VuFind\Search\History as SearchHistory;
use VuFind\Search\Memory as SearchMemory;
use VuFind\Search\Options\PluginManager as SearchOptionsPluginManager;
use VuFind\Search\RecommendListener;
use VuFind\Search\Results\PluginManager as ResultsPluginManager;
use VuFind\Search\ResultScroller;
use VuFind\Search\SearchNormalizer;
use VuFind\Search\SearchRunner;
use VuFind\ServiceManager\Factory\Autowire;
use VuFind\Solr\Utils as SolrUtils;
use VuFind\View\FlashMessenger\FlashMessenger;
use VuFind\View\Helper\Root\ResultFeed;
use VuFindTheme\ThemeInfo;

use function count;
use function in_array;
use function intval;
use function is_array;

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
abstract class AbstractSearchAndResultsAction extends AbstractTemplateRenderingAction
{
    /**
     * Search class family to use.
     *
     * @var string
     */
    protected string $searchClassId = DEFAULT_SEARCH_BACKEND;

    /**
     * Should we save searches to history?
     *
     * @var bool
     */
    protected bool $saveToHistory = true;

    /**
     * Should we remember the search for breadcrumb purposes?
     *
     * @var bool
     */
    protected bool $rememberSearch = true;

    /**
     * Constructor.
     *
     * @param SearchRunner               $searchRunner               Search runner
     * @param ResultsPluginManager       $resultsPluginManager       Search results plugin manager
     * @param ResultScroller             $resultScroller             Result scroller
     * @param RecommendPluginManager     $recommendPluginManager     Recommendation plugin manager
     * @param SearchMemory               $searchMemory               Search memoy
     * @param BlockLoader                $blockLoader                Content block loader
     * @param FlashMessenger             $flashMessenger             Flash messenger
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
     */
    public function __construct(
        protected SearchRunner $searchRunner,
        protected ResultsPluginManager $resultsPluginManager,
        protected ResultScroller $resultScroller,
        protected RecommendPluginManager $recommendPluginManager,
        protected SearchMemory $searchMemory,
        protected BlockLoader $blockLoader,
        protected FlashMessenger $flashMessenger,
        protected ConfigManager $configManager,
        protected RecordRouter $recordRouter,
        protected SessionManager $sessionManager,
        #[Autowire(container: DbServicePluginManager::class)]
        protected SearchServiceInterface $searchService,
        protected AuthManager $authManager,
        protected SearchNormalizer $searchNormalizer,
        #[Autowire(container: 'ViewHelperManager')]
        protected ResultFeed $resultFeedHelper,
        protected ThemeInfo $themeInfo,
        protected SearchHistory $searchHistory,
        protected SearchOptionsPluginManager $searchOptionsPluginManager,
    ) {
        parent::__construct();
    }

    /**
     * Render search home page.
     *
     * @return ResponseInterface
     */
    protected function renderHomePage(): ResponseInterface
    {
        $blocks = $this->blockLoader->getFromSearchClassId($this->searchClassId);
        return $this->renderTemplate(
            $this->request,
            $this->response,
            $this->createTemplateParams(compact('blocks'))
        );
    }

    /**
     * Render advanced search page.
     *
     * @param ?callable $setupCallback Optional callback for setting up additional template parameters
     *
     * @return ResponseInterface
     */
    protected function renderAdvancedSearch(?callable $setupCallback = null): ResponseInterface
    {
        $templateParams = $this->createTemplateParams(
            [
                'options' => $this->getOptionsForClass(),
                'saved' => false,
            ]
        );
        if ($templateParams['options']->getAdvancedSearchAction() === null) {
            throw new \Exception('Advanced search not supported.');
        }

        // Handle request to edit existing saved search:
        // 'edit' query parameter is added for legacy template support; we use intval to ensure that
        // the correct type is passed to restoreAdvancedSearch.
        $searchId = intval($this->getQueryParam('sid') ?? $this->getQueryParam('edit') ?? 0);
        if ($searchId > 0) {
            $templateParams['saved'] = $this->restoreAdvancedSearch($searchId);
        }

        // If we have default filters, set them up as a fake "saved" search
        // to properly populate special controls on the advanced screen.
        if (!$templateParams['saved'] && count($templateParams['options']->getDefaultFilters()) > 0) {
            $templateParams['saved'] = $this->resultsPluginManager->get($this->searchClassId);
            $templateParams['saved']->getParams()->initFromRequest(
                new \Laminas\Stdlib\Parameters([])
            );
        }

        if ($setupCallback) {
            $templateParams = $setupCallback($templateParams);
        }

        return $this->renderTemplate($this->request, $this->response, $templateParams);
    }

    /**
     * Perform a search and render the results.
     *
     * @param ?callable $setupCallback Optional setup callback that overrides the default one
     *
     * @return ResponseInterface
     */
    protected function renderSearchResults(?callable $setupCallback = null): ResponseInterface
    {
        $templateParams = $this->createTemplateParams();
        $config = $this->configManager->getConfigArray($this->getOptionsForClass()->getFacetsIni());
        $templateParams['multiFacetsSelection'] = static::getMultiSelectionValueFromConfig($config);
        $extraErrors = [];

        // Handle saved search requests:
        $savedId = $this->getQueryParam('saved');
        if ($savedId !== null) {
            return $this->redirectToSavedSearch((int)$savedId);
        }

        // Send both GET and POST variables to search class:
        $templateParams['request'] = $request = $this->request->getQueryParams() + $this->request->getParsedBody();

        $lastView = $this->searchMemory->retrieveLastSetting($this->searchClassId, 'view');
        try {
            $templateParams['results'] = $results = $this->searchRunner->run(
                $request,
                $this->searchClassId,
                $setupCallback ?: $this->getSearchSetupCallback(),
                $lastView
            );
        } catch (\VuFindSearch\Backend\Exception\DeepPagingException $e) {
            return $this->redirectToLegalSearchPage($request, $e->getLegalPage());
        }
        $templateParams['params'] = $params = $results->getParams();

        // For page parameter being out of results list, we want to redirect to correct page
        $page = $params->getPage();
        $totalResults = $results->getResultTotal();
        $limit = $params->getLimit();
        $lastPage = $limit ? ceil($totalResults / $limit) : 1;
        if ($totalResults > 0 && $page > $lastPage) {
            $queryParams = $request;
            $queryParams['page'] = $lastPage;
            return $this->getHelper(RedirectHelper::class)->redirectToRoute(
                $this->response,
                $params->getOptions()->getSearchAction(),
                queryParams: $queryParams
            );
        }

        // If we received an EmptySet back, that indicates that the real search
        // failed due to some kind of syntax error, and we should display a
        // warning to the user; otherwise, we should proceed with normal post-search
        // processing.
        if ($results instanceof \VuFind\Search\EmptySet\Results) {
            $templateParams['parseError'] = true;
        } else {
            // If a "jumpto" parameter is set, deal with that now:
            if ($jump = $this->processJumpTo($results)) {
                return $jump;
            }

            // Remember the current URL as the last search.
            $this->rememberSearch($results);

            // Add to search history:
            if ($this->saveToHistory) {
                $this->saveSearchToHistory($results);
            }

            // Jump to only result, if configured:
            if ($jump = $this->processJumpToOnlyResult($results)) {
                return $jump;
            }

            // Set up results scroller:
            if ($results->getOptions()->resultScrollerActive()) {
                $this->resultScroller->init($results);
            }

            foreach ($results->getErrors() as $error) {
                try {
                    $this->flashMessenger->addErrorMessage($error);
                } catch (\Exception $e) {
                    // The flash messenger will throw an exception if session writes are disabled,
                    // which will happen in combined search AJAX requests. For that situation, we'll
                    // pass error messages through to the template so they can still be displayed.
                    $extraErrors[] = $error;
                }
            }
        }

        // Special case: If we're in RSS view, we need to render differently:
        if (isset($templateParams['params']) && $templateParams['params']?->getView() == 'rss') {
            return $this->getRssSearchResponse($templateParams);
        }

        // Schedule options for footer tools
        $templateParams['scheduleOptions'] = $this->searchHistory->getScheduleOptions();
        $templateParams['saveToHistory'] = $this->saveToHistory;

        // Add extra errors, if necessary:
        if (count($extraErrors) > 0) {
            $templateParams['extraErrors'] = $extraErrors;
        }
        return $this->renderTemplate($this->request, $this->response, $templateParams);
    }

    /**
     * Returns a list of all items associated with one facet for the lightbox.
     *
     * Parameters:
     * facet        The facet to retrieve
     * searchParams Facet search params from $results->getUrlQuery()->getParams()
     *
     * @return ResponseInterface
     */
    public function renderFacetList(): ResponseInterface
    {
        $this->disableSessionWrites();  // avoid session write timing bug
        // Get results
        $results = $this->resultsPluginManager->get($this->searchClassId);
        $params = $results->getParams();
        $params->initFromRequest(new Parameters($this->request->getQueryParams()));
        // Get parameters
        $facet = $this->getQueryParam('facet', '');
        $contains = $this->getQueryParam('contains', '');
        $page = (int)$this->getQueryParam('facetpage', '1');
        // Has the request been sent in an AJAX context?
        $ajax = (int)$this->getQueryParam('ajax', 0);
        $urlBase = $this->getQueryParam('urlBase', '');
        $searchAction = $this->getQueryParam('searchAction', '');
        // $urlBase and $searchAction should be relative URLs; if there is an
        // absolute URL passed in, this may be a sign of malicious activity and
        // we should fail.
        if (str_contains($urlBase . $searchAction, '://')) {
            throw new \Exception('Unexpected absolute URL found.');
        }
        $options = $results->getOptions();
        $facetSortOptions = $options->getFacetSortOptions($facet);
        $sort = $this->getQueryParam('facetsort');
        if ($sort === null || !in_array($sort, array_keys($facetSortOptions))) {
            $sort = empty($facetSortOptions)
                ? 'count'
                : current(array_keys($facetSortOptions));
        }
        $config = $this->configManager->getConfigArray($options->getFacetsIni());
        $limit = $config['Results_Settings']['lightboxLimit'] ?? 50;
        $limit = (int)$this->getQueryParam('facetlimit', $limit);
        if (!empty($contains)) {
            $params->setFacetContains($contains);
            $params->setFacetContainsIgnoreCase(true);
        }
        $facets = $results->getPartialFieldFacets(
            [$facet],
            false,
            $limit,
            $sort,
            $page,
            $this->getQueryParam('facetop', 'AND') == 'OR'
        );
        $list = $facets[$facet]['data']['list'] ?? [];
        $facetLabel = $params->getFacetLabel($facet);

        $templateParams = [
            'contains' => $contains,
            'data' => $list,
            'exclude' => intval($this->getQueryParam('facetexclude', '0')),
            'facet' => $facet,
            'facetLabel' => $facetLabel,
            'operator' => $this->getQueryParam('facetop', 'AND'),
            'page' => $page,
            'results' => $results,
            'anotherPage' => $facets[$facet]['more'] ?? '',
            'sort' => $sort,
            'sortOptions' => $facetSortOptions,
            'baseUriExtra' => $this->getQueryParam('baseUriExtra'),
            'active' => $sort,
            'key' => $sort,
            'urlBase' => $urlBase,
            'searchAction' => $searchAction,
            'multiFacetsSelection' => static::getMultiSelectionValueFromConfig($config),
        ];
        $templateParams['delegateParams'] = $templateParams;
        return $this->renderTemplate(
            $this->request,
            $this->response,
            $this->createTemplateParams($templateParams),
            $ajax ? 'search/facet-list-content' : 'search/facet-list'
        );
    }

    /**
     * Create an array of template parameters.
     *
     * @param array $params Parameters to pass to template renderer.
     *
     * @return array
     */
    protected function createTemplateParams(array $params = []): array
    {
        $params['searchClassId'] = $this->searchClassId;
        return $params;
    }

    /**
     * Given a saved search ID, redirect the user to the appropriate place.
     *
     * @param int $id ID from search history
     *
     * @return ResponseInterface
     */
    protected function redirectToSavedSearch(int $id): ResponseInterface
    {
        $search = $this->retrieveSearchSecurely($id);
        if (empty($search)) {
            // User is trying to view a saved search from another session
            // (deliberate or expired) or associated with another user.
            throw new \Exception('Attempt to access invalid search ID');
        }

        // If we got this far, the user is allowed to view the search, so we can
        // deminify it to a new object.
        $savedSearch = $search->getSearchObject()?->deminify($this->resultsPluginManager);
        if (!$savedSearch) {
            throw new Exception("Problem getting search object from search {$search->getId()}.");
        }

        // Now redirect to the URL associated with the saved search; this
        // simplifies problems caused by mixing different classes of search
        // object, and it also prevents the user from ever landing on a
        // "?saved=xxxx" URL, which may not persist beyond the current session.
        // (We want all searches to be persistent and bookmarkable).
        return $this->getHelper(RedirectHelper::class)->redirectToRoute(
            $this->response,
            $savedSearch->getOptions()->getSearchAction(),
            queryParams: $savedSearch->getUrlQuery()->getParamArray()
        );
    }

    /**
     * Store the URL of the provided search (if appropriate).
     *
     * @param Results $results Search results object
     *
     * @return void
     */
    protected function rememberSearch(Results $results): void
    {
        // Only save search URL if the property tells us to...
        if ($this->rememberSearch) {
            $searchUrl = $this->routeHelper->getUrlFromRoute(
                $results->getOptions()->getSearchAction(),
                $results->getUrlQuery()->getParamArray()
            );
            $this->searchMemory->rememberSearch($searchUrl, $results->getSearchId());
        }

        // Always save search parameters, since these are namespaced by search
        // class ID.
        $this->searchMemory->rememberParams($results->getParams());
    }

    /**
     * Get active recommendation module settings.
     *
     * @return array
     */
    protected function getActiveRecommendationSettings()
    {
        // Enable recommendations unless explicitly told to disable them:
        $all = ['top', 'side', 'noresults', 'bottom'];
        $noRecommend = $this->getQueryParam('noRecommend', '');
        if (in_array($noRecommend, [1, '1', 'true', true], true)) {
            return [];
        } elseif (in_array($noRecommend, [0, '0', 'false', false], true)) {
            return $all;
        }
        return array_diff(
            $all,
            array_map('trim', explode(',', strtolower($noRecommend)))
        );
    }

    /**
     * Get a callback for setting up a search (or null if callback is unnecessary).
     *
     * @return mixed
     */
    protected function getSearchSetupCallback()
    {
        // Setup callback to attach listener if appropriate:
        $activeRecs = $this->getActiveRecommendationSettings();
        if (empty($activeRecs)) {
            return null;
        }

        $override = $this->getQueryParam('recommendOverride');

        // Retrieve recommend settings from params object:
        return function ($runner, $params, $searchId) use ($activeRecs, $override): void {
            $listener = new RecommendListener($this->recommendPluginManager, $searchId);
            $config = [];
            $rawConfig = $params->getOptions()
                ->getRecommendationSettings($params->getSearchHandler());
            foreach ($rawConfig as $key => $value) {
                if (in_array($key, $activeRecs)) {
                    $config[$key] = $value;
                }
            }

            // Special case: override recommend settings through parameter (used by
            // combined search)
            if (is_array($override)) {
                $config = array_merge($config, $override);
            }

            $listener->setConfig($config);
            $listener->attach($runner->getEventManager()->getSharedManager());
        };
    }

    /**
     * If the search backend has thrown a "deep paging" exception, we should show a
     * flash message and redirect the user to a legal page.
     *
     * @param array $request Incoming request parameters
     * @param int   $page    Legal page number
     *
     * @return mixed
     */
    protected function redirectToLegalSearchPage(array $request, int $page)
    {
        if (($request['page'] ?? 0) <= $page) {
            throw new \Exception('Unrecoverable deep paging error.');
        }
        $request['page'] = $page;
        $this->flashMessenger->addErrorMessage(
            [
                'msg' => 'deep_paging_failure',
                'tokens' => ['%%page%%' => $page],
            ]
        );
        return $this->getRedirectResponse($this->response, '?' . http_build_query($request));
    }

    /**
     * Support method for renderSearchResults() -- return the search results reformatted as an RSS feed.
     *
     * @param $templateParams Template parameters
     *
     * @return ResponseInterface
     */
    protected function getRssSearchResponse(array $templateParams): ResponseInterface
    {
        // Build the RSS feed:
        $feed = ($this->resultFeedHelper)($templateParams['results']);
        $writer = new \Laminas\Feed\Writer\Renderer\Feed\Rss($feed);
        $writer->render();

        // Apply XSLT if we can find a relevant file:
        $themeHits = $this->themeInfo->findInThemes('assets/xsl/rss.xsl');
        if ($themeHits) {
            $xsl = $this->routeHelper->getUrlFromRoute('home') . 'themes/'
                . $themeHits[0]['theme'] . '/' . $themeHits[0]['relativeFile'];
            $writer->getElement()->parentNode->insertBefore(
                $writer->getDomDocument()->createProcessingInstruction(
                    'xml-stylesheet',
                    'type="text/xsl" href="' . $xsl . '"'
                ),
                $writer->getElement()
            );
        }

        // Format the response:
        $response = $this->response;
        $response = $response->withAddedHeader('Content-Type', 'text/xml');
        $response->getBody()->write($writer->saveXml());
        return $response;
    }

    /**
     * Get the value multiFacetsSelection from the config.
     *
     * @param array $config The config containing multiFacetsSelection
     *
     * @return string
     */
    protected static function getMultiSelectionValueFromConfig(array $config): string
    {
        $multiFacetsSelection = $config['Results_Settings']['multiFacetsSelection'] ?? 'false';
        return match ($multiFacetsSelection) {
            true, '1' => 'true',
            false, '', '0' => 'false',
            default => $multiFacetsSelection,
        };
    }

    /**
     * Process the jumpto parameter -- either redirect to a specific record, or ignore the parameter and return null.
     *
     * @param Results $results Search results object.
     *
     * @return ?ResponseInterface
     */
    protected function processJumpTo(Results $results): ?ResponseInterface
    {
        // Missing/invalid parameter?  Ignore it:
        $jumpto = $this->getQueryParam('jumpto');
        if (empty($jumpto) || !is_numeric($jumpto)) {
            return null;
        }

        $recordList = $results->getResults();
        return isset($recordList[$jumpto - 1])
            ? $this->getRedirectForRecord($recordList[$jumpto - 1])
            : null;
    }

    /**
     * Process jump to record if there is only one result.
     *
     * @param Results $results Search results object.
     *
     * @return ?ResponseInterface
     */
    protected function processJumpToOnlyResult(Results $results): ?ResponseInterface
    {
        // If jumpto is explicitly disabled (set to false, e.g. by combined search),
        // we should NEVER jump to a result regardless of other factors.
        $jumpto = $this->getQueryParam('jumpto', '1');
        if (
            $jumpto
            && ($this->configManager->getConfigArray('config')['Record']['jump_to_single_search_result'] ?? false)
            && $results->getResultTotal() == 1
            && $recordList = $results->getResults()
        ) {
            return $this->getRedirectForRecord(
                reset($recordList),
                ['sid' => $results->getSearchId()]
            );
        }

        return null;
    }

    /**
     * Get a redirection response to a single record.
     *
     * @param \VuFind\RecordDriver\AbstractBase $record      Record driver
     * @param array                             $queryParams Any query parameters
     *
     * @return ResponseInterface
     */
    protected function getRedirectForRecord(
        \VuFind\RecordDriver\AbstractBase $record,
        array $queryParams = []
    ): ResponseInterface {
        $details = $this->recordRouter->getTabRouteDetails($record);
        return $this->getHelper(RedirectHelper::class)->redirectToRoute(
            $this->response,
            $details['route'],
            $details['params'],
            array_merge_recursive(
                $details['options']['query'] ?? [],
                $queryParams
            )
        );
    }

    /**
     * Get a saved search, enforcing user ownership. Returns row if found, null
     * otherwise.
     *
     * @param int $searchId Primary key value
     *
     * @return ?SearchEntityInterface
     */
    protected function retrieveSearchSecurely(int $searchId): ?SearchEntityInterface
    {
        $sessId = $this->sessionManager->getId();
        return $this->searchService->getSearchByIdAndOwner($searchId, $sessId, $this->authManager->getUserObject());
    }

    /**
     * Save a search to the history in the database.
     *
     * @param Results $results Search results
     *
     * @return void
     */
    protected function saveSearchToHistory(Results $results): void
    {
        $sessId = $this->sessionManager->getId();
        $this->searchNormalizer->saveNormalizedSearch(
            $results,
            $sessId,
            $this->authManager->getUserObject()?->getId()
        );
    }

    /**
     * Get the requested search object or add a flash message indicating why the operation failed.
     *
     * @param int $searchId ID value of a saved advanced search.
     *
     * @return ?Results Restored search object if found, null otherwise.
     */
    protected function restoreAdvancedSearch(int $searchId): ?Results
    {
        // Look up search in database and fail if it is not found:
        $search = $this->retrieveSearchSecurely($searchId);
        if (empty($search)) {
            $this->flashMessenger->addErrorMessage('advSearchError_notFound');
            return null;
        }

        // Restore the full search object:
        $savedSearch = $search->getSearchObject()?->deminify($this->resultsPluginManager);
        if (!$savedSearch) {
            throw new Exception("Problem getting search object from search {$search->getId()}.");
        }

        // Fail if this is not the right type of search:
        if ($savedSearch->getParams()->getSearchType() != 'advanced') {
            try {
                $savedSearch->getParams()->convertToAdvancedSearch();
            } catch (\Exception $ex) {
                $this->flashMessenger->addErrorMessage('advSearchError_notAdvanced');
                return null;
            }
        }

        return $savedSearch;
    }

    /**
     * Get the current settings for the specified range facet, if it is set:
     *
     * @param array    $fields      Fields to check
     * @param string   $type        Type of range to include in return value
     * @param ?Results $savedSearch Saved search object (null if none)
     *
     * @return array
     */
    protected function getRangeSettings(array $fields, string $type, ?Results $savedSearch = null): array
    {
        $parts = [];

        foreach ($fields as $field) {
            // Default to blank strings:
            $from = $to = '';

            // Check to see if there is an existing range in the search object:
            if ($savedSearch) {
                $filters = $savedSearch->getParams()->getRawFilters();
                foreach ($filters[$field] ?? [] as $current) {
                    if ($range = SolrUtils::parseRange($current)) {
                        $from = $range['from'] == '*' ? '' : $range['from'];
                        $to = $range['to'] == '*' ? '' : $range['to'];
                        $savedSearch->getParams()
                            ->removeFilter($field . ':' . $current);
                        break;
                    }
                }
            }

            // Send back the settings:
            $parts[] = [
                'field' => $field,
                'type' => $type,
                'values' => [$from, $to],
            ];
        }

        return $parts;
    }

    /**
     * Get the range facet configurations from the specified config section and
     * filter them appropriately.
     *
     * @param string $config  Name of config file
     * @param string $section Configuration section to check
     * @param array  $filter  List of fields to include (if empty, all fields will be returned)
     *
     * @return array
     */
    protected function getRangeFieldList(string $config, string $section, array $filter): array
    {
        $config = $this->configManager->getConfigArray($config);
        $fields = $config['SpecialFacets'][$section] ?? [];

        if (!empty($filter)) {
            $fields = array_intersect($fields, $filter);
        }

        return $fields;
    }

    /**
     * Get the current settings for the date range facets, if set:
     *
     * @param ?Results $savedSearch Saved search object (null if none)
     * @param string   $config      Name of config file
     * @param array    $filter      List of fields to include (if empty, all fields will be returned)
     *
     * @return array
     */
    protected function getDateRangeSettings(
        ?Results $savedSearch = null,
        string $config = 'facets',
        array $filter = []
    ): array {
        $fields = $this->getRangeFieldList($config, 'dateRange', $filter);
        return $this->getRangeSettings($fields, 'date', $savedSearch);
    }

    /**
     * Get the current settings for the full date range facets, if set:
     *
     * @param ?Results $savedSearch Saved search object (null if none)
     * @param string   $config      Name of config file
     * @param array    $filter      List of fields to include (if empty, all fields will be returned)
     *
     * @return array
     */
    protected function getFullDateRangeSettings(
        ?Results $savedSearch = null,
        string $config = 'facets',
        array $filter = []
    ): array {
        $fields = $this->getRangeFieldList($config, 'fullDateRange', $filter);
        return $this->getRangeSettings($fields, 'fulldate', $savedSearch);
    }

    /**
     * Get the current settings for the generic range facets, if set:
     *
     * @param ?Results $savedSearch Saved search object (null if none)
     * @param string   $config      Name of config file
     * @param array    $filter      List of fields to include (if empty, all fields will be returned)
     *
     * @return array
     */
    protected function getGenericRangeSettings(
        ?Results $savedSearch = null,
        string $config = 'facets',
        array $filter = []
    ): array {
        $fields = $this->getRangeFieldList($config, 'genericRange', $filter);
        return $this->getRangeSettings($fields, 'generic', $savedSearch);
    }

    /**
     * Get the current settings for the numeric range facets, if set:
     *
     * @param ?Results $savedSearch Saved search object (false if none)
     * @param string   $config      Name of config file
     * @param array    $filter      List of fields to include (if empty, all fields will be returned)
     *
     * @return array
     */
    protected function getNumericRangeSettings(
        ?Results $savedSearch = null,
        string $config = 'facets',
        array $filter = []
    ): array {
        $fields = $this->getRangeFieldList($config, 'numericRange', $filter);
        return $this->getRangeSettings($fields, 'numeric', $savedSearch);
    }

    /**
     * Get all active range facets:
     *
     * @param array    $specialFacets Special facet setting (in parsed format)
     * @param ?Results $savedSearch   Saved search object (null if none)
     * @param string   $config        Name of config file
     *
     * @return array
     */
    protected function getAllRangeSettings(
        array $specialFacets,
        ?Results $savedSearch = null,
        string $config = 'facets'
    ): array {
        $result = [];
        if (isset($specialFacets['daterange'])) {
            $dates = $this->getDateRangeSettings(
                $savedSearch,
                $config,
                $specialFacets['daterange']
            );
            $result = array_merge($result, $dates);
        }
        if (isset($specialFacets['fulldaterange'])) {
            $fulldates = $this->getFullDateRangeSettings(
                $savedSearch,
                $config,
                $specialFacets['fulldaterange']
            );
            $result = array_merge($result, $fulldates);
        }
        if (isset($specialFacets['genericrange'])) {
            $generic = $this->getGenericRangeSettings(
                $savedSearch,
                $config,
                $specialFacets['genericrange']
            );
            $result = array_merge($result, $generic);
        }
        if (isset($specialFacets['numericrange'])) {
            $numeric = $this->getNumericRangeSettings(
                $savedSearch,
                $config,
                $specialFacets['numericrange']
            );
            $result = array_merge($result, $numeric);
        }
        return $result;
    }

    /**
     * Parse the "special facets" setting.
     *
     * @param string $specialFacets Unparsed string
     *
     * @return array
     */
    protected function parseSpecialFacetsSetting(string $specialFacets): array
    {
        // Parse the special facets into a more useful format:
        $parsed = [];
        foreach (explode(',', $specialFacets) as $current) {
            $parts = explode(':', $current);
            $key = array_shift($parts);
            $parsed[$key] = $parts;
        }
        return $parsed;
    }

    /**
     * Process the checkbox setting from special facets.
     *
     * @param array    $params      Parameters to the checkbox setting
     * @param ?Results $savedSearch Saved search object (null if none)
     *
     * @return array
     */
    protected function processAdvancedCheckboxes(array $params, ?Results $savedSearch = null): array
    {
        // Set defaults for missing parameters:
        $config = $params[0] ?? 'facets';
        $section = $params[1] ?? 'CheckboxFacets';

        // Load config file:
        $config = $this->configManager->getConfigArray($config);

        // Process checkbox settings in config:
        $flipCheckboxes = false;
        if (str_starts_with($section, '~')) {        // reverse flag
            $section = substr($section, 1);
            $flipCheckboxes = true;
        }
        $checkboxFacets = ($section && isset($config[$section])) ? $config[$section] : [];
        if ($flipCheckboxes) {
            $checkboxFacets = array_flip($checkboxFacets);
        }

        // Reformat for convenience:
        $formatted = [];
        foreach ($checkboxFacets as $filter => $desc) {
            $current = compact('desc', 'filter');
            $current['selected']
                = $savedSearch && $savedSearch->getParams()->hasFilter($filter);
            // We don't want to double-display checkboxes on advanced search, so
            // if they are checked, we should remove them from the object to
            // prevent display in the "other filters" area.
            if ($current['selected']) {
                $savedSearch->getParams()->removeFilter($filter);
            }
            $formatted[] = $current;
        }

        return $formatted;
    }

    /**
     * Get proper options file for search class.
     *
     * @return \VuFind\Search\Base\Options
     */
    protected function getOptionsForClass(): \VuFind\Search\Base\Options
    {
        return $this->searchOptionsPluginManager->get($this->searchClassId);
    }
}
