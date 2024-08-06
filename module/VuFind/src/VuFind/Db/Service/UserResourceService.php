<?php

/**
 * Database service for UserResource.
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
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */

namespace VuFind\Db\Service;

use Exception;
use VuFind\Db\Entity\ResourceEntityInterface;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Entity\UserListEntityInterface;
use VuFind\Db\Entity\UserResourceEntityInterface;
use VuFind\Db\Table\DbTableAwareInterface;
use VuFind\Db\Table\DbTableAwareTrait;

use function is_int;

/**
 * Database service for UserResource.
 *
 * @category VuFind
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
class UserResourceService extends AbstractDbService implements
    DbTableAwareInterface,
    DbServiceAwareInterface,
    UserResourceServiceInterface
{
    use DbServiceAwareTrait;
    use DbTableAwareTrait;

    /**
     * Get information saved in a user's favorites for a particular record.
     *
     * @param string                           $recordId ID of record being checked.
     * @param string                           $source   Source of record to look up
     * @param UserListEntityInterface|int|null $listOrId Optional list entity or ID
     * (to limit results to a particular list).
     * @param UserEntityInterface|int|null     $userOrId Optional user entity or ID
     * (to limit results to a particular user).
     *
     * @return UserResourceEntityInterface[]
     */
    public function getFavoritesForRecord(
        string $recordId,
        string $source = DEFAULT_SEARCH_BACKEND,
        UserListEntityInterface|int|null $listOrId = null,
        UserEntityInterface|int|null $userOrId = null
    ): array {
        $listId = is_int($listOrId) ? $listOrId : $listOrId?->getId();
        $userId = is_int($userOrId) ? $userOrId : $userOrId?->getId();
        return iterator_to_array(
            $this->getDbTable('UserResource')->getSavedData($recordId, $source, $listId, $userId)
        );
    }

    /**
     * Get statistics on use of UserResource.
     *
     * @return array
     */
    public function getStatistics(): array
    {
        return $this->getDbTable('UserResource')->getStatistics();
    }

    /**
     * Create user/resource/list link if one does not exist; update notes if one does.
     *
     * @param ResourceEntityInterface|int $resourceOrId Entity or ID of resource to link up
     * @param UserEntityInterface|int     $userOrId     Entity or ID of user creating link
     * @param UserListEntityInterface|int $listOrId     Entity or ID of list to link up
     * @param string                      $notes        Notes to associate with link
     *
     * @return UserResource|false
     */
    public function createOrUpdateLink(
        ResourceEntityInterface|int $resourceOrId,
        UserEntityInterface|int $userOrId,
        UserListEntityInterface|int $listOrId,
        string $notes = ''
    ): UserResourceEntityInterface {
        $resource = $resourceOrId instanceof ResourceEntityInterface
            ? $resourceOrId : $this->getDbService(ResourceServiceInterface::class)->getResourceById($resourceOrId);
        if (!$resource) {
            throw new Exception("Cannot retrieve resource $resourceOrId");
        }
        $list = $listOrId instanceof UserListEntityInterface
            ? $listOrId : $this->getDbService(UserListServiceInterface::class)->getUserListById($listOrId);
        if (!$list) {
            throw new Exception("Cannot retrieve list $listOrId");
        }
        $user = $userOrId instanceof UserEntityInterface
            ? $userOrId : $this->getDbService(UserServiceInterface::class)->getUserById($userOrId);
        if (!$user) {
            throw new Exception("Cannot retrieve user $userOrId");
        }
        $params = [
            'resource_id' => $resource->getId(),
            'list_id' => $list->getId(),
            'user_id' => $user->getId(),
        ];
        if (!($result = $this->getDbTable('UserResource')->select($params)->current())) {
            $result = $this->createEntity()
                ->setResource($resource)
                ->setUser($user)
                ->setUserList($list);
        }
        // Update the notes:
        $result->setNotes($notes);
        $this->persistEntity($result);
        return $result;
    }

    /**
     * Unlink rows for the specified resource.
     *
     * @param int|int[]|null              $resourceId ID (or array of IDs) of resource(s) to unlink (null for ALL
     * matching resources)
     * @param UserEntityInterface|int     $userOrId   ID or entity representing user removing links
     * @param UserListEntityInterface|int $listOrId   ID or entity representing list to unlink (null for ALL
     * matching lists)
     *
     * @return void
     */
    public function unlinkFavorites(
        int|array|null $resourceId,
        UserEntityInterface|int $userOrId,
        UserListEntityInterface|int|null $listOrId = null
    ): void {
        // Build the where clause to figure out which rows to remove:
        $listId = is_int($listOrId) ? $listOrId : $listOrId?->getId();
        $userId = is_int($userOrId) ? $userOrId : $userOrId->getId();
        $callback = function ($select) use ($resourceId, $userId, $listId) {
            $select->where->equalTo('user_id', $userId);
            if (null !== $resourceId) {
                $select->where->in('resource_id', (array)$resourceId);
            }
            if (null !== $listId) {
                $select->where->equalTo('list_id', $listId);
            }
        };

        // Delete the rows:
        $this->getDbTable('UserResource')->delete($callback);
    }

    /**
     * Create a UserResource entity object.
     *
     * @return UserResourceEntityInterface
     */
    public function createEntity(): UserResourceEntityInterface
    {
        return $this->getDbTable('UserResource')->createRow();
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
        $this->getDbTable('UserResource')->update(['resource_id' => $new], ['resource_id' => $old]);
    }

    /**
     * Deduplicate rows (sometimes necessary after merging foreign key IDs).
     *
     * @return void
     */
    public function deduplicate(): void
    {
        $this->getDbTable('UserResource')->deduplicate();
    }
}
