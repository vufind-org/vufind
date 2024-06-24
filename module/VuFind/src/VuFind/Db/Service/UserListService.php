<?php

/**
 * Database service for UserList.
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

use Exception;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\ExpressionInterface;
use Laminas\Db\Sql\Select;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Entity\UserListEntityInterface;
use VuFind\Db\Table\DbTableAwareInterface;
use VuFind\Db\Table\DbTableAwareTrait;
use VuFind\Exception\RecordMissing as RecordMissingException;

use function is_int;

/**
 * Database service for UserList.
 *
 * @category VuFind
 * @package  Database
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
class UserListService extends AbstractDbService implements DbTableAwareInterface, UserListServiceInterface
{
    use DbTableAwareTrait;

    /**
     * Create a UserList entity object.
     *
     * @return UserListEntityInterface
     */
    public function createEntity(): UserListEntityInterface
    {
        return $this->getDbTable('UserList')->createRow();
    }

    /**
     * Delete a user list entity.
     *
     * @param UserListEntityInterface|int $listOrId List entity object or ID to delete
     *
     * @return void
     */
    public function deleteUserList(UserListEntityInterface|int $listOrId): void
    {
        $listId = $listOrId instanceof UserListEntityInterface ? $listOrId->getId() : $listOrId;
        $this->getDbTable('UserList')->delete(['id' => $listId]);
    }

    /**
     * Retrieve a list object.
     *
     * @param int $id Numeric ID for existing list.
     *
     * @return UserListEntityInterface
     * @throws RecordMissingException
     */
    public function getUserListById(int $id): UserListEntityInterface
    {
        $result = $this->getDbTable('UserList')->select(['id' => $id])->current();
        if (empty($result)) {
            throw new RecordMissingException('Cannot load list ' . $id);
        }
        return $result;
    }

    /**
     * Get public lists.
     *
     * @param array $includeFilter List of list ids or entities to include in result.
     * @param array $excludeFilter List of list ids or entities to exclude from result.
     *
     * @return UserListEntityInterface[]
     */
    public function getPublicLists(array $includeFilter = [], array $excludeFilter = []): array
    {
        $callback = function ($listOrId) {
            return $listOrId instanceof UserListEntityInterface ? $listOrId->getId() : $listOrId;
        };
        $includeIds = array_map($callback, $includeFilter);
        $excludeIds = array_map($callback, $excludeFilter);
        $callback = function ($select) use ($includeIds, $excludeIds) {
            $select->where->equalTo('public', 1);
            if ($excludeIds) {
                $select->where->notIn('id', $excludeIds);
            }
            if ($includeIds) {
                $select->where->in('id', $includeIds);
            }
        };
        return iterator_to_array($this->getDbTable('UserList')->select($callback));
    }

    /**
     * Get lists belonging to the user and their count. Returns an array of arrays with
     * list_entity and count keys.
     *
     * @param UserEntityInterface|int $userOrId User entity object or ID
     *
     * @return array
     * @throws Exception
     */
    public function getUserListsAndCountsByUser(UserEntityInterface|int $userOrId): array
    {
        $userId = $userOrId instanceof UserEntityInterface ? $userOrId->getId() : $userOrId;
        $callback = function (Select $select) use ($userId) {
            $select->columns(
                [
                    Select::SQL_STAR,
                    'cnt' => new Expression(
                        'COUNT(DISTINCT(?))',
                        ['ur.resource_id'],
                        [ExpressionInterface::TYPE_IDENTIFIER]
                    ),
                ]
            );
            $select->join(
                ['ur' => 'user_resource'],
                'user_list.id = ur.list_id',
                [],
                $select::JOIN_LEFT
            );
            $select->where->equalTo('user_list.user_id', $userId);
            $select->group(
                [
                    'user_list.id', 'user_list.user_id', 'title', 'description',
                    'created', 'public',
                ]
            );
            $select->order(['title']);
        };

        $result = [];
        foreach ($this->getDbTable('UserList')->select($callback) as $row) {
            $result[] = ['list_entity' => $row, 'count' => $row->cnt];
        }
        return $result;
    }

    /**
     * Get lists associated with a particular tag and/or list of IDs. If IDs and
     * tags are both provided, only the intersection of matches will be returned.
     *
     * @param string|string[]|null $tag               Tag or tags to match (by text, not ID; null for all)
     * @param int|int[]|null       $listId            List ID or IDs to match (null for all)
     * @param bool                 $publicOnly        Whether to return only public lists
     * @param bool                 $andTags           Use AND operator when filtering by tag.
     * @param bool                 $caseSensitiveTags Should we treat tags case-sensitively?
     *
     * @return UserListEntityInterface[]
     */
    public function getUserListsByTagAndId(
        string|array|null $tag = null,
        int|array|null $listId = null,
        bool $publicOnly = true,
        bool $andTags = true,
        bool $caseSensitiveTags = false
    ): array {
        $lists = $this->getDbTable('ResourceTags')
            ->getListsForTag($tag, $listId, $publicOnly, $andTags, $caseSensitiveTags);
        $listIds = array_column(iterator_to_array($lists), 'list_id');
        $callback = function ($select) use ($listIds) {
            $select->where->in('id', $listIds);
        };
        return iterator_to_array($this->getDbTable('UserList')->select($callback));
    }

    /**
     * Get list objects belonging to the specified user.
     *
     * @param UserEntityInterface|int $userOrId User entity object or ID
     *
     * @return UserListEntityInterface[]
     */
    public function getUserListsByUser(UserEntityInterface|int $userOrId): array
    {
        $userId = $userOrId instanceof UserEntityInterface ? $userOrId->getId() : $userOrId;
        $callback = function ($select) use ($userId) {
            $select->where->equalTo('user_id', $userId);
            $select->order(['title']);
        };
        return iterator_to_array($this->getDbTable('UserList')->select($callback));
    }

    /**
     * Get lists containing a specific record.
     *
     * @param string                       $recordId ID of record being checked.
     * @param string                       $source   Source of record to look up
     * @param UserEntityInterface|int|null $userOrId Optional user ID or entity object (to limit results
     * to a particular user).
     *
     * @return UserListEntityInterface[]
     */
    public function getListsContainingRecord(
        string $recordId,
        string $source = DEFAULT_SEARCH_BACKEND,
        UserEntityInterface|int|null $userOrId = null
    ): array {
        return iterator_to_array(
            $this->getDbTable('UserList')->getListsContainingResource(
                $recordId,
                $source,
                is_int($userOrId) ? $userOrId : $userOrId->getId()
            )
        );
    }
}
