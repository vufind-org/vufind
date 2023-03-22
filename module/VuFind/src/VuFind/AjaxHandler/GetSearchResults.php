<?php

/**
 * "Get Search Results" AJAX handler
 *
 * PHP version 7
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
use Laminas\View\Renderer\PhpRenderer;
use VuFind\Search\Base\Results;
use VuFind\Search\Results\PluginManager as ResultsManager;
use VuFind\Session\Settings as SessionSettings;

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
     * Elements to render for each search results page.
     *
     * @var array
     */
    protected $elements = [
        '.js-record-list' => [
            'method' => 'renderResults',
            'target' => 'outer',
        ],
        '.js-ajax-pagination' => [
            'method' => 'renderPagination',
            'target' => 'outer',
        ],
        '.js-search-stats' => [
            'method' => 'renderSearchStats',
            'target' => 'inner',
        ],
    ];

    /**
     * Constructor
     *
     * @param SessionSettings $sessionSettings Session settings
     * @param ResultsManager  $resultsManager  Results Manager
     * @param PhpRenderer     $renderer        View renderer
     */
    public function __construct(
        SessionSettings $sessionSettings,
        ResultsManager $resultsManager,
        PhpRenderer $renderer
    ) {
        $this->sessionSettings = $sessionSettings;
        $this->resultsManager = $resultsManager;
        $this->renderer = $renderer;
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
        $this->disableSessionWrites();  // avoid session write timing bug

        $results = $this->getSearchResults($params);
        $elements = $this->getElements($params, $results);
        return $this->formatResponse(compact('elements'));
    }

    /**
     * Get search results
     *
     * @param Params $params Request params
     *
     * @return Results
     */
    protected function getSearchResults(Params $params): Results
    {
        parse_str($params->fromQuery('querystring', ''), $searchParams);

        $backend = $params->fromQuery('source', DEFAULT_SEARCH_BACKEND);
        $results = $this->resultsManager->get($backend);
        $paramsObj = $results->getParams();
        $paramsObj->getOptions()->spellcheckEnabled(false);
        $paramsObj->initFromRequest(new Parameters($searchParams));

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
            $html = call_user_func([$this, $element['method']], $params, $results);
            if (null !== $html) {
                $result[$selector] = [
                    'html' => $html,
                    'target' => $element['target'],
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
        $template = 'list-' . $results->getParams()->getView();
        $templatePath = "$baseAction/$template.phtml";
        if ('search' !== $baseAction && !$this->renderer->resolver($templatePath)) {
            $templatePath = "search/$template.phtml";
        }
        $params = $results->getParams();
        return $this->renderer->render($templatePath, compact('results', 'params'));
    }

    /**
     * Render pagination
     *
     * @param Params  $params  Request params
     * @param Results $results Search results
     *
     * @return ?string
     */
    protected function renderPagination(Params $params, Results $results): ?string
    {
        $backend = $params->fromQuery('source', DEFAULT_SEARCH_BACKEND);
        $paginationOptions = 'EDS' === $backend
            ? ['disableFirst' => true, 'disableLast' => true] : [];
        $pagination = $this->renderer->plugin('paginationControl');
        return $pagination(
            $results->getPaginator(),
            'Sliding',
            'search/pagination.phtml',
            ['results' => $results, 'options' => $paginationOptions]
        );
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
}
