<?php

/**
 * Asserts that user has a verified email
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2025.
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Authorization
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/ Wiki
 */

namespace VuFind\Role\Assertion;

use LmcRbacMvc\Assertion\AssertionInterface;
use LmcRbacMvc\Service\AuthorizationService;
use VuFind\Db\Entity\UserEntityInterface;

/**
 * Asserts that user has a verified email
 *
 * @category VuFind
 * @package  Authorization
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/ Wiki
 */
class HasVerifiedEmailAssertion implements AssertionInterface
{
    /**
     * Check if user has verified email to display developer settings
     *
     * @param AuthorizationService $authorizationService Authorization service
     *
     * @return bool
     */
    public function assert(AuthorizationService $authorizationService)
    {
        $identity = $authorizationService->getIdentity();
        if ($identity instanceof UserEntityInterface) {
            return (bool)$identity->getEmailVerified();
        }
        return false;
    }
}
