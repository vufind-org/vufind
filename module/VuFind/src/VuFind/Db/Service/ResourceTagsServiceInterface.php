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

use DateTime;
use Laminas\Paginator\Paginator;
use VuFind\Db\Entity\ResourceEntityInterface;
use VuFind\Db\Entity\ResourceTagsEntityInterface;
use VuFind\Db\Entity\TagsEntityInterface;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Entity\UserListEntityInterface;

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
     * @param ?int    $page       The page number to select
     * @param int     $limit      The number of items to fetch
     *
     * @return Paginator
     */
    public function getResourceTagsPaginator(
        ?int $userId = null,
        ?int $resourceId = null,
        ?int $tagId = null,
        ?string $order = null,
        ?int $page = null,
        int $limit = 20
    ): Paginator;

    /**
     * Create a ResourceTagsEntityInterface object.
     *
     * @return ResourceTagsEntityInterface
     */
    public function createEntity(): ResourceTagsEntityInterface;

    /**
     * Create a resource_tags row linking the specified resources
     *
     * @param ResourceEntityInterface|int|null $resourceOrId Resource entity or ID to link up (optional)
     * @param TagsEntityInterface|int          $tagOrId      Tag entity or ID to link up
     * @param UserEntityInterface|int|null     $userOrId     User entity or ID creating link (optional but recommended)
     * @param UserListEntityInterface|int|null $listOrId     List entity or ID to link up (optional)
     * @param ?DateTime                        $posted       Posted date (optional -- omit for current)
     *
     * @return void
     */
    public function createLink(
        ResourceEntityInterface|int|null $resourceOrId,
        TagsEntityInterface|int $tagOrId,
        UserEntityInterface|int|null $userOrId = null,
        UserListEntityInterface|int|null $listOrId = null,
        ?DateTime $posted = null
    );

    /**
     * Remove links from the resource_tags table based on an array of IDs.
     *
     * @param string[] $ids Identifiers from resource_tags to delete.
     *
     * @return int          Count of $ids
     */
    public function deleteLinksByResourceTagsIdArray(array $ids): int;

    /**
     * Unlink rows for the specified resource.
     *
     * @param int|int[]|null                               $resourceId ID (or array of IDs) of resource(s) to
     * unlink (null for ALL matching resources)
     * @param UserEntityInterface|int                      $userOrId   ID or entity representing user
     * @param UserListEntityInterface|int|bool|string|null $listOrId   ID of list to unlink (null for ALL matching
     * tags, 'none' for tags not in a list, true for tags only found in a list)
     * @param int|int[]|null                               $tagId      ID or array of IDs of tag(s) to unlink (null
     * for ALL matching tags)
     *
     * @return void
     */
    public function destroyResourceLinks(
        int|array|null $resourceId,
        UserEntityInterface|int $userOrId,
        UserListEntityInterface|int|bool|string|null $listOrId = null,
        int|array|null $tagId = null
    );

    /**
     * Gets unique tagged resources from the database.
     *
     * @param ?int $userId     ID of user (null for any)
     * @param ?int $resourceId ID of the resource (null for any)
     * @param ?int $tagId      ID of the tag (null for any)
     *
     * @return array[]
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
     * @return array[]
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
     * @return array[]
     */
    public function getUniqueUsers(
        ?int $userId = null,
        ?int $resourceId = null,
        ?int $tagId = null
    ): array;

    /**
     * Delete resource tags rows matching specified filter(s). Return count of IDs deleted.
     *
     * @param ?int $userId     ID of user (null for any)
     * @param ?int $resourceId ID of the resource (null for any)
     * @param ?int $tagId      ID of the tag (null for any)
     *
     * @return int
     */
    public function deleteResourceTags(
        ?int $userId = null,
        ?int $resourceId = null,
        ?int $tagId = null
    ): int;
}
