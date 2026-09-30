<?php

/**
 * "Search hierarchy tree" action.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010-2023.
 * Copyright (C) The National Library of Finland 2024-2026.
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

namespace VuFind\Action\Hierarchy;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\Action\AbstractAction;
use VuFind\ActionHelper\ResponseHelper;
use VuFind\Http\HttpStatus;
use VuFind\Search\Results\PluginManager as ResultsPluginManager;
use VuFind\ServiceManager\Factory\Autowire;

use function array_slice;
use function count;

/**
 * "Search hierarchy tree" action.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class SearchTreeAction extends AbstractAction
{
    /**
     * Constructor.
     *
     * @param ResultsPluginManager $resultsPluginManager Search results plugin manager
     * @param array                $config               VuFind configuration
     */
    public function __construct(
        protected ResultsPluginManager $resultsPluginManager,
        #[Autowire(config: 'config')]
        protected array $config,
    ) {
    }

    /**
     * Search hierarchy tree.
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
        $this->disableSessionWrites();  // avoid session write timing bug

        $responseHelper = $this->getHelper(ResponseHelper::class);
        $limit = (int)($this->config['Hierarchy']['treeSearchLimit'] ?? 100);
        if (null === ($hierarchyID = $this->getQueryParam('hierarchyID'))) {
            return $responseHelper->getJsonResponse($response, ['error' => 'Missing ID'], HttpStatus::BAD_REQUEST);
        }
        $source = $this->getQueryParam('hierarchySource', DEFAULT_SEARCH_BACKEND);
        $lookfor = $this->getQueryParam('lookfor', '');
        $searchType = $this->getQueryParam('type', 'AllFields');

        $results = $this->resultsPluginManager->get($source);
        $params = $results->getParams();
        $params->setBasicSearch($lookfor, $searchType);
        $params->addFilter('hierarchy_top_id:' . $hierarchyID);
        $facets = $results->getFullFieldFacets(['id'], false, null === $limit ? -1 : $limit + 1);

        $callback = fn ($data) => $data['value'];
        $resultIDs = array_map($callback, $facets['id']['data']['list'] ?? []);

        $limitReached = ($limit > 0 && count($resultIDs) > $limit);
        $results = array_slice($resultIDs, 0, $limit);

        return $responseHelper->getJsonResponse($response, compact('limitReached', 'results'), allowCaching: true);
    }
}
