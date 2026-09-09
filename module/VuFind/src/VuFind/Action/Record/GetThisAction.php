<?php

/**
 * Record "get this" action.
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

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\Auth\Manager as AuthManager;
use VuFind\GetThis\GetThisLoader;
use VuFind\ILS\Connection;
use VuFind\Record\Loader as RecordLoader;
use VuFind\Record\Router as RecordRouter;
use VuFind\RecordTab\TabManager;
use VuFind\Search\Memory as SearchMemory;
use VuFind\Search\ResultScroller;
use VuFind\ServiceManager\Factory\Autowire;
use VuFind\View\Helper\Root\Record;

/**
 * Record "get this" action.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class GetThisAction extends AbstractRecordAction
{
    /**
     * Constructor.
     *
     * @param SearchMemory   $searchMemory   Search memory
     * @param TabManager     $tabManager     Tab manager
     * @param AuthManager    $authManager    Authentication manager
     * @param RecordLoader   $recordLoader   Record loader
     * @param RecordRouter   $recordRouter   Record router
     * @param ResultScroller $resultScroller Result scroller
     * @param array          $config         VuFind configuration
     * @param Connection     $ilsConnection  ILS connection
     * @param GetThisLoader  $getThisLoader  Get This loader
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
        protected Connection $ilsConnection,
        protected GetThisLoader $getThisLoader,
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
     * Display the "Get This" dialog content.
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
        $templateParams = $this->getTemplateParams();
        $id = $this->getRouteParam('id');
        $itemId = $this->getQueryParam('item_id');
        $items = $this->ilsConnection->getStatus($id);
        if ($templateParams['driver']) {
            $this->getThisLoader->setRecordDriver($templateParams['driver']);
        }
        $this->getThisLoader->setItems($items);
        $this->getThisLoader->setDefaultItemId($itemId);

        $templateParams['getThisLoader'] = $this->getThisLoader;
        return $this->renderTemplate($request, $response, $templateParams, 'record/get-this');
    }
}
