<?php

/**
 * Logout action.
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
use VuFind\ActionHelper\ContextHelper;
use VuFind\ActionHelper\RedirectHelper;

/**
 * Logout action.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class LogoutAction extends AbstractMyResearchAction
{
    /**
     * Initialize the action.
     *
     * @return void
     */
    protected function init(): void
    {
        // Default to false rather than null because we don't want a default setting to override the action's
        // accessibility and break the logout process!
        $this->accessPermission = false;
    }

    /**
     * Log out with appropriate redirect(s).
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
        if ($logoutRoute = $this->config['Site']['logOutRoute'] ?? null) {
            $logoutTarget = $this->serverUrlHelper->getUrlForPath($this->routeHelper->getUrlFromRoute($logoutRoute));
        } else {
            $homeUrl = $this->serverUrlHelper->getUrlForPath($this->getRouteHelper()->getUrlFromRoute('home'));
            if (!($logoutTarget = $this->getHelper(ContextHelper::class)->getReferrer($request))) {
                $logoutTarget = $homeUrl;
            }

            // If there is an auth_method parameter in the query, we should strip it out. Otherwise, the user may get
            // stuck in an infinite loop of logging out and getting logged back in when using environment-based
            // authentication methods like Shibboleth.
            $logoutTarget = preg_replace('/([?&])auth_method=[^&]*&?/', '$1', $logoutTarget);
            $logoutTarget = rtrim($logoutTarget, '?');

            // Another special case: if logging out will send the user back to the MyResearch home action, instead send
            // them all the way to VuFind home. Otherwise, they might get logged back in again, which is confusing. Even
            // in the best scenario, they'll just end up on a login screen, which is not helpful.
            $myResearchHomeUrl = $this->serverUrlHelper->getUrlForPath(
                $this->getRouteHelper()->getUrlFromRoute('myresearch-home')
            );
            if ($logoutTarget === $myResearchHomeUrl) {
                $logoutTarget = $homeUrl;
            }
        }
        $redirectUrl = $this->authManager->getLogoutRedirectUrl($logoutTarget);
        $this->authManager->clearLoginState();
        return $this->getHelper(RedirectHelper::class)->redirectToUrl($response, $redirectUrl);
    }
}
