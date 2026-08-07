<?php

/**
 * Abstract base class for EDS record redirect actions.
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
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */

namespace VuFind\Action\EdsRecord;

use Lmc\Rbac\Mvc\Service\AuthorizationService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\Action\Record\AbstractRecordAction;
use VuFind\ActionHelper\FlashMessagesHelper;
use VuFind\ActionHelper\LoginHelper;
use VuFind\ActionHelper\PermissionHelper;
use VuFind\ActionHelper\RedirectHelper;
use VuFind\Auth\Manager as AuthManager;
use VuFind\Exception\Forbidden as ForbiddenException;
use VuFind\Record\Loader as RecordLoader;
use VuFind\Record\Router as RecordRouter;
use VuFind\RecordTab\TabManager;
use VuFind\Search\Memory as SearchMemory;
use VuFind\Search\ResultScroller;
use VuFind\ServiceManager\Factory\Autowire;
use VuFindSearch\ParamBag;

/**
 * Abstract base class for EDS record redirect actions.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
abstract class AbstractRedirectToEBookAction extends AbstractRecordAction
{
    /**
     * Constructor.
     *
     * @param SearchMemory         $searchMemory         Search memory
     * @param TabManager           $tabManager           Tab manager
     * @param AuthManager          $authManager          Authentication manager
     * @param RecordLoader         $recordLoader         Record loader
     * @param RecordRouter         $recordRouter         Record router
     * @param ResultScroller       $resultScroller       Result scroller
     * @param array                $config               VuFind configuration
     * @param AuthorizationService $authorizationService Authorization service
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
        protected AuthorizationService $authorizationService,
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
     * Redirect to an eBook.
     *
     * @param ServerRequestInterface $request  Server request
     * @param ResponseInterface      $response Response
     * @param ?string                $format   Format of eBook to request from API.
     * @param string                 $method   Record driver method to use to obtain target URL.
     *
     * @return ResponseInterface
     */
    protected function redirectToEbook(
        ServerRequestInterface $request,
        ResponseInterface $response,
        ?string $format,
        string $method
    ): ResponseInterface {
        $paramArray = $format === null ? [] : ['ebookpreferredformat' => $format];
        $params = new ParamBag($paramArray);
        $driver = $this->loadRecord($params, true);
        // If the user is a guest, redirect them to the login screen.
        if (!$this->getHelper(PermissionHelper::class)->isAuthorized('access.EDSExtendedResults')) {
            if (!$this->getUser()) {
                return $this->getHelper(LoginHelper::class)->forceLogin($request, $response);
            }
            throw new ForbiddenException('Access denied.');
        }
        $redirectHelper = $this->getHelper(RedirectHelper::class);
        $url = $driver->tryMethod($method);
        if (!$url) {
            $this->getHelper(FlashMessagesHelper::class)->addErrorMessage('error_accessing_full_text');
            return $redirectHelper->redirectToRoute($response, 'edsrecord', ['id' => $this->getRouteParam('id')]);
        }
        return $redirectHelper->redirectToUrl($response, $url);
    }
}
