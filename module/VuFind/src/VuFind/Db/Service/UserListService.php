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
use Laminas\Log\LoggerAwareInterface;
use VuFind\Db\Entity\Resource;
use VuFind\Db\Entity\User;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Entity\UserList;
use VuFind\Db\Entity\UserListEntityInterface;
use VuFind\Db\Entity\UserResource;
use VuFind\Exception\RecordMissing as RecordMissingException;
use VuFind\Log\LoggerAwareTrait;
use VuFind\Tags;

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
class UserListService extends AbstractDbService implements
    UserListServiceInterface,
    LoggerAwareInterface,
    DbServiceAwareInterface
{
    use LoggerAwareTrait;
    use DbServiceAwareTrait;

    /**
     * Get an array of resource tags associated with the list.
     *
     * @param UserList $list UserList object.
     *
     * @return array
     */
    public function getResourceTags($list)
    {
        $user = $list->getUser();
        $tags = $this->getDbService(TagService::class)->getUserTagsFromFavorites($user, $list);
        return $tags;
    }

    /**
     * Create a UserList entity object.
     *
     * @return UserListEntityInterface
     */
    public function createEntity(): UserListEntityInterface
    {
        $class = $this->getEntityClass(UserList::class);
        return new $class();
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
        $this->deleteEntity($this->getDoctrineReference(UserList::class, $listOrId));
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
        $result = $this->getEntityById(\VuFind\Db\Entity\UserList::class, $id);
        if (empty($result)) {
            throw new RecordMissingException('Cannot load list ' . $id);
        }
        return $result;
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
        $dql = 'SELECT ul FROM ' . $this->getEntityClass(UserList::class) . ' ul '
            . 'JOIN ' . $this->getEntityClass(UserResource::class) . ' ur WITH ur.list = ul.id '
            . 'JOIN ' . $this->getEntityClass(Resource::class) . ' r WITH r.id = ur.resource '
            . 'WHERE r.recordId = :recordId AND r.source = :source ';

        $parameters = compact('recordId', 'source');
        if (null !== $userOrId) {
            $userId = $userOrId instanceof UserEntityInterface ? $userOrId->getId() : $userOrId;
            $dql .= 'AND ur.user = :userId ';
            $parameters['userId'] = $userId;
        }

        $dql .= 'ORDER BY ul.title';
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);
        $results = $query->getResult();
        return $results;
    }

    /**
     * Get public lists.
     *
     * @param array $includeFilter List of list ids to include in result.
     * @param array $excludeFilter List of list entities to exclude from result.
     *
     * @return array
     */
    public function getPublicLists($includeFilter = [], $excludeFilter = [])
    {
        $dql = 'SELECT ul FROM ' . $this->getEntityClass(UserList::class) . ' ul ';

        $parameters = [];
        $where = ["ul.public = '1'"];
        if (!empty($includeFilter)) {
            $where[] = 'ul.id IN (:includeFilter)';
            $parameters['includeFilter'] = $includeFilter;
        }
        if (!empty($excludeFilter)) {
            $where[] = 'ul NOT IN (:excludeFilter)';
            $parameters['excludeFilter'] = $excludeFilter;
        }
        $dql .= 'WHERE ' . implode(' AND ', $where);

        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);
        $results = $query->getResult();
        return $results;
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
        $dql = 'SELECT ul AS list_entity, COUNT(DISTINCT(ur.resource)) AS count '
            . 'FROM ' . $this->getEntityClass(UserList::class) . ' ul '
            . 'LEFT JOIN ' . $this->getEntityClass(UserResource::class) . ' ur WITH ur.list = ul.id '
            . 'WHERE ul.user = :user '
            . 'GROUP BY ul '
            . 'ORDER BY ul.title';

        $parameters = ['user' => $this->getDoctrineReference(User::class, $userOrId)];
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);
        $results = $query->getResult();
        return $results;
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
        $dql = 'SELECT ul '
            . 'FROM ' . $this->getEntityClass(UserList::class) . ' ul '
            . 'WHERE ul.user = :user '
            . 'ORDER BY ul.title';

        $parameters = ['user' => $this->getDoctrineReference(User::class, $userOrId)];
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);
        $results = $query->getResult();
        return $results;
    }

    /**
     * Retrieve a batch of list objects corresponding to the provided IDs
     *
     * @param array $ids List ids.
     *
     * @return array
     */
    public function getListsById($ids)
    {
        $dql = 'SELECT ul FROM ' . $this->getEntityClass(UserList::class) . ' ul '
            . 'WHERE ul.id IN (:ids)';
        $parameters = compact('ids');
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);
        $results = $query->getResult();
        return $results;
    }
}
