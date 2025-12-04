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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Database
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */

namespace VuFind\Db\Service;

use Exception;
use Psr\Log\LoggerAwareInterface;
use VuFind\Db\Entity\ResourceEntityInterface;
use VuFind\Db\Entity\ResourceTagsEntityInterface;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Entity\UserListEntityInterface;
use VuFind\Db\Entity\UserResourceEntityInterface;
use VuFind\Exception\RecordMissing as RecordMissingException;
use VuFind\Log\LoggerAwareTrait;

use function count;

/**
 * Database service for UserList.
 *
 * @category VuFind
 * @package  Database
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
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
     * Create a UserList entity object.
     *
     * @return UserListEntityInterface
     */
    public function createEntity(): UserListEntityInterface
    {
        return $this->entityPluginManager->get(UserListEntityInterface::class);
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
        $this->deleteEntity($this->getDoctrineReference(UserListEntityInterface::class, $listOrId));
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
        $result = $this->getEntityById(\VuFind\Db\Entity\UserListEntityInterface::class, $id);
        if (empty($result)) {
            throw new RecordMissingException('Cannot load list ' . $id);
        }
        return $result;
    }

    /**
     * Get public lists.
     *
     * @param array           $includeFilter List of list ids or entities to include in result.
     * @param array           $excludeFilter List of list ids or entities to exclude from result.
     * @param string|string[] $types         Types of user lists to get. Set to an empty array to get all.
     *
     * @return UserListEntityInterface[]
     */
    public function getPublicLists(
        array $includeFilter = [],
        array $excludeFilter = [],
        string|array $types = [UserListEntityInterface::TYPE_DEFAULT]
    ): array {
        $dql = 'SELECT ul FROM ' . UserListEntityInterface::class . ' ul '
            . 'WHERE ul.public = :public ';

        $parameters = ['public' => true];
        if ($includeFilter) {
            $dql .= 'AND ul IN (:includeFilter) ';
            $parameters['includeFilter'] = $includeFilter;
        }
        if ($excludeFilter) {
            $dql .= 'AND ul NOT IN (:excludeFilter) ';
            $parameters['excludeFilter'] = $excludeFilter;
        }
        $this->addTypesCheck($dql, $parameters, $types);

        $query = $this->entityManager->createQuery(trim($dql));
        $query->setParameters($parameters);
        $results = $query->getResult();
        return $results;
    }

    /**
     * Get lists belonging to the user and their count. Returns an array of arrays with
     * list_entity and count keys.
     *
     * @param UserEntityInterface|int $userOrId User entity object or ID
     * @param string|string[]         $types    Types of user lists to get. Set to an empty array to get all.
     *
     * @return array
     * @throws Exception
     */
    public function getUserListsAndCountsByUser(
        UserEntityInterface|int $userOrId,
        string|array $types = [UserListEntityInterface::TYPE_DEFAULT]
    ): array {
        $parameters = [
            'user' => $this->getDoctrineReference(UserEntityInterface::class, $userOrId),
        ];
        $dql = 'SELECT ul AS list_entity, COUNT(DISTINCT(ur.resource)) AS count '
            . 'FROM ' . UserListEntityInterface::class . ' ul '
            . 'LEFT JOIN ' . UserResourceEntityInterface::class . ' ur WITH ur.list = ul.id '
            . 'WHERE ul.user = :user ';

        $this->addTypesCheck($dql, $parameters, $types);

        $dql .= 'GROUP BY ul '
            . 'ORDER BY ul.title';

        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);
        $results = $query->getResult();
        return $results;
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
     * @param string|string[]      $types             Types of user lists to get. Set to an empty array to get all.
     *
     * @return UserListEntityInterface[]
     */
    public function getUserListsByTagAndId(
        string|array|null $tag = null,
        int|array|null $listId = null,
        bool $publicOnly = true,
        bool $andTags = true,
        bool $caseSensitiveTags = false,
        string|array $types = [UserListEntityInterface::TYPE_DEFAULT]
    ): array {
        $tag = $tag ? (array)$tag : null;
        $listId = $listId ? (array)$listId : null;
        $dql = 'SELECT IDENTITY(rt.list) '
            . 'FROM ' . ResourceTagsEntityInterface::class . ' rt '
            . 'JOIN rt.tag t '
            . 'JOIN rt.list ul '
            // Discard tags assigned to a user resource:
            . 'WHERE rt.resource IS NULL '
            // Restrict to tags by list owner:
            . 'AND rt.user = ul.user ';
        $parameters = [];
        if (null !== $listId) {
            $dql .= 'AND rt.list IN (:listId) ';
            $parameters['listId'] = $listId;
        }
        if ($publicOnly) {
            $dql .= 'AND ul.public = :public ';
            $parameters['public'] = true;
        }
        $this->addTypesCheck($dql, $parameters, $types);

        if ($tag) {
            if ($caseSensitiveTags) {
                $dql .= 'AND t.tag IN (:tag) ';
                $parameters['tag'] = $tag;
            } else {
                $tagClauses = [];
                foreach ($tag as $i => $currentTag) {
                    $tagPlaceholder = 'tag' . $i;
                    $tagClauses[] = 'LOWER(t.tag) = LOWER(:' . $tagPlaceholder . ')';
                    $parameters[$tagPlaceholder] = $currentTag;
                }
                $dql .= 'AND (' . implode(' OR ', $tagClauses) . ')';
            }
        }
        $dql .= ' GROUP BY rt.list ';
        if ($tag && $andTags) {
            // If we are ANDing the tags together, only pick lists that match ALL tags:
            $dql .= 'HAVING COUNT(DISTINCT(rt.tag)) = :cnt ';
            $parameters['cnt'] = count(array_unique($tag));
        }
        $dql .= 'ORDER BY rt.list';
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);
        return $this->getUserListsById($query->getSingleColumnResult());
    }

    /**
     * Get list objects belonging to the specified user.
     *
     * @param UserEntityInterface|int $userOrId User entity object or ID
     * @param string|string[]         $types    Types of user lists to get. Set to an empty array to get all.
     *
     * @return UserListEntityInterface[]
     */
    public function getUserListsByUser(
        UserEntityInterface|int $userOrId,
        string|array $types = [UserListEntityInterface::TYPE_DEFAULT]
    ): array {
        $dql = 'SELECT ul '
            . 'FROM ' . UserListEntityInterface::class . ' ul '
            . 'WHERE ul.user = :user ';

        $parameters = [
            'user' => $this->getDoctrineReference(UserEntityInterface::class, $userOrId),
        ];
        $this->addTypesCheck($dql, $parameters, $types);

        $dql .= 'ORDER BY ul.title';
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);
        $results = $query->getResult();
        return $results;
    }

    /**
     * Retrieve a batch of list objects corresponding to the provided IDs
     *
     * @param int[]           $ids   List ids.
     * @param string|string[] $types Types of user lists to get. Set to an empty array to get all.
     *
     * @return array
     */
    protected function getUserListsById(
        array $ids,
        string|array $types = [UserListEntityInterface::TYPE_DEFAULT]
    ): array {
        $dql = 'SELECT ul FROM ' . UserListEntityInterface::class . ' ul '
            . 'WHERE ul.id IN (:ids)';
        $parameters = compact('ids');
        $this->addTypesCheck($dql, $parameters, $types);

        $query = $this->entityManager->createQuery(trim($dql));
        $query->setParameters($parameters);
        $results = $query->getResult();
        return $results;
    }

    /**
     * Get lists containing a specific record.
     *
     * @param string                       $recordId ID of record being checked.
     * @param string                       $source   Source of record to look up
     * @param UserEntityInterface|int|null $userOrId Optional user ID or entity object (to limit results
     * to a particular user).
     * @param string|string[]              $types    Types of user lists to get. Set to an empty array to get all.
     *
     * @return UserListEntityInterface[]
     */
    public function getListsContainingRecord(
        string $recordId,
        string $source = DEFAULT_SEARCH_BACKEND,
        UserEntityInterface|int|null $userOrId = null,
        string|array $types = [UserListEntityInterface::TYPE_DEFAULT]
    ): array {
        $dql = 'SELECT ul FROM ' . UserListEntityInterface::class . ' ul '
            . 'JOIN ' . UserResourceEntityInterface::class . ' ur WITH ur.list = ul.id '
            . 'JOIN ' . ResourceEntityInterface::class . ' r WITH r.id = ur.resource '
            . 'WHERE r.recordId = :recordId AND r.source = :source ';

        $parameters = compact('recordId', 'source');
        $this->addTypesCheck($dql, $parameters, $types);

        if (null !== $userOrId) {
            $dql .= 'AND ur.user = :user ';
            $parameters['user'] = $this->getDoctrineReference(UserEntityInterface::class, $userOrId);
        }

        $dql .= 'ORDER BY ul.title';
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);
        $results = $query->getResult();
        return $results;
    }

    /**
     * Helper function to append type check into a dql
     *
     * @param string          $dql        Dql string
     * @param array           $parameters Parameters for dql
     * @param string|string[] $types      Types of user lists to get. Set to an empty array to get all.
     *
     * @return void
     */
    protected function addTypesCheck(string &$dql, array &$parameters, string|array $types): void
    {
        if (!$types) {
            return;
        }
        $dql .= 'AND ul.type IN (:types) ';
        $parameters['types'] = (array)$types;
    }
}
