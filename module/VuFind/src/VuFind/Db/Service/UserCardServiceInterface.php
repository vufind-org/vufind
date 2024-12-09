<?php

/**
 * Database service for UserCard.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2023.
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
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */

namespace VuFind\Db\Service;

use VuFind\Db\Entity\UserCardEntityInterface;
use VuFind\Db\Entity\UserEntityInterface;

/**
 * Database service for UserCard.
 *
 * @category VuFind
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
interface UserCardServiceInterface extends DbServiceInterface
{
    /**
     * Get user_card rows with insecure catalog passwords.
     *
     * @return UserCardEntityInterface[]
     */
    public function getInsecureRows(): array;

    /**
     * Get user_card rows with catalog usernames set.
     *
     * @return UserCardEntityInterface[]
     */
    public function getAllRowsWithUsernames(): array;

    /**
     * Get all library cards associated with the user.
     *
     * @param UserEntityInterface|int $userOrId    User object or identifier
     * @param ?int                    $id          Optional card ID filter
     * @param ?string                 $catUsername Optional catalog username filter
     *
     * @return UserCardEntityInterface[]
     */
    public function getLibraryCards(
        UserEntityInterface|int $userOrId,
        ?int $id = null,
        ?string $catUsername = null
    ): array;

    /**
     * Get or create library card data.
     *
     * @param UserEntityInterface|int $userOrId User object or identifier
     * @param ?int                    $id       Card ID to fetch (or null to create a new card)
     *
     * @return UserCardEntityInterface Card data if found; throws exception otherwise
     * @throws \VuFind\Exception\LibraryCard
     */
    public function getOrCreateLibraryCard(UserEntityInterface|int $userOrId, ?int $id = null): UserCardEntityInterface;

    /**
     * Delete library card.
     *
     * @param UserEntityInterface         $user     User owning card to delete
     * @param UserCardEntityInterface|int $userCard UserCard id or object to be deleted
     *
     * @return bool
     * @throws \Exception
     */
    public function deleteLibraryCard(UserEntityInterface $user, UserCardEntityInterface|int $userCard): bool;

    /**
     * Persist the provided library card data, either by updating a specified card
     * or by creating a new one (when $card is null). Also updates the primary user
     * row when appropriate. Will throw an exception if a duplicate $username value
     * is provided; there should only be one card row per username.
     *
     * Returns the row that was added or updated.
     *
     * @param UserEntityInterface|int          $userOrId User object or identifier
     * @param UserCardEntityInterface|int|null $cardOrId Card ID (null = create new)
     * @param string                           $cardName Card name
     * @param string                           $username Username
     * @param string                           $password Password
     * @param string                           $homeLib  Home Library
     *
     * @return UserCardEntityInterface
     * @throws \VuFind\Exception\LibraryCard
     */
    public function persistLibraryCardData(
        UserEntityInterface|int $userOrId,
        UserCardEntityInterface|int|null $cardOrId,
        string $cardName,
        string $username,
        string $password,
        string $homeLib = ''
    ): UserCardEntityInterface;

    /**
     * Verify that the user's current ILS settings exist in their library card data
     * (if enabled) and are up to date. Designed to be called after updating the
     * user row; will create or modify library card rows as needed.
     *
     * @param UserEntityInterface|int $userOrId User object or identifier
     *
     * @return bool
     * @throws \VuFind\Exception\PasswordSecurity
     */
    public function synchronizeUserLibraryCardData(UserEntityInterface|int $userOrId): bool;

    /**
     * Activate a library card for the given username.
     *
     * @param UserEntityInterface|int $userOrId User owning card
     * @param int                     $id       Library card ID to activate
     *
     * @return void
     * @throws \VuFind\Exception\LibraryCard
     */
    public function activateLibraryCard(UserEntityInterface|int $userOrId, int $id): void;

    /**
     * Create a UserCard entity object.
     *
     * @return UserCardEntityInterface
     */
    public function createEntity(): UserCardEntityInterface;
}
