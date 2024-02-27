<?php

/**
 * "Get Search Results" AJAX handler
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  AJAX
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\AjaxHandler;

use Laminas\Mvc\Controller\Plugin\Params;
use Laminas\Stdlib\Parameters;
use Laminas\View\Model\ViewModel;
use Laminas\View\Renderer\PhpRenderer;
use VuFind\Db\Row\User as UserRow;
use VuFind\Db\Table\Search;
use VuFind\Record\Loader as RecordLoader;
use VuFind\Search\Base\Results;
use VuFind\Search\Results\PluginManager as ResultsManager;
use VuFind\Search\SearchNormalizer;
use VuFind\Session\Settings as SessionSettings;

use function call_user_func;

/**
 * "Get Search Results" AJAX handler
 *
 * @category VuFind
 * @package  AJAX
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class GetSearchResults extends \VuFind\AjaxHandler\AbstractBase implements
    \Laminas\Log\LoggerAwareInterface,
    \VuFind\I18n\Translator\TranslatorAwareInterface
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;
    use \VuFind\Log\LoggerAwareTrait;

    /**
     * ResultsManager
     *
     * @var resultsManager
     */
    protected $resultsManager;

    /**
     * View renderer
     *
     * @var PhpRenderer
     */
    protected $renderer;

    /**
     * Record loader
     *
     * @var RecordLoader
     */
    protected $recordLoader;

    /**
     * Logged-in user
     *
     * @var ?UserRow
     */
    protected $user;

    /**
     * Session ID
     *
     * @var string
     */
    protected $sessionId;

    /**
     * Search normalizer
     *
     * @var SearchNormalizer
     */
    protected $searchNormalizer;

    /**
     * Search table
     *
     * @var SearchTable
     */
    protected $searchTable;

    /**
     * Main configuration
     *
     * @var array
     */
    protected $config;

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
     * Constructor
     *
     * @param SessionSettings  $sessionSettings Session settings
     * @param ResultsManager   $resultsManager  Results Manager
     * @param PhpRenderer      $renderer        View renderer
     * @param RecordLoader     $recordLoader    Record loader
     * @param ?UserRow         $user            Logged-in user
     * @param string           $sessionId       Session ID
     * @param SearchNormalizer $normalizer      Search normalizer
     * @param SearchTable      $searchTable     Search table
     * @param array            $config          Main configuration
     */
    public function __construct(
        SessionSettings $sessionSettings,
        ResultsManager $resultsManager,
        PhpRenderer $renderer,
        RecordLoader $recordLoader,
        ?UserRow $user,
        string $sessionId,
        SearchNormalizer $normalizer,
        Search $searchTable,
        array $config
    ) {
        $this->sessionSettings = $sessionSettings;
        $this->resultsManager = $resultsManager;
        $this->renderer = $renderer;
        $this->recordLoader = $recordLoader;
        $this->user = $user;
        $this->sessionId = $sessionId;
        $this->searchNormalizer = $normalizer;
        $this->searchTable = $searchTable;
        $this->config = $config;
    }

    /**
     * Handle a request.
     *
     * @param Params $params Parameter helper from controller
     *
     * @return array [response data, HTTP status code]
     */
    public function handleRequest(Params $params)
    {
        $results = $this->getSearchResults($params);
        if (!$results) {
            return $this->formatResponse(['error' => 'Invalid request'], 400);
        }
        $elements = $this->getElements($params, $results);
        return $this->formatResponse(compact('elements'));
    }

    /**
     * Get search results
     *
     * @param Params $params Request params
     *
     * @return ?Results
     */
    protected function getSearchResults(Params $params): ?Results
    {
        parse_str($params->fromQuery('querystring', ''), $searchParams);
        $backend = $params->fromQuery('source', DEFAULT_SEARCH_BACKEND);

        $results = $this->resultsManager->get($backend);
        $paramsObj = $results->getParams();
        $paramsObj->getOptions()->spellcheckEnabled(false);
        $paramsObj->initFromRequest(new Parameters($searchParams));

        if ($params->fromQuery('history')) {
            $this->saveSearchToHistory($results);
        }

        return $results;
    }

    /**
     * Render page elements
     *
     * @param Params  $params  Request params
     * @param Results $results Search results
     *
     * @return array
     */
    protected function getElements(Params $params, Results $results): array
    {
        $result = [];
        foreach ($this->elements as $selector => $element) {
            $content = call_user_func([$this, $element['method']], $params, $results);
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
     * Render search results
     *
     * @param Params  $params  Request params
     * @param Results $results Search results
     *
     * @return ?string
     */
    protected function renderResults(Params $params, Results $results): ?string
    {
        [$baseAction] = explode('-', $results->getOptions()->getSearchAction());
        $templatePath = "$baseAction/results-list.phtml";
        if ('search' !== $baseAction && !$this->renderer->resolver($templatePath)) {
            $templatePath = 'search/results-list.phtml';
        }
        $params = $results->getParams();
        $options = $results->getOptions();
        $cart = $this->renderer->plugin('cart');
        $showBulkOptions = $options->supportsCart()
            && ($this->config['Site']['showBulkOptions'] ?? false);
        // Checkboxes if appropriate:
        $showCartControls = $options->supportsCart()
            && $cart()->isActive()
            && ($showBulkOptions || !$cart()->isActiveInSearch());
        // Enable bulk options if appropriate:
        $showCheckboxes = $showCartControls || $showBulkOptions;

        return $this->renderer->render(
            $templatePath,
            compact(
                'results',
                'params',
                'showBulkOptions',
                'showCartControls',
                'showCheckboxes',
            )
        );
    }

    /**
     * Render pagination
     *
     * @param Params  $params   Request params
     * @param Results $results  Search results
     * @param string  $template Paginator template
     * @param string  $ulClass  Additional class for the pagination container
     * @param string  $navClass Additional class for the nav element
     *
     * @return ?string
     */
    protected function renderPagination(
        Params $params,
        Results $results,
        string $template = 'search/pagination.phtml',
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
        $pagination = $this->renderer->plugin('paginationControl');
        return $pagination(
            $results->getPaginator(),
            'Sliding',
            $template,
            ['results' => $results, 'options' => $paginationOptions]
        );
    }

    /**
     * Render simple pagination
     *
     * @param Params  $params  Request params
     * @param Results $results Search results
     *
     * @return ?string
     */
    protected function renderPaginationSimple(Params $params, Results $results): ?string
    {
        return $this->renderPagination($params, $results, 'search/pagination_simple.phtml');
    }

    /**
     * Render top pagination
     *
     * @param Params  $params  Request params
     * @param Results $results Search results
     *
     * @return ?string
     */
    protected function renderPaginationTop(Params $params, Results $results): ?string
    {
        return $this->renderPagination($params, $results, 'search/pagination-top.phtml');
    }

    /**
     * Render search stats
     *
     * @param Params  $params  Request params
     * @param Results $results Search results
     *
     * @return ?string
     */
    protected function renderSearchStats(Params $params, Results $results): ?string
    {
        if (!($statsKey = $params->fromQuery('statsKey'))) {
            return null;
        }

        $localizedNumber = $this->renderer->plugin('localizedNumber');
        $escapeHtml = $this->renderer->plugin('escapeHtml');
        $lookfor = $results->getUrlQuery()->isQuerySuppressed()
            ? '' : $results->getParams()->getDisplayQuery();
        $transParams = [
            '%%start%%' => $localizedNumber($results->getStartRecord()),
            '%%end%%' => $localizedNumber($results->getEndRecord()),
            '%%total%%' => $localizedNumber($results->getResultTotal()),
            '%%lookfor%%' => $escapeHtml($lookfor),
        ];

        return $this->translate($statsKey, $transParams);
    }

    /**
     * Render analytics
     *
     * @param Params  $params  Request params
     * @param Results $results Search results
     *
     * @return ?string
     */
    protected function renderAnalytics(Params $params, Results $results): ?string
    {
        // Mimic the typical page structure so that analytics helpers can find the
        // search results:
        $view = new ViewModel();
        $view->setTemplate('Helpers/analytics.phtml');
        $view->addChild(new ViewModel(compact('results')));
        return $this->renderer->render($view);
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
        $this->searchTable->saveSearch(
            $this->searchNormalizer,
            $results,
            $this->sessionId,
            $this->user->id ?? null
        );
    }
}
