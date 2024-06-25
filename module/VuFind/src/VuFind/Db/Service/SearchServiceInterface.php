<?php

/**
 * Database service interface for search.
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
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */

namespace VuFind\Db\Service;

use VuFind\Db\Entity\SearchEntityInterface;
use VuFind\Db\Entity\UserEntityInterface;

/**
 * Database service interface for search.
 *
 * @category VuFind
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways interface
 */
interface SearchServiceInterface extends DbServiceInterface
{
    /**
     * Create a search entity.
     *
     * @return SearchEntityInterface
     */
    public function createEntity(): SearchEntityInterface;

    /**
     * Create a search entity containing the specified checksum, persist it to the database,
     * and return a fully populated object. Throw an exception if something goes wrong during
     * the process.
     *
     * @param int $checksum Checksum
     *
     * @return SearchEntityInterface
     * @throws Exception
     */
    public function createAndPersistEntityWithChecksum(int $checksum): SearchEntityInterface;

    /**
     * Destroy unsaved searches belonging to the specified session/user.
     *
     * @param string                       $sessionId Session ID of current user.
     * @param UserEntityInterface|int|null $userOrId  User entity or ID of current user (optional).
     *
     * @return void
     */
    public function destroySession(string $sessionId, UserEntityInterface|int|null $userOrId = null): void;

    /**
     * Get a SearchEntityInterface object by ID.
     *
     * @param int $id Search identifier
     *
     * @return ?SearchEntityInterface
     */
    public function getSearchById(int $id): ?SearchEntityInterface;

    /**
     * Get a SearchEntityInterface object by ID and owner.
     *
     * @param int                          $id        Search identifier
     * @param string                       $sessionId Session ID of current user.
     * @param UserEntityInterface|int|null $userOrId  User entity or ID of current user (optional).
     *
     * @return ?SearchEntityInterface
     */
    public function getSearchByIdAndOwner(
        int $id,
        string $sessionId,
        UserEntityInterface|int|null $userOrId
    ): ?SearchEntityInterface;

    /**
     * Get an array of rows for the specified user.
     *
     * @param string                       $sessionId Session ID of current user.
     * @param UserEntityInterface|int|null $userOrId  User entity or ID of current user (optional).
     *
     * @return SearchEntityInterface[]
     */
    public function getSearches(string $sessionId, UserEntityInterface|int|null $userOrId = null): array;

    /**
     * Get scheduled searches.
     *
     * @return SearchEntityInterface[]
     */
    public function getScheduledSearches(): array;

    /**
     * Retrieve all searches matching the specified checksum and belonging to the user specified by session or user
     * entity/ID.
     *
     * @param int                          $checksum  Checksum to match
     * @param string                       $sessionId Current session ID
     * @param UserEntityInterface|int|null $userOrId  Entity or ID representing current user (optional).
     *
     * @return SearchEntityInterface[]
     * @throws Exception
     */
    public function getSearchesByChecksumAndOwner(
        int $checksum,
        string $sessionId,
        UserEntityInterface|int|null $userOrId = null
    ): array;

    /**
     * Set invalid user_id values in the table to null; return count of affected rows.
     *
     * @return int
     */
    public function cleanUpInvalidUserIds(): int;

    /**
     * Get saved searches with missing checksums (used for cleaning up legacy data).
     *
     * @return SearchEntityInterface[]
     */
    public function getSavedSearchesWithMissingChecksums(): array;
}
