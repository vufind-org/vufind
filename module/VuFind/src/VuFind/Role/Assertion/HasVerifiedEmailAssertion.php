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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Authorization
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/ Wiki
 */

namespace VuFind\Role\Assertion;

use Lmc\Rbac\Assertion\AssertionInterface;
use Lmc\Rbac\Identity\IdentityInterface;
use VuFind\Db\Entity\UserEntityInterface;

/**
 * Asserts that user has a verified email
 *
 * @category VuFind
 * @package  Authorization
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/ Wiki
 */
class HasVerifiedEmailAssertion implements AssertionInterface
{
    /**
     * Check if user has verified email to display developer settings
     *
     * @param string             $permission Permission
     * @param ?IdentityInterface $identity   Identity to check
     * @param mixed              $context    Permission context
     *
     * @return bool
     */
    public function assert(
        string $permission,
        ?IdentityInterface $identity = null,
        mixed $context = null
    ): bool {
        if ($identity instanceof UserEntityInterface) {
            return (bool)$identity->getEmailVerified();
        }
        return false;
    }
}
