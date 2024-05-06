<?php

/**
 * Interface for persisting user data in the session.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2024.
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
 * @package  Auth
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */

namespace VuFind\Auth;

use Exception;
use VuFind\Db\Entity\UserEntityInterface;

/**
 * Interface for persisting user data in the session.
 *
 * @category VuFind
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
interface UserSessionPersistenceInterface
{
    /**
     * Update session container to store data representing a user (used by privacy mode).
     *
     * @param UserEntityInterface $user User to store in session.
     *
     * @return void
     * @throws Exception
     */
    public function addUserDataToSession(UserEntityInterface $user): void;

    /**
     * Update session container to store user ID (used outside of privacy mode).
     *
     * @param int $id User ID
     *
     * @return void
     */
    public function addUserIdToSession(int $id): void;

    /**
     * Clear the user data from the session.
     *
     * @return void
     */
    public function clearUserFromSession(): void;

    /**
     * Build a user entity using data from a session container. Return null if user
     * data cannot be found.
     *
     * @return ?UserEntityInterface
     */
    public function getUserFromSession(): ?UserEntityInterface;

    /**
     * Is there user data currently stored in the session container?
     *
     * @return bool
     */
    public function hasUserSessionData(): bool;
}
