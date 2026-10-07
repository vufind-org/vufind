<?php

/**
 * User login action.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2023-2026.
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

namespace VuFind\Action\MyResearch;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\Action\AbstractTemplateRenderingAction;
use VuFind\ActionHelper\ContextHelper;
use VuFind\ActionHelper\ForwardHelper;
use VuFind\ActionHelper\LoginHelper;
use VuFind\ActionHelper\RedirectHelper;
use VuFind\ActionHelper\ResponseHelper;
use VuFind\Auth\Manager as AuthManager;
use VuFind\ServiceManager\Factory\Autowire;

/**
 * User login action.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class UserLoginAction extends AbstractTemplateRenderingAction
{
    /**
     * Constructor.
     *
     * @param AuthManager $authManager Authentication manager
     */
    #[Autowire]
    public function __construct(
        protected AuthManager $authManager,
    ) {
        parent::__construct();
    }

    /**
     * Initialize the action.
     *
     * @return void
     */
    protected function init(): void
    {
        // Default to false rather than null because we don't want a default setting to override the action's
        // accessibility and break the login process!
        $this->accessPermission = false;
    }

    /**
     * Display login.
     *
     * This is used for explicit login links within the UI to differentiate them from contextual login links that are
     * triggered by attempting to access protected actions.
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
        // Don't log in if already logged in!
        if ($this->authManager->getIdentity()) {
            return $this->getHelper(ContextHelper::class)->inLightbox($request)  // different behavior in lightbox
                ? $this->getHelper(ResponseHelper::class)->getRefreshResponse($response)
                : $this->getHelper(RedirectHelper::class)->redirectToRoute($response, 'home');
        }
        $loginHelper = $this->getHelper(LoginHelper::class);
        $loginHelper->clearFollowupUrl();
        // Set followup with the isReferrer flag so that the post-login process
        // can decide whether to use it:
        $loginHelper->setFollowupUrlToReferrer($request, true, ['isReferrer' => true]);

        if ($sesssionInitiator = $this->authManager->getSessionInitiator()) {
            return $this->getHelper(RedirectHelper::class)->redirectToUrl($response, $sesssionInitiator);
        }
        return $this->getHelper(ForwardHelper::class)->forwardTo($request, $response, 'MyResearch/Login');
    }
}
