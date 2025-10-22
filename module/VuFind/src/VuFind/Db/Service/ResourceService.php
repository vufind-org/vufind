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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
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
use Psr\Log\LoggerAwareInterface;
use VuFind\Db\Entity\PluginManager as EntityPluginManager;
use VuFind\Db\Entity\ResourceEntityInterface;
use VuFind\Db\Entity\ResourceTagsEntityInterface;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Entity\UserListEntityInterface;
use VuFind\Db\Entity\UserResourceEntityInterface;
use VuFind\Db\PersistenceManager;
use VuFind\Log\LoggerAwareTrait;

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
    LoggerAwareInterface
{
    use DbServiceAwareTrait;
    use Feature\ResourceSortTrait;
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
     * @param PersistenceManager  $persistenceManager      Entity persistence manager
     * @param callable            $resourcePopulatorLoader Resource populator
     */
    public function __construct(
        EntityManager $entityManager,
        EntityPluginManager $entityPluginManager,
        PersistenceManager $persistenceManager,
        callable $resourcePopulatorLoader
    ) {
        $this->resourcePopulatorLoader = $resourcePopulatorLoader;
        parent::__construct($entityManager, $entityPluginManager, $persistenceManager);
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
        $resource = $this->entityManager->find(ResourceEntityInterface::class, $id);
        return $resource;
    }

    /**
     * Create a resource entity object.
     *
     * @return ResourceEntityInterface
     */
    public function createEntity(): ResourceEntityInterface
    {
        return $this->entityPluginManager->get(ResourceEntityInterface::class);
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
            . 'FROM ' . ResourceEntityInterface::class . ' r '
            . "WHERE r.title = '' OR r.author IS NULL OR r.year IS NULL";

        $query = $this->entityManager->createQuery($dql);
        $result = $query->getResult();
        return $result;
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
        $repo = $this->entityManager->getRepository(ResourceEntityInterface::class);
        $criteria = [
            'recordId' => $ids,
            'source' => $source,
        ];
        return $repo->findBy($criteria);
    }

    /**
     * Get resources associated with a particular tag.
     *
     * @param string $tag               Tag to match
     * @param int    $user              ID of user owning favorite list
     * @param ?int   $list              ID of list to retrieve (null for all favorites)
     * @param bool   $caseSensitiveTags Should tags be treated case sensitively?
     *
     * @return array
     */
    protected function getResourceIDsForTag(
        string $tag,
        int $user,
        ?int $list = null,
        bool $caseSensitiveTags = false
    ): array {
        $dql = 'SELECT DISTINCT(rt.resource) AS resource_id '
            . 'FROM ' . ResourceTagsEntityInterface::class . ' rt '
            . 'JOIN rt.tag t '
            . 'WHERE ' . ($caseSensitiveTags ? 't.tag = :tag' : 'LOWER(t.tag) = LOWER(:tag) ')
            . 'AND rt.user = :user';

        $user = $this->getDoctrineReference(UserEntityInterface::class, $user);
        $parameters = compact('tag', 'user');
        if (null !== $list) {
            $list = $this->getDoctrineReference(UserListEntityInterface::class, $list);
            $dql .= ' AND rt.list = :list';
            $parameters['list'] = $list;
        }
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);
        return $query->getSingleColumnResult();
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
        $user = $this->getDoctrineReference(UserEntityInterface::class, $userOrId);
        $list = $listOrId ? $this->getDoctrineReference(UserListEntityInterface::class, $listOrId) : null;
        $orderByDetails = empty($sort) ? [] : $this->getResourceOrderByClause($sort);
        $dql = 'SELECT DISTINCT r';
        if (!empty($orderByDetails['extraSelect'])) {
            $dql .= ', ' . $orderByDetails['extraSelect'];
        }
        $dql .= ' FROM ' . ResourceEntityInterface::class . ' r '
            . 'JOIN ' . UserResourceEntityInterface::class . ' ur WITH r.id = ur.resource ';
        $dqlWhere = [];
        $dqlWhere[] = 'ur.user = :user';
        $parameters = compact('user');
        if (null !== $list) {
            $dqlWhere[] = 'ur.list = :list';
            $parameters['list'] = $list;
        }

        // Adjust for tags if necessary:
        if (!empty($tags)) {
            $matches = null;
            foreach ($tags as $tag) {
                $nextTagBatch = $this->getResourceIDsForTag($tag, $user->getId(), $list?->getId(), $caseSensitiveTags);
                $matches = array_intersect(
                    $matches ?? $nextTagBatch, // first time, use whole batch
                    $nextTagBatch
                );
            }
            $dqlWhere[] = 'r.id IN (:ids)';
            $parameters['ids'] = $matches;
        }
        $dql .= ' WHERE ' . implode(' AND ', $dqlWhere);
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
        $dql = 'DELETE FROM ' . ResourceEntityInterface::class . ' r '
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
        $dql = 'UPDATE ' . ResourceEntityInterface::class . ' r '
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
        $this->deleteEntity($this->getDoctrineReference(ResourceEntityInterface::class, $resourceOrId));
    }
}
