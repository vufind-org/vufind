<?php

/**
 * Record explain action.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010-2024.
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

namespace VuFind\Action\Record;

use Laminas\Stdlib\Parameters;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\Auth\Manager as AuthManager;
use VuFind\Record\Loader as RecordLoader;
use VuFind\Record\Router as RecordRouter;
use VuFind\RecordTab\TabManager;
use VuFind\Search\Explanation\PluginManager as ExplanationPluginManager;
use VuFind\Search\Memory as SearchMemory;
use VuFind\Search\ResultScroller;
use VuFind\ServiceManager\Factory\Autowire;
use VuFind\View\Helper\Root\Record;

/**
 * Record explain action.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class ExplainAction extends AbstractRecordAction
{
    /**
     * Constructor.
     *
     * @param SearchMemory             $searchMemory             Search memory
     * @param TabManager               $tabManager               Tab manager
     * @param AuthManager              $authManager              Authentication manager
     * @param RecordLoader             $recordLoader             Record loader
     * @param RecordRouter             $recordRouter             Record router
     * @param ResultScroller           $resultScroller           Result scroller
     * @param array                    $config                   VuFind configuration
     * @param ExplanationPluginManager $explanationPluginManager Explanation plugin manager
     */
    public function __construct(
        SearchMemory $searchMemory,
        TabManager $tabManager,
        AuthManager $authManager,
        RecordLoader $recordLoader,
        RecordRouter $recordRouter,
        ResultScroller $resultScroller,
        #[Autowire(config: 'config')]
        array $config,
        protected ExplanationPluginManager $explanationPluginManager,
    ) {
        parent::__construct(
            $searchMemory,
            $tabManager,
            $authManager,
            $recordLoader,
            $recordRouter,
            $resultScroller,
            $config
        );
    }

    /**
     * Show explanation for why a record was found and how its relevancy is computed.
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
        $record = $this->loadRecord();
        $templateParams = $this->getTemplateParams();

        if (!$record->tryMethod('explainEnabled')) {
            $templateParams['disabled'] = true;
        } else {
            $explanation = $this->explanationPluginManager->get($record->getSourceIdentifier());

            $params = $explanation->getParams();
            $params->initFromRequest(new Parameters($request->getQueryParams()));
            $explanation->performRequest($record->getUniqueID());

            $templateParams['explanation'] = $explanation;
        }
        return $this->renderTemplate($request, $response, $templateParams, 'record/explain');
    }
}
