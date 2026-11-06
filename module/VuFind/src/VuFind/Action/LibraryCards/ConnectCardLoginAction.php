<?php

/**
 * "Connect library card with authentication" action.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2015-2026.
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

namespace VuFind\Action\LibraryCards;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\Action\AbstractTemplateRenderingAction;
use VuFind\ActionHelper\FlashMessagesHelper;
use VuFind\ActionHelper\LoginHelper;
use VuFind\ActionHelper\RedirectHelper;
use VuFind\Auth\Manager as AuthManager;
use VuFind\Http\ServerUrlHelper;
use VuFind\ServiceManager\Factory\Autowire;

/**
 * "Connect library card with authentication" action.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class ConnectCardLoginAction extends AbstractTemplateRenderingAction
{
    /**
     * Constructor.
     *
     * @param AuthManager     $authManager     Authentication manager
     * @param ServerUrlHelper $serverUrlHelper Server URL helper
     */
    #[Autowire]
    public function __construct(
        protected AuthManager $authManager,
        protected ServerUrlHelper $serverUrlHelper,
    ) {
        parent::__construct();
    }

    /**
     * Connect a new library card for an authenticated user.
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
        // Force login:
        if (!($user = $this->authManager->getUserObject())) {
            return $this->getHelper(LoginHelper::class)->forceLogin($request, $response);
        }

        $redirectHelper = $this->getHelper(RedirectHelper::class);
        $url = $this->serverUrlHelper->getUrlForPath($this->routeHelper->getUrlFromRoute('librarycards-connectcard'));
        if (!($redirectUrl = $this->authManager->getSessionInitiator($url))) {
            $this->getHelper(FlashMessagesHelper::class)->addErrorMessage('authentication_error_technical');
            return $redirectHelper->redirectToRoute($response, 'librarycards-home');
        }
        return $redirectHelper->redirectToUrl($response, $redirectUrl);
    }
}
