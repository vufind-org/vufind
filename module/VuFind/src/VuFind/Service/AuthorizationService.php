<?php

/**
 * Authorization service.
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
 * @package  Service
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Service;

use Lmc\Rbac\Assertion\AssertionInterface;
use Lmc\Rbac\Identity\IdentityInterface;
use Lmc\Rbac\Service\AuthorizationServiceInterface as BaseAuthorizationServiceInterface;
use VuFind\Auth\Manager as AuthManager;

/**
 * Authorization service.
 *
 * @category VuFind
 * @package  Service
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class AuthorizationService implements AuthorizationServiceInterface
{
    /**
     * Constructor.
     *
     * @param AuthManager                       $authManager              Authentication manager
     * @param BaseAuthorizationServiceInterface $baseAuthorizationService Base authorization service
     */
    public function __construct(
        protected AuthManager $authManager,
        protected BaseAuthorizationServiceInterface $baseAuthorizationService,
    ) {
    }

    /**
     * Get user's identity.
     *
     * @return ?IdentityInterface
     */
    public function getIdentity(): ?IdentityInterface
    {
        return $this->authManager->getIdentity();
    }

    /**
     * Check if the permission is granted to the current identity.
     *
     * @param string $permission Permission
     *
     * @return bool
     */
    public function isGranted(string $permission): bool
    {
        return $this->baseAuthorizationService->isGranted($this->authManager->getUserObject(), $permission);
    }

    /**
     * Set assertion for a given permission.
     *
     * @param string                             $permission Permission
     * @param AssertionInterface|callable|string $assertion  Assertion
     *
     * @return void
     */
    public function setAssertion(
        string $permission,
        AssertionInterface|callable|string $assertion
    ): void {
        $this->baseAuthorizationService->setAssertion($permission, $assertion);
    }
}
