<?php

/**
 * Database service for Finna cache.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2024.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */

namespace Finna\Db\Service;

use DateTime;
use Finna\Db\Entity\FinnaCacheEntityInterface;
use VuFind\Db\Service\AbstractDbService;
use VuFind\Db\Service\Feature\DeleteExpiredInterface;

/**
 * Database service for Finna cache.
 *
 * @category VuFind
 * @package  Database
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
class FinnaCacheService extends AbstractDbService implements
    FinnaCacheServiceInterface,
    DeleteExpiredInterface
{
    /**
     * Create a FinnaCache entity object.
     *
     * @return FinnaCacheEntityInterface
     */
    public function createEntity(): FinnaCacheEntityInterface
    {
        return $this->entityPluginManager->get(FinnaCacheEntityInterface::class);
    }

    /**
     * Delete an entity.
     *
     * @param FinnaCacheEntityInterface $entity Entity
     *
     * @return void
     */
    public function deleteCacheEntry(FinnaCacheEntityInterface $entity): void
    {
        if ($id = $entity->getId()) {
            $this->persistenceManager->deleteEntity($entity);
        }
    }

    /**
     * Get cache item from database by resource id.
     *
     * @param string $resourceId Resource id
     *
     * @return ?FinnaCacheEntityInterface
     */
    public function getByResourceId(string $resourceId): ?FinnaCacheEntityInterface
    {
        return $this->entityManager->getRepository(FinnaCacheEntityInterface::class)
            ->findOneBy(compact('resourceId'));

        $dql = 'SELECT c ' . FinnaCacheEntityInterface::class . ' c WHERE resourceId = :resourceId';
        $query = $this->entityManager->createQuery($dql);
        $query->setParameter('resourceId', $id);
        return $query->getOneOrNullResult();
    }

    /**
     * Delete expired records. Allows setting a limit so that rows can be deleted in small batches.
     *
     * @param DateTime $dateLimit Date threshold of an "expired" record.
     * @param ?int     $limit     Maximum number of rows to delete or null for no limit.
     *
     * @return int Number of rows deleted
     */
    public function deleteExpired(DateTime $dateLimit, ?int $limit = null): int
    {
        $subQueryBuilder = $this->entityManager->createQueryBuilder();
        $subQueryBuilder->select('c.id')
            ->from(FinnaCacheEntityInterface::class, 'c')
            ->where('c.created < :latestDate')
            ->setParameter('latestDate', $dateLimit->format(VUFIND_DATABASE_DATETIME_FORMAT));
        if ($limit) {
            $subQueryBuilder->setMaxResults($limit);
        }
        $queryBuilder = $this->entityManager->createQueryBuilder();
        $queryBuilder->delete(FinnaCacheEntityInterface::class, 'fc')
            ->where('fc.id IN (:ids)')
            ->setParameter('ids', $subQueryBuilder->getQuery()->getResult());
        return $queryBuilder->getQuery()->execute();
    }
}
