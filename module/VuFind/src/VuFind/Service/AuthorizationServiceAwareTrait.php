<?php

/**
 * Default implementation of AuthorizationServiceAwareInterface.
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
 * @link     https://vufind.org/wiki/development
 */

namespace VuFind\Service;

/**
 * Default implementation of AuthorizationServiceAwareInterface.
 *
 * @category VuFind
 * @package  Service
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development
 */
trait AuthorizationServiceAwareTrait
{
    /**
     * Authorization service.
     *
     * @var ?AuthorizationServiceInterface
     */
    protected ?AuthorizationServiceInterface $authorizationService = null;

    /**
     * Get the authorization service.
     *
     * @return ?AuthorizationServiceInterface
     */
    public function getAuthorizationService(): ?AuthorizationServiceInterface
    {
        return $this->authorizationService;
    }

    /**
     * Set the authorization service.
     *
     * @param AuthorizationServiceInterface $service Authorization service
     *
     * @return void
     */
    public function setAuthorizationService(AuthorizationServiceInterface $service): void
    {
        $this->authorizationService = $service;
    }
}
