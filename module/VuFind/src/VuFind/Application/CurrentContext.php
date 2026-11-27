<?php

/**
 * Plugin manager for current context information.
 *
 * PHP version 8
 *
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
 * @package  Application
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\Application;

use Closure;
use Laminas\Session\ManagerInterface;
use VuFind\Auth\Manager as AuthManager;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Http\RouteHelper;
use VuFind\Http\ServerUrlHelper;
use VuFind\ServiceManager\Factory\Autowire;

/**
 * Plugin manager for current context information.
 *
 * @category VuFind
 * @package  Application
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class CurrentContext extends \VuFind\ServiceManager\AbstractValueContainer
{
    /**
     * Constructor.
     *
     * @param ServerUrlHelper  $serverUrlHelper    Server URL helper
     * @param RouteHelper      $routeHelper        Route helper
     * @param Closure          $authManagerFactory Autentication manager factory callback
     * @param ManagerInterface $sessionManager     Session manager
     */
    #[Autowire]
    public function __construct(
        protected ServerUrlHelper $serverUrlHelper,
        protected RouteHelper $routeHelper,
        #[Autowire(service: AuthManager::class, lazyLoad: true)]
        protected Closure $authManagerFactory,
        protected ManagerInterface $sessionManager,
    ) {
    }

    /**
     * Get absolute base URL.
     *
     * @return string
     */
    protected function getValueAbsoluteBaseUrl(): string
    {
        return $this->serverUrlHelper->getUrlForPath($this->getValueBaseUrlPath());
    }

    /**
     * Get absolute URL for the account-home route.
     *
     * @return string
     */
    protected function getValueAccountHomeAbsoluteUrl(): string
    {
        return $this->serverUrlHelper->getUrlForPath($this->routeHelper->getUrlFromRoute('account-home'));
    }

    /**
     * Get base path.
     *
     * @return string
     */
    protected function getValueBaseUrlPath(): string
    {
        return $this->routeHelper->getUrlFromRoute('home');
    }

    /**
     * Get logged-in user.
     *
     * @return ?UserEntityInterface
     */
    protected function getValueCurrentUser(): ?UserEntityInterface
    {
        return ($this->authManagerFactory)()->getUserObject();
    }

    /**
     * Get session ID.
     *
     * @return string
     */
    protected function getValueSessionId(): string
    {
        return $this->sessionManager->getId();
    }
}
