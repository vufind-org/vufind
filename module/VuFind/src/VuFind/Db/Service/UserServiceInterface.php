<?php

/**
 * Database service interface for users.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2024.
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
 * @package  Database
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */

namespace VuFind\Db\Service;

use Exception;
use Laminas\Session\Container as SessionContainer;
use VuFind\Db\Entity\UserEntityInterface;

/**
 * Database service interface for users.
 *
 * @category VuFind
 * @package  Database
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
interface UserServiceInterface extends DbServiceInterface
{
    /**
     * Retrieve a user object from the database based on ID.
     *
     * @param int $id ID.
     *
     * @return ?UserEntityInterface
     */
    public function getUserById(int $id): ?UserEntityInterface;

    /**
     * Retrieve a user object from the database based on the given field.
     *
     * @param string          $fieldName  Field name
     * @param int|null|string $fieldValue Field value
     *
     * @return ?UserEntityInterface
     */
    public function getUserByField(string $fieldName, int|null|string $fieldValue): ?UserEntityInterface;

    /**
     * Create a new user entity.
     *
     * @return UserEntityInterface
     */
    public function createEntity(): UserEntityInterface;

    /**
     * Update session container to store data representing a user (used by privacy mode).
     *
     * @param SessionContainer    $session Session container.
     * @param UserEntityInterface $user    User to store in session.
     *
     * @return void
     * @throws Exception
     */
    public function addUserDataToSessionContainer(SessionContainer $session, UserEntityInterface $user): void;

    /**
     * Build a user entity using data from a session container.
     *
     * @param SessionContainer $session Session container.
     *
     * @return UserEntityInterface
     */
    public function getUserFromSessionContainer(SessionContainer $session): UserEntityInterface;
}
