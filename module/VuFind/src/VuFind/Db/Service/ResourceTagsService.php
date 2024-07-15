<?php

/**
 * Database service for resource_tags.
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

use function is_int;

/**
 * Database service for resource_tags.
 *
 * @category VuFind
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
class ResourceTagsService extends AbstractDbService implements
    ResourceTagsServiceInterface,
    Feature\TransactionInterface,
    \VuFind\Db\Table\DbTableAwareInterface
{
    use \VuFind\Db\Table\DbTableAwareTrait;

    /**
     * Begin a database transaction.
     *
     * @return void
     * @throws Exception
     */
    public function beginTransaction(): void
    {
        $this->getDbTable('ResourceTags')->beginTransaction();
    }

    /**
     * Commit a database transaction.
     *
     * @return void
     * @throws Exception
     */
    public function commitTransaction(): void
    {
        $this->getDbTable('ResourceTags')->commitTransaction();
    }

    /**
     * Roll back a database transaction.
     *
     * @return void
     * @throws Exception
     */
    public function rollBackTransaction(): void
    {
        $this->getDbTable('ResourceTags')->rollbackTransaction();
    }

    /**
     * Get Resource Tags Paginator
     *
     * @param ?int    $userId            ID of user (null for any)
     * @param ?int    $resourceId        ID of the resource (null for any)
     * @param ?int    $tagId             ID of the tag (null for any)
     * @param ?string $order             The order in which to return the data
     * @param ?int    $page              The page number to select
     * @param int     $limit             The number of items to fetch
     * @param bool    $caseSensitiveTags Should we treat tags as case-sensitive?
     *
     * @return Paginator
     */
    public function getResourceTagsPaginator(
        ?int $userId = null,
        ?int $resourceId = null,
        ?int $tagId = null,
        ?string $order = null,
        ?int $page = null,
        int $limit = 20,
        bool $caseSensitiveTags = false
    ): Paginator {
        return $this->getDbTable('ResourceTags')
            ->getResourceTags($userId, $resourceId, $tagId, $order, $page, $limit, $caseSensitiveTags);
    }

    /**
     * Create a ResourceTagsEntityInterface object.
     *
     * @return ResourceTagsEntityInterface
     */
    public function createEntity(): ResourceTagsEntityInterface
    {
        return $this->getDbTable('ResourceTags')->createRow();
    }

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
    ) {
        $table = $this->getDbTable('ResourceTags');
        $resourceId = is_int($resourceOrId) ? $resourceOrId : $resourceOrId?->getId();
        $tagId = is_int($tagOrId) ? $tagOrId : $tagOrId->getId();
        $userId = is_int($userOrId) ? $userOrId : $userOrId?->getId();
        $listId = is_int($listOrId) ? $listOrId : $listOrId?->getId();

        $callback = function ($select) use ($resourceId, $tagId, $userId, $listId) {
            $select->where->equalTo('resource_id', $resourceId)
                ->equalTo('tag_id', $tagId);
            if (null !== $listId) {
                $select->where->equalTo('list_id', $listId);
            } else {
                $select->where->isNull('list_id');
            }
            if (null !== $userId) {
                $select->where->equalTo('user_id', $userId);
            } else {
                $select->where->isNull('user_id');
            }
        };
        $result = $table->select($callback)->current();

        // Only create row if it does not already exist:
        if (!$result) {
            $result = $this->createEntity();
            $result->resource_id = $resourceId;
            $result->tag_id = $tagId;
            if (null !== $listId) {
                $result->list_id = $listId;
            }
            if (null !== $userId) {
                $result->user_id = $userId;
            }
            $result->setPosted($posted ?? new DateTime());
            $this->persistEntity($result);
        }
    }

    /**
     * Remove links from the resource_tags table based on an array of IDs.
     *
     * @param string[] $ids Identifiers from resource_tags to delete.
     *
     * @return int          Count of $ids
     */
    public function deleteLinksByResourceTagsIdArray(array $ids): int
    {
        return $this->getDbTable('ResourceTags')->deleteByIdArray($ids);
    }

    /**
     * Unlink tag rows for the specified resource and user.
     *
     * @param int|int[]|null                   $resourceId ID (or array of IDs) of resource(s) to
     * unlink (null for ALL matching resources)
     * @param UserEntityInterface|int          $userOrId   ID or entity representing user
     * @param UserListEntityInterface|int|null $listOrId   ID of list to unlink (null for ALL matching tags)
     * @param int|int[]|null                   $tagId      ID or array of IDs of tag(s) to unlink (null
     * for ALL matching tags)
     *
     * @return void
     */
    public function destroyResourceTagsLinksForUser(
        int|array|null $resourceId,
        UserEntityInterface|int $userOrId,
        UserListEntityInterface|int|null $listOrId = null,
        int|array|null $tagId = null
    ): void {
        $userId = $userOrId instanceof UserEntityInterface ? $userOrId->getId() : $userOrId;
        $listId = $listOrId instanceof UserListEntityInterface ? $listOrId->getId() : $listOrId;
        $callback = function ($select) use ($resourceId, $userId, $listId, $tagId) {
            $select->where->equalTo('user_id', $userId);
            if (null !== $resourceId) {
                $select->where->in('resource_id', (array)$resourceId);
            }
            if (null !== $listId) {
                $select->where->equalTo('list_id', $listId);
            }
            if (null !== $tagId) {
                $select->where->in('tag_id', (array)$tagId);
            }
        };
        $this->getDbTable('ResourceTags')->delete($callback);
    }

    /**
     * Unlink tag rows that are not associated with a favorite list for the specified resource and user.
     *
     * @param int|int[]|null          $resourceId ID (or array of IDs) of resource(s) to unlink (null for ALL matching
     * resources)
     * @param UserEntityInterface|int $userOrId   ID or entity representing user
     * @param int|int[]|null          $tagId      ID or array of IDs of tag(s) to unlink (null for ALL matching tags)
     *
     * @return void
     */
    public function destroyNonListResourceTagsLinksForUser(
        int|array|null $resourceId,
        UserEntityInterface|int $userOrId,
        int|array|null $tagId = null
    ): void {
        $userId = $userOrId instanceof UserEntityInterface ? $userOrId->getId() : $userOrId;
        $callback = function ($select) use ($resourceId, $userId, $tagId) {
            $select->where->equalTo('user_id', $userId);
            if (null !== $resourceId) {
                $select->where->in('resource_id', (array)$resourceId);
            }
            $select->where->isNull('list_id');
            if (null !== $tagId) {
                $select->where->in('tag_id', (array)$tagId);
            }
        };
        $this->getDbTable('ResourceTags')->delete($callback);
    }

    /**
     * Unlink all tag rows associated with favorite lists for the specified resource and user. Tags added directly
     * to records outside of favorites will not be impacted.
     *
     * @param int|int[]|null          $resourceId ID (or array of IDs) of resource(s) to unlink (null for ALL matching
     * resources)
     * @param UserEntityInterface|int $userOrId   ID or entity representing user
     * @param int|int[]|null          $tagId      ID or array of IDs of tag(s) to unlink (null for ALL matching tags)
     *
     * @return void
     */
    public function destroyAllListResourceTagsLinksForUser(
        int|array|null $resourceId,
        UserEntityInterface|int $userOrId,
        int|array|null $tagId = null
    ): void {
        $userId = $userOrId instanceof UserEntityInterface ? $userOrId->getId() : $userOrId;
        $callback = function ($select) use ($resourceId, $userId, $tagId) {
            $select->where->equalTo('user_id', $userId);
            if (null !== $resourceId) {
                $select->where->in('resource_id', (array)$resourceId);
            }
            $select->where->isNotNull('list_id');
            if (null !== $tagId) {
                $select->where->in('tag_id', (array)$tagId);
            }
        };
        $this->getDbTable('ResourceTags')->delete($callback);
    }

    /**
     * Unlink rows for the specified user list. This removes tags ON THE LIST ITSELF, not tags on
     * resources within the list.
     *
     * @param UserListEntityInterface|int $listOrId ID or entity representing list
     * @param UserEntityInterface|int     $userOrId ID or entity representing user
     * @param int|int[]|null              $tagId    ID or array of IDs of tag(s) to unlink (null for ALL matching tags)
     *
     * @return void
     */
    public function destroyUserListLinks(
        UserListEntityInterface|int $listOrId,
        UserEntityInterface|int $userOrId,
        int|array|null $tagId = null
    ): void {
        $listId = $listOrId instanceof UserListEntityInterface ? $listOrId->getId() : $listOrId;
        $userId = $userOrId instanceof UserEntityInterface ? $userOrId->getId() : $userOrId;
        $callback = function ($select) use ($userId, $listId, $tagId) {
            $select->where->equalTo('user_id', $userId);
            // retrieve tags assigned to a user list and filter out user resource tags
            // (resource_id is NULL for list tags).
            $select->where->isNull('resource_id');
            $select->where->equalTo('list_id', $listId);

            if (null !== $tagId) {
                $select->where->in('tag_id', (array)$tagId);
            }
        };
        $this->getDbTable('ResourceTags')->delete($callback);
    }

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
    ): array {
        return $this->getDbTable('ResourceTags')->getUniqueResources($userId, $resourceId, $tagId)->toArray();
    }

    /**
     * Gets unique tags from the database.
     *
     * @param ?int $userId        ID of user (null for any)
     * @param ?int $resourceId    ID of the resource (null for any)
     * @param ?int $tagId         ID of the tag (null for any)
     * @param bool $caseSensitive Should we treat tags in a case-sensitive manner?
     *
     * @return array[]
     */
    public function getUniqueTags(
        ?int $userId = null,
        ?int $resourceId = null,
        ?int $tagId = null,
        bool $caseSensitive = false
    ): array {
        return $this->getDbTable('ResourceTags')->getUniqueTags($userId, $resourceId, $tagId, $caseSensitive)
            ->toArray();
    }

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
    ): array {
        return $this->getDbTable('ResourceTags')->getUniqueUsers($userId, $resourceId, $tagId)->toArray();
    }

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
    ): int {
        $deleted = 0;
        while (true) {
            $nextBatch = $this->getResourceTagsPaginator($userId, $resourceId, $tagId);
            if ($nextBatch->getTotalItemCount() < 1) {
                return $deleted;
            }
            $ids = [];
            foreach ($nextBatch as $row) {
                $ids[] = $row['id'];
            }
            $deleted += $this->deleteLinksByResourceTagsIdArray($ids);
        }
    }

    /**
     * Get count of anonymous tags
     *
     * @return int count
     */
    public function getAnonymousCount(): int
    {
        return $this->getDbTable('ResourceTags')->getAnonymousCount();
    }

    /**
     * Assign anonymous tags to the specified user.
     *
     * @param UserEntityInterface|int $userOrId User entity or ID to own anonymous tags.
     *
     * @return void
     */
    public function assignAnonymousTags(UserEntityInterface|int $userOrId): void
    {
        $userId = $userOrId instanceof UserEntityInterface ? $userOrId->getId() : $userOrId;
        $this->getDbTable('ResourceTags')->assignAnonymousTags($userId);
    }

    /**
     * Change all matching rows to use the new resource ID instead of the old one (called when an ID changes).
     *
     * @param int $old Original resource ID
     * @param int $new New resource ID
     *
     * @return void
     */
    public function changeResourceId(int $old, int $new): void
    {
        $this->getDbTable('ResourceTags')->update(['resource_id' => $new], ['resource_id' => $old]);
    }

    /**
     * Deduplicate rows (sometimes necessary after merging foreign key IDs).
     *
     * @return void
     */
    public function deduplicate(): void
    {
        $this->getDbTable('ResourceTags')->deduplicate();
    }
}
