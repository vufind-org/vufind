<?php

/**
 * DevTools deminify action.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2011.
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

namespace VuFindDevTools\Action\DevTools;

use Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\Action\AbstractTemplateRenderingAction;
use VuFind\Search\Minified;
use VuFind\Search\Results\PluginManager as ResultsPluginManager;
use VuFind\ServiceManager\Factory\Autowire;
use VuFindSearch\Service as SearchService;

use function is_callable;

/**
 * DevTools deminify action.
 *
 * @category VuFind
 * @package  Action
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class DeminifyAction extends AbstractTemplateRenderingAction
{
    /**
     * Constructor.
     *
     * @param ResultsPluginManager $resultsPluginManager Search results plugin manager
     * @param SearchService        $searchService        Search service
     */
    #[Autowire]
    public function __construct(
        protected ResultsPluginManager $resultsPluginManager,
        protected SearchService $searchService,
    ) {
        parent::__construct();
    }

    /**
     * Deminify a minified search object.
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
        $templateParams = [];
        if ($min = trim($this->getPostParam('min', ''))) {
            try {
                $templateParams['min'] = unserialize($min);
                if ($templateParams['min'] instanceof Minified) {
                    $templateParams['results'] = $templateParams['min']->deminify($this->resultsPluginManager);
                    $params = $templateParams['results']->getParams();
                    $templateParams['query'] = $params->getQuery();
                    if (is_callable([$params, 'getBackendParameters'])) {
                        $templateParams['backendParams'] = $params->getBackendParameters()->getArrayCopy();
                    }
                    if ($builder = $this->getQueryBuilder($params->getSearchClassId())) {
                        $templateParams['queryParams'] = $builder->build($templateParams['query'])->getArrayCopy();
                    }
                }
            } catch (Exception $e) {
                $templateParams['min'] = '<' . $e->getMessage() . '>';
            }
        }
        return $this->renderTemplate($request, $response, $templateParams);
    }

    /**
     * Fetch the query builder for the specified search backend. Return null if unavailable.
     *
     * @param string $id Backend ID
     *
     * @return ?object
     */
    protected function getQueryBuilder(string $id): ?object
    {
        $command = new \VuFindSearch\Command\GetQueryBuilderCommand($id);
        try {
            $this->searchService->invoke($command);
        } catch (\Exception $e) {
            return null;
        }
        return $command->getResult();
    }
}
