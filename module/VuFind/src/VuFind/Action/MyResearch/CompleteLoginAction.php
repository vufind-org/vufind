<?php

/**
 * Complete login action.
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
use VuFind\ActionHelper\LoginHelper;
use VuFind\ActionHelper\RedirectHelper;
use VuFind\ActionHelper\ResponseHelper;
use VuFind\Auth\Manager as AuthManager;
use VuFind\ServiceManager\Factory\Autowire;

use function is_array;

/**
 * Complete login action.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class CompleteLoginAction extends AbstractTemplateRenderingAction
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
     * Complete login - perform a user login followed by a catalog login.
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
        $loginHelper = $this->getHelper(LoginHelper::class);
        if (!$this->authManager->getIdentity()) {
            return $loginHelper->forceLogin($request, $response, '');
        }
        if (!is_array($patron = $loginHelper->catalogLogin($request, $response))) {
            return $patron;
        }
        return $this->getHelper(ContextHelper::class)->inLightbox($request)
            ? $this->getHelper(ResponseHelper::class)->getRefreshResponse($response)
            : $this->getHelper(RedirectHelper::class)->redirectToRoute($response, 'home');
    }
}
