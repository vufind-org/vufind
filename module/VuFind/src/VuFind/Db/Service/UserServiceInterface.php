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
     * Create an entity for the specified username.
     *
     * @param string $username Username
     *
     * @return UserEntityInterface
     */
    public function createEntityForUsername(string $username): UserEntityInterface;

    /**
     * Delete a user entity.
     *
     * @param UserEntityInterface|int $userOrId User entity object or ID to delete
     *
     * @return void
     */
    public function deleteUser(UserEntityInterface|int $userOrId): void;

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
     * Field name must be id, username, email, verify_hash or cat_id.
     *
     * @param string          $fieldName  Field name
     * @param int|string|null $fieldValue Field value
     *
     * @return ?UserEntityInterface
     */
    public function getUserByField(string $fieldName, int|string|null $fieldValue): ?UserEntityInterface;

    /**
     * Retrieve a user object by catalog ID. Returns null if no match is found.
     *
     * @param string $catId Catalog ID
     *
     * @return ?UserEntityInterface
     */
    public function getUserByCatId(string $catId): ?UserEntityInterface;

    /**
     * Retrieve a user object by email address. Returns null if no match is found.
     *
     * @param string $email Email address
     *
     * @return ?UserEntityInterface
     */
    public function getUserByEmail(string $email): ?UserEntityInterface;

    /**
     * Retrieve a user object by username. Returns null if no match is found.
     *
     * @param string $username Username
     *
     * @return ?UserEntityInterface
     */
    public function getUserByUsername(string $username): ?UserEntityInterface;

    /**
     * Retrieve a user object by verify hash. Returns null if no match is found.
     *
     * @param string $hash Verify hash
     *
     * @return ?UserEntityInterface
     */
    public function getUserByVerifyHash(string $hash): ?UserEntityInterface;

    /**
     * Update the user's email address, if appropriate. Note that this does NOT
     * automatically save the row; it assumes a subsequent call will be made to
     * persist the data.
     *
     * @param UserEntityInterface $user         User entity to update
     * @param string              $email        New email address
     * @param bool                $userProvided Was this email provided by the user (true) or
     * an automated lookup (false)?
     *
     * @return void
     */
    public function updateUserEmail(
        UserEntityInterface $user,
        string $email,
        bool $userProvided = false
    ): void;

    /**
     * Get all rows with catalog usernames.
     *
     * @return UserEntityInterface[]
     */
    public function getAllUsersWithCatUsernames(): array;

    /**
     * Get user rows with insecure catalog passwords.
     *
     * @return UserEntityInterface[]
     */
    public function getInsecureRows(): array;

    /**
     * Create a new user entity.
     *
     * @return UserEntityInterface
     */
    public function createEntity(): UserEntityInterface;
}
