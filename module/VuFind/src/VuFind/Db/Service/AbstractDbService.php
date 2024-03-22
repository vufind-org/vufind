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
use Laminas\Db\RowGateway\AbstractRowGateway;
use VuFind\Db\Entity\EntityInterface;
use VuFind\Db\Entity\PluginManager as EntityPluginManager;

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
     * Constructor
     *
     * @param EntityManager       $entityManager       Doctrine ORM entity manager
     * @param EntityPluginManager $entityPluginManager VuFind entity plugin manager
     */
    public function __construct(
        protected EntityManager $entityManager,
        protected EntityPluginManager $entityPluginManager
    ) {
    }

    /**
     * Resolve an entity class name using the plugin manager.
     *
     * @param string $entity Entity class name or alias
     *
     * @return string
     */
    protected function getEntityClass(string $entity): string
    {
        $entity = $this->entityPluginManager->get($entity);
        return $entity::class;
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
        // Compatibility with legacy \VuFind\Db\Row objects:
        if ($entity instanceof AbstractRowGateway) {
            $entity->save();
            return;
        }
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
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
        $this->entityManager->remove($entity);
        $this->entityManager->flush();
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
        return $this->entityManager->find(
            $this->getEntityClass($entityClass),
            $id
        );
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
        $dql = 'SELECT COUNT(e) FROM ' . $this->getEntityClass($entityClass) . ' e ';
        $query = $this->entityManager->createQuery($dql);
        $count = $query->getSingleScalarResult();
        return $count;
    }
}
