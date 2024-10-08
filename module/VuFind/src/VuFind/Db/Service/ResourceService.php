<?php

/**
 * Database service for resource.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */

namespace VuFind\Db\Service;

use Doctrine\ORM\EntityManager;
use Exception;
use Laminas\Log\LoggerAwareInterface;
use VuFind\Db\Entity\PluginManager as EntityPluginManager;
use VuFind\Db\Entity\Resource;
use VuFind\Db\Entity\ResourceEntityInterface;
use VuFind\Db\Entity\User;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Entity\UserList;
use VuFind\Db\Entity\UserListEntityInterface;
use VuFind\Db\Entity\UserResource;
use VuFind\Log\LoggerAwareTrait;

use function in_array;

/**
 * Database service for resource.
 *
 * @category VuFind
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
class ResourceService extends AbstractDbService implements
    ResourceServiceInterface,
    DbServiceAwareInterface,
    Feature\TransactionInterface,
    LoggerAwareInterface
{
    use DbServiceAwareTrait;
    use LoggerAwareTrait;

    /**
     * Callback to load the resource populator.
     *
     * @var callable
     */
    protected $resourcePopulatorLoader;

    /**
     * Constructor
     *
     * @param EntityManager       $entityManager           Doctrine ORM entity manager
     * @param EntityPluginManager $entityPluginManager     VuFind entity plugin manager
     * @param callable            $resourcePopulatorLoader Resource populator
     */
    public function __construct(
        EntityManager $entityManager,
        EntityPluginManager $entityPluginManager,
        callable $resourcePopulatorLoader
    ) {
        $this->resourcePopulatorLoader = $resourcePopulatorLoader;
        parent::__construct($entityManager, $entityPluginManager);
    }

    /**
     * Begin a database transaction.
     *
     * @return void
     * @throws Exception
     */
    public function beginTransaction(): void
    {
        $this->entityManager->getConnection()->beginTransaction();
    }

    /**
     * Commit a database transaction.
     *
     * @return void
     * @throws Exception
     */
    public function commitTransaction(): void
    {
        $this->entityManager->getConnection()->commit();
    }

    /**
     * Roll back a database transaction.
     *
     * @return void
     * @throws Exception
     */
    public function rollBackTransaction(): void
    {
        $this->entityManager->getConnection()->rollBack();
    }

    /**
     * Lookup and return a resource.
     *
     * @param int $id Identifier value
     *
     * @return ?ResourceEntityInterface
     */
    public function getResourceById(int $id): ?ResourceEntityInterface
    {
        $resource = $this->entityManager->find(
            $this->getEntityClass(\VuFind\Db\Entity\Resource::class),
            $id
        );
        return $resource;
    }

    /**
     * Create a resource entity object.
     *
     * @return ResourceEntityInterface
     */
    public function createEntity(): ResourceEntityInterface
    {
        $class = $this->getEntityClass(Resource::class);
        return new $class();
    }

    /**
     * Get a set of records that do not have metadata stored in the resource
     * table.
     *
     * @return ResourceEntityInterface[]
     */
    public function findMissingMetadata(): array
    {
        $dql = 'SELECT r '
            . 'FROM ' . $this->getEntityClass(Resource::class) . ' r '
            . "WHERE r.title = '' OR r.author IS NULL OR r.year IS NULL";

        $query = $this->entityManager->createQuery($dql);
        $result = $query->getResult();
        return $result;
    }

    /**
     * Apply a sort parameter to a query on the resource table. Returns an
     * array with two keys: 'orderByClause' (the actual ORDER BY) and
     * 'extraSelect' (extra values to add to SELECT, if necessary)
     *
     * @param string $sort  Field to use for sorting (may include
     *                      'desc' qualifier)
     * @param string $alias Alias to the resource table (defaults to 'r')
     *
     * @return array
     */
    public static function getOrderByClause(string $sort, string $alias = 'r'): array
    {
        // Apply sorting, if necessary:
        $legalSorts = [
            'title', 'title desc', 'author', 'author desc', 'year', 'year desc', 'last_saved', 'last_saved desc',
        ];
        $orderByClause = $extraSelect = '';
        if (!empty($sort) && in_array(strtolower($sort), $legalSorts)) {
            // Strip off 'desc' to obtain the raw field name -- we'll need it
            // to sort null values to the bottom:
            $parts = explode(' ', $sort);
            $rawField = trim($parts[0]);

            // Start building the list of sort fields:
            $order = [];

            // Only include the table alias on non-virtual fields:
            $fieldPrefix = (strtolower($rawField) === 'last_saved') ? '' : "$alias.";

            // The title field can't be null, so don't bother with the extra
            // isnull() sort in that case.
            if (strtolower($rawField) === 'title') {
                // Do nothing
            } elseif (strtolower($rawField) === 'last_saved') {
                $extraSelect = 'ur.saved AS HIDDEN last_saved, '
                    . 'CASE WHEN ur.saved IS NULL THEN 1 ELSE 0 END AS HIDDEN last_savedsort';
                $order[] = 'last_savedsort';
            } else {
                $extraSelect = 'CASE WHEN ' . $fieldPrefix . $rawField . ' IS NULL THEN 1 ELSE 0 END AS HIDDEN '
                    . $rawField . 'sort';
                $order[] = "{$rawField}sort";
            }

            // Apply the user-specified sort:
            $order[] = $fieldPrefix . $sort;
            // Inject the sort preferences into the query object:
            $orderByClause = ' ORDER BY ' . implode(', ', $order);
        }
        return compact('orderByClause', 'extraSelect');
    }

    /**
     * Retrieve a single resource row by record ID/source. Return null if it does not exist.
     *
     * @param string $id     Record ID
     * @param string $source Record source
     *
     * @return ?ResourceEntityInterface
     */
    public function getResourceByRecordId(string $id, string $source = DEFAULT_SEARCH_BACKEND): ?ResourceEntityInterface
    {
        return current($this->getResourcesByRecordIds([$id], $source)) ?: null;
    }

    /**
     * Retrieve resource entities matching a set of specified records.
     *
     * @param string[] $ids    Array of IDs
     * @param string   $source Source of records to look up
     *
     * @return ResourceEntityInterface[]
     */
    public function getResourcesByRecordIds(array $ids, string $source = DEFAULT_SEARCH_BACKEND): array
    {
        $repo = $this->entityManager->getRepository($this->getEntityClass(Resource::class));
        $criteria = [
            'recordId' => $ids,
            'source' => $source,
        ];
        return $repo->findBy($criteria);
    }

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
    ): array {
        $user = $this->getDoctrineReference(User::class, $userOrId);
        $list = $listOrId ? $this->getDoctrineReference(UserList::class, $listOrId) : null;
        $orderByDetails = empty($sort) ? [] : ResourceService::getOrderByClause($sort);
        $dql = 'SELECT DISTINCT r';
        if (!empty($orderByDetails['extraSelect'])) {
            $dql .= ', ' . $orderByDetails['extraSelect'];
        }
        $dql .= ' FROM ' . $this->getEntityClass(Resource::class) . ' r '
            . 'JOIN ' . $this->getEntityClass(UserResource::class) . ' ur WITH r.id = ur.resource ';
        $dqlWhere = [];
        $dqlWhere[] = 'ur.user = :user';
        $parameters = compact('user');
        if (null !== $list) {
            $dqlWhere[] = 'ur.list = :list';
            $parameters['list'] = $list;
        }

        // Adjust for tags if necessary:
        if (!empty($tags)) {
            $linkingTable = $this->getDbService(TagService::class);
            $matches = [];
            foreach ($tags as $tag) {
                $matches[] = $linkingTable
                    ->getResourceIDsForTag($tag, $user->getId(), $list?->getId(), $caseSensitiveTags);
            }
            $dqlWhere[] = 'r.id IN (:ids)';
            $parameters['ids'] = $matches;
        }
        $dql .= ' WHERE ' . implode(' AND ', $dqlWhere);
        //$dql .= ' GROUP BY r.id';
        if (!empty($orderByDetails['orderByClause'])) {
            $dql .= $orderByDetails['orderByClause'];
        }

        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);

        if ($offset > 0) {
            $query->setFirstResult($offset);
        }
        if (null !== $limit) {
            $query->setMaxResults($limit);
        }

        $result = $query->getResult();
        return $result;
    }

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
    public function deleteResourceByRecordId(string $id, string $source): bool
    {
        $dql = 'DELETE FROM ' . $this->getEntityClass(Resource::class) . ' r '
            . 'WHERE r.recordId = :id AND r.source = :source';
        $parameters = compact('id', 'source');
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);
        return $query->execute();
    }

    /**
     * Globally change the name of a source value in the database; return the number of rows affected.
     *
     * @param string $old Old source value
     * @param string $new New source value
     *
     * @return int
     */
    public function renameSource(string $old, string $new): int
    {
        $dql = 'UPDATE ' . $this->getEntityClass(Resource::class) . ' r '
            . 'SET r.source=:new WHERE r.source=:old';
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters(compact('new', 'old'));
        return $query->execute();
    }

    /**
     * Delete a resource entity.
     *
     * @param ResourceEntityInterface|int $resourceOrId Resource entity or ID value.
     *
     * @return void
     */
    public function deleteResource(ResourceEntityInterface|int $resourceOrId): void
    {
        $this->deleteEntity($this->getDoctrineReference(UserResource::class, $resourceOrId));
    }
}
