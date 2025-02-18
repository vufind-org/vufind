<?php

/**
 * Database service interface for resource.
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
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */

namespace VuFind\Db\Service;

use Exception;
use VuFind\Db\Entity\ResourceEntityInterface;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Entity\UserListEntityInterface;

/**
 * Database service interface for resource.
 *
 * @category VuFind
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
interface ResourceServiceInterface extends DbServiceInterface
{
    /**
     * Lookup and return a resource.
     *
     * @param int $id Identifier value
     *
     * @return ?ResourceEntityInterface
     */
    public function getResourceById(int $id): ?ResourceEntityInterface;

    /**
     * Create a resource entity object.
     *
     * @return ResourceEntityInterface
     */
    public function createEntity(): ResourceEntityInterface;

    /**
     * Get a set of records that do not have metadata stored in the resource
     * table.
     *
     * @return ResourceEntityInterface[]
     */
    public function findMissingMetadata(): array;

    /**
     * Retrieve a single resource row by record ID/source. Return null if it does not exist.
     *
     * @param string $id     Record ID
     * @param string $source Record source
     *
     * @return ?ResourceEntityInterface
     */
    public function getResourceByRecordId(
        string $id,
        string $source = DEFAULT_SEARCH_BACKEND
    ): ?ResourceEntityInterface;

    /**
     * Retrieve resource entities matching a set of specified records.
     *
     * @param string[] $ids    Array of IDs
     * @param string   $source Source of records to look up
     *
     * @return ResourceEntityInterface[]
     */
    public function getResourcesByRecordIds(array $ids, string $source = DEFAULT_SEARCH_BACKEND): array;

    /**
     * Get a set of resources from the requested favorite list.
     *
     * @param UserEntityInterface|int          $userOrId          ID of user owning favorite list
     * @param UserListEntityInterface|int|null $listOrId          ID of list to retrieve (null for all favorites)
     * @param string[]                         $tags              Tags to use for limiting results
     * @param ?string                          $sort              Resource table field to use for sorting (null for no
     * particular sort).
     * @param int                              $offset            Offset for results
     * @param ?int                             $limit             Limit for results (null for none)
     * @param bool                             $caseSensitiveTags Treat tags as case-sensitive?
     *
     * @return ResourceEntityInterface[]
     */
    public function getFavorites(
        UserEntityInterface|int $userOrId,
        UserListEntityInterface|int|null $listOrId = null,
        array $tags = [],
        ?string $sort = null,
        int $offset = 0,
        ?int $limit = null,
        bool $caseSensitiveTags = false
    ): array;

    /**
     * Delete a resource by record id and source. Return true if found and deleted, false if not found.
     * Throws exception if something goes wrong.
     *
     * @param string $id     Resource ID
     * @param string $source Resource source
     *
     * @return bool
     * @throws Exception
     */
    public function deleteResourceByRecordId(string $id, string $source): bool;

    /**
     * Globally change the name of a source value in the database; return the number of rows affected.
     *
     * @param string $old Old source value
     * @param string $new New source value
     *
     * @return int
     */
    public function renameSource(string $old, string $new): int;

    /**
     * Delete a resource entity.
     *
     * @param ResourceEntityInterface|int $resourceOrId Resource entity or ID value.
     *
     * @return void
     */
    public function deleteResource(ResourceEntityInterface|int $resourceOrId): void;
}
