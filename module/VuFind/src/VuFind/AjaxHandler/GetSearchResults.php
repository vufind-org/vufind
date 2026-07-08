<?php

/**
 * "Get Search Results" AJAX handler.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2023.
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
 * @package  AJAX
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\AjaxHandler;

use Laminas\Stdlib\Parameters;
use Laminas\View\Helper\EscapeHtml;
use Laminas\View\Helper\PaginationControl;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\Cart;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Record\Loader as RecordLoader;
use VuFind\Search\Base\Results;
use VuFind\Search\Memory;
use VuFind\Search\Results\PluginManager as ResultsManager;
use VuFind\Search\ResultScroller;
use VuFind\Search\SearchNormalizer;
use VuFind\Session\Settings as SessionSettings;
use VuFind\View\Helper\Root\LocalizedNumber;
use VuFind\View\Renderer\TemplateRendererInterface;

use function call_user_func;

/**
 * "Get Search Results" AJAX handler.
 *
 * @category VuFind
 * @package  AJAX
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class GetSearchResults extends \VuFind\AjaxHandler\AbstractBase implements
    \Psr\Log\LoggerAwareInterface,
    \VuFind\I18n\Translator\TranslatorAwareInterface
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;
    use \VuFind\Log\LoggerAwareTrait;

    /**
     * Elements to render for each search results page.
     *
     * Note that results list is last before scripts so that we update most controls
     * before hiding the loading indicator (in practice this only affects tests).
     *
     * Key is a selector that finds all elements to update.
     * Value is an associative array with the following keys:
     *
     *   method  Method to create the response content
     *   target  Target attribute in the element for the content
     *           (inner for innerHTML, outer for outerHTML or null for none)
     *   attrs   New attributes for the element
     *
     * @var array
     */
    protected $elements = [
        '.js-pagination.js-pagination__top' => [
            'method' => 'renderPaginationTop',
            'target' => 'outer',
        ],
        '.js-pagination:not(.js-pagination__top)' => [
            'method' => 'renderPagination',
            'target' => 'outer',
        ],
        '.js-pagination-simple' => [
            'method' => 'renderPaginationSimple',
            'target' => 'outer',
        ],
        '.js-search-stats' => [
            'method' => 'renderSearchStats',
            'target' => 'inner',
            'attrs' => [
                'aria-live' => 'polite',
            ],
        ],
        '.js-result-list' => [
            'method' => 'renderResults',
            'target' => 'outer',
        ],
        'head' => [
            'method' => 'renderAnalytics',
            'target' => null,
        ],
    ];

    /**
     * Constructor.
     *
     * @param SessionSettings           $sessionSettings   Session settings
     * @param ResultsManager            $resultsManager    Results Manager
     * @param TemplateRendererInterface $renderer          Template renderer
     * @param RecordLoader              $recordLoader      Record loader
     * @param ?UserEntityInterface      $user              Logged-in user
     * @param string                    $sessionId         Session ID
     * @param SearchNormalizer          $searchNormalizer  Search normalizer
     * @param array                     $config            Main configuration
     * @param Memory                    $searchMemory      Search memory
     * @param ResultScroller            $resultScroller    Result scroller helper
     * @param Cart                      $cart              Cart service
     * @param PaginationControl         $paginationControl Pagination control view helper
     * @param LocalizedNumber           $localizedNumber   Localized number view helper
     * @param EscapeHtml                $escapeHtml        Escape HTML view helper
     */
    public function __construct(
        SessionSettings $sessionSettings,
        protected ResultsManager $resultsManager,
        protected TemplateRendererInterface $renderer,
        protected RecordLoader $recordLoader,
        protected ?UserEntityInterface $user,
        protected string $sessionId,
        protected SearchNormalizer $searchNormalizer,
        protected array $config,
        protected Memory $searchMemory,
        protected ResultScroller $resultScroller,
        protected Cart $cart,
        protected PaginationControl $paginationControl,
        protected LocalizedNumber $localizedNumber,
        protected EscapeHtml $escapeHtml,
    ) {
        parent::__construct($sessionSettings);
    }

    /**
     * Handle a request.
     *
     * @param ServerRequestInterface $request Request
     *
     * @return array [response data, HTTP status code]
     */
    public function handleRequest(ServerRequestInterface $request): array
    {
        $results = $this->getSearchResults($request);
        if (!$results) {
            return $this->formatResponse(['error' => 'Invalid request'], 400);
        }
        $elements = $this->getElements($request, $results);
        return $this->formatResponse(compact('elements'));
    }

    /**
     * Get search results.
     *
     * @param ServerRequestInterface $request Request
     *
     * @return ?Results
     */
    protected function getSearchResults(ServerRequestInterface $request): ?Results
    {
        parse_str($this->getQueryParam($request, 'querystring', ''), $searchParams);
        $backend = $this->getQueryParam($request, 'source', DEFAULT_SEARCH_BACKEND);

        $results = $this->resultsManager->get($backend);
        $paramsObj = $results->getParams();
        $paramsObj->getOptions()->spellcheckEnabled(false);
        $paramsObj->initFromRequest(new Parameters($searchParams));
        $results->performAndProcessSearch();

        // For a page parameter being out of the results list,
        // we want load the last page available
        $totalResults = $results->getResultTotal();
        $limit = $paramsObj->getLimit();
        $lastPage = $limit ? ceil($totalResults / $limit) : 1;
        if ($totalResults > 0 && $paramsObj->getPage() > $lastPage) {
            $paramsObj->setPage($lastPage);
            $results->performAndProcessSearch();
        }

        if ($this->getQueryParam($request, 'history')) {
            $this->saveSearchToHistory($results);
        }

        if ($results->getOptions()->resultScrollerActive()) {
            $this->resultScroller->init($results);
        }

        // Always save search parameters, since these are namespaced by search
        // class ID.
        $this->searchMemory->rememberParams($results->getParams());

        return $results;
    }

    /**
     * Render page elements.
     *
     * @param ServerRequestInterface $request Request
     * @param Results                $results Search results
     *
     * @return array
     */
    protected function getElements(ServerRequestInterface $request, Results $results): array
    {
        $result = [];
        foreach ($this->elements as $selector => $element) {
            $content = call_user_func([$this, $element['method']], $request, $results);
            if (null !== $content) {
                $result[$selector] = [
                    'content' => $content,
                    'target' => $element['target'],
                    'attrs' => $element['attrs'] ?? [],
                ];
            }
        }
        return $result;
    }

    /**
     * Render search results.
     *
     * @param ServerRequestInterface $request Request
     * @param Results                $results Search results
     *
     * @return ?string
     */
    protected function renderResults(ServerRequestInterface $request, Results $results): ?string
    {
        [$baseAction] = explode('-', $results->getOptions()->getSearchAction());
        $templatePath = "$baseAction/results-list.phtml";
        if ('search' !== $baseAction && !$this->renderer->resolveTemplateFilename($templatePath)) {
            $templatePath = 'search/results-list.phtml';
        }
        $options = $results->getOptions();
        $showBulkOptions = $options->supportsCart()
            && ($this->config['Site']['showBulkOptions'] ?? false);
        // Checkboxes if appropriate:
        $showCartControls = $options->supportsCart()
            && $this->cart->isActive()
            && ($showBulkOptions || !$this->cart->isActiveInSearch());
        // Enable bulk options if appropriate:
        $showCheckboxes = $showCartControls || $showBulkOptions;
        // Include request parameters:
        parse_str($this->getQueryParam($request, 'querystring', ''), $searchQueryParams);

        return $this->renderer->renderTemplateAsString(
            $request,
            $templatePath,
            [
                'request' => $searchQueryParams,
                'results' => $results,
                'params' => $results->getParams(),
                'showBulkOptions' => $showBulkOptions,
                'showCartControls' => $showCartControls,
                'showCheckboxes' => $showCheckboxes,
                'saveToHistory' => (bool)$this->getQueryParam($request, 'history', false),
            ]
        );
    }

    /**
     * Render pagination.
     *
     * @param ServerRequestInterface $request  Request
     * @param Results                $results  Search results
     * @param string                 $template Paginator template
     * @param string                 $ulClass  Additional class for the pagination container
     * @param string                 $navClass Additional class for the nav element
     *
     * @return ?string
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function renderPagination(
        ServerRequestInterface $request,
        Results $results,
        string $template = 'Helpers/pagination.phtml',
        string $ulClass = '',
        string $navClass = ''
    ): ?string {
        $paginationOptions = [];
        if ($ulClass) {
            $paginationOptions['className'] = $ulClass;
        }
        if ($navClass) {
            $paginationOptions['navClassName'] = $navClass;
        }
        return ($this->paginationControl)(
            $results->getPaginator(),
            'Sliding',
            $template,
            ['params' => ['results' => $results], 'options' => $paginationOptions]
        );
    }

    /**
     * Render simple pagination.
     *
     * @param ServerRequestInterface $request Request
     * @param Results                $results Search results
     *
     * @return ?string
     */
    protected function renderPaginationSimple(
        ServerRequestInterface $request,
        Results $results
    ): ?string {
        return $this->renderPagination($request, $results, 'Helpers/pagination-simple.phtml');
    }

    /**
     * Render top pagination.
     *
     * @param ServerRequestInterface $request Request
     * @param Results                $results Search results
     *
     * @return ?string
     */
    protected function renderPaginationTop(
        ServerRequestInterface $request,
        Results $results
    ): ?string {
        return $this->renderPagination($request, $results, 'Helpers/pagination-top.phtml');
    }

    /**
     * Render search stats.
     *
     * @param ServerRequestInterface $request Request
     * @param Results                $results Search results
     *
     * @return ?string
     */
    protected function renderSearchStats(
        ServerRequestInterface $request,
        Results $results
    ): ?string {
        if (!($statsKey = $this->getQueryParam($request, 'statsKey'))) {
            return null;
        }

        $lookfor = $results->getUrlQuery()->isQuerySuppressed()
            ? '' : $results->getParams()->getDisplayQuery();
        $transParams = [
            '%%start%%' => ($this->localizedNumber)($results->getStartRecord()),
            '%%end%%' => ($this->localizedNumber)($results->getEndRecord()),
            '%%total%%' => ($this->localizedNumber)($results->getResultTotal()),
            '%%lookfor%%' => ($this->escapeHtml)($lookfor),
        ];

        return $this->translate($statsKey, $transParams);
    }

    /**
     * Render analytics.
     *
     * @param ServerRequestInterface $request Request
     * @param Results                $results Search results
     *
     * @return ?string
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function renderAnalytics(
        ServerRequestInterface $request,
        Results $results
    ): ?string {
        // Mimic the typical page structure so that analytics helpers can find the
        // search results:
        return $this->renderer->renderTemplateAsString(
            $request,
            'Helpers/analytics.phtml',
            childTemplates: [
                [
                    'template' => 'layout/bare.phtml',
                    'params' => compact('results'),
                ],
            ]
        );
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
        $this->searchNormalizer->saveNormalizedSearch(
            $results,
            $this->sessionId,
            $this->user?->getId()
        );
    }
}
