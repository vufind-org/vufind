<?php

/**
 * Database service interface for resource_tags.
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

use Laminas\Paginator\Paginator;

/**
 * Database service interface for resource_tags.
 *
 * @category VuFind
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
interface ResourceTagsServiceInterface extends DbServiceInterface
{
    /**
     * Get Resource Tags Paginator
     *
     * @param ?int    $userId     ID of user (null for any)
     * @param ?int    $resourceId ID of the resource (null for any)
     * @param ?int    $tagId      ID of the tag (null for any)
     * @param ?string $order      The order in which to return the data
     * @param ?string $page       The page number to select
     * @param int     $limit      The number of items to fetch
     *
     * @return Paginator
     */
    public function getResourceTagsPaginator(
        ?int $userId = null,
        ?int $resourceId = null,
        ?int $tagId = null,
        ?string $order = null,
        ?string $page = null,
        int $limit = 20
    ): Paginator;

    /**
     * Remove links from the resource_tags table based on an array of IDs.
     *
     * @param string[] $ids Identifiers from resource_tags to delete.
     *
     * @return int          Count of $ids
     */
    public function deleteLinksByResourceTagsIdArray(array $ids): int;

    /**
     * Gets unique tagged resources from the database.
     *
     * @param ?int $userId     ID of user (null for any)
     * @param ?int $resourceId ID of the resource (null for any)
     * @param ?int $tagId      ID of the tag (null for any)
     *
     * @return array
     */
    public function getUniqueResources(
        ?int $userId = null,
        ?int $resourceId = null,
        ?int $tagId = null
    ): array;

    /**
     * Gets unique tags from the database.
     *
     * @param ?int $userId     ID of user (null for any)
     * @param ?int $resourceId ID of the resource (null for any)
     * @param ?int $tagId      ID of the tag (null for any)
     *
     * @return array
     */
    public function getUniqueTags(
        ?int $userId = null,
        ?int $resourceId = null,
        ?int $tagId = null
    ): array;

    /**
     * Gets unique users from the database.
     *
     * @param ?int $userId     ID of user (null for any)
     * @param ?int $resourceId ID of the resource (null for any)
     * @param ?int $tagId      ID of the tag (null for any)
     *
     * @return array
     */
    public function getUniqueUsers(
        ?int $userId = null,
        ?int $resourceId = null,
        ?int $tagId = null
    ): array;

    /**
     * Delete resource tags rows matching specified filter(s).
     *
     * @param ?int $userId     ID of user (null for any)
     * @param ?int $resourceId ID of the resource (null for any)
     * @param ?int $tagId      ID of the tag (null for any)
     *
     * @return void
     */
    public function deleteResourceTags(
        ?int $userId = null,
        ?int $resourceId = null,
        ?int $tagId = null
    ): void;
}
