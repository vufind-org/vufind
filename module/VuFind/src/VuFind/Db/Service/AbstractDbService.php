<?php

/**
 * Database service abstract base class
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2021.
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

use Doctrine\ORM\EntityManager;
use VuFind\Db\Entity\EntityInterface;
use VuFind\Db\Entity\PluginManager as EntityPluginManager;
use VuFind\Db\PersistenceManager;

use function is_callable;
use function is_int;

/**
 * Database service abstract base class
 *
 * @category VuFind
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
abstract class AbstractDbService implements DbServiceInterface
{
    /**
     * Variable to allow multiple functions to use the same retry count if necessary.
     * How many times can a function try, before continuing?
     *
     * @var int
     */
    protected int $retryCount = 5;

    /**
     * Constructor
     *
     * @param EntityManager       $entityManager       Doctrine ORM entity manager
     * @param EntityPluginManager $entityPluginManager Database entity plugin manager
     * @param PersistenceManager  $persistenceManager  Entity persistence manager
     */
    public function __construct(
        protected EntityManager $entityManager,
        protected EntityPluginManager $entityPluginManager,
        protected PersistenceManager $persistenceManager
    ) {
    }

    /**
     * Persist an entity.
     *
     * @param EntityInterface $entity Entity to persist.
     *
     * @return void
     */
    public function persistEntity(EntityInterface $entity): void
    {
        $this->persistenceManager->persistEntity($entity);
    }

    /**
     * Delete an entity.
     *
     * @param EntityInterface $entity Entity to persist.
     *
     * @return void
     */
    public function deleteEntity(EntityInterface $entity): void
    {
        $this->persistenceManager->deleteEntity($entity);
    }

    /**
     * Get a Doctrine reference for an entity or ID.
     *
     * @param class-string<T>     $desiredClass Desired Doctrine entity class
     * @param int|EntityInterface $objectOrId   Object or identifier to convert to entity
     *
     * @template T
     *
     * @return T
     */
    public function getDoctrineReference(string $desiredClass, int|EntityInterface $objectOrId): EntityInterface
    {
        if ($objectOrId instanceof $desiredClass) {
            return $objectOrId;
        }
        if (is_int($objectOrId)) {
            $id = $objectOrId;
        } else {
            if (!is_callable([$objectOrId, 'getId'])) {
                throw new \Exception('No getId() method on ' . $objectOrId::class);
            }
            $id = $objectOrId->getId();
        }
        return $this->entityManager->getReference($desiredClass, $id);
    }

    /**
     * Retrieve an entity by id.
     *
     * @param string $entityClass Entity class.
     * @param int    $id          Id of the entity to be retrieved
     *
     * @return ?object
     */
    public function getEntityById($entityClass, $id)
    {
        return $this->entityManager->find($entityClass, $id);
    }

    /**
     * Get the row count of a given entity.
     *
     * @param string $entityClass Entity class.
     *
     * @return int
     */
    public function getRowCountForTable($entityClass)
    {
        $dql = 'SELECT COUNT(e) FROM ' . $entityClass . ' e ';
        $query = $this->entityManager->createQuery($dql);
        $count = $query->getSingleScalarResult();
        return $count;
    }
}
