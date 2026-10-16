<?php

/**
 * Abstract database service for session related data.
 *
 * PHP version 8
 *
 * Copyright (C) Hebis Verbundzentrale 2026.
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
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */

namespace VuFind\Db\Service;

use Doctrine\ORM\EntityManager;
use Laminas\Session\SessionManager;
use VuFind\Db\Entity\EntityInterface;
use VuFind\Db\Entity\PluginManager as EntityPluginManager;
use VuFind\Db\Entity\SessionEntityInterface;
use VuFind\Db\PersistenceManager;
use VuFind\Session\HandlerInterface;

/**
 * Abstract database service for session related data.
 *
 * @category VuFind
 * @package  Database
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
abstract class AbstractSessionDataService extends AbstractDbService implements SessionDataServiceInterface
{
    /**
     * Get class name of entity managed by the service.
     *
     * @return string
     */
    abstract protected function getEntityClass(): string;

    /**
     * Constructor.
     *
     * @param EntityManager       $entityManager       Doctrine ORM entity manager
     * @param EntityPluginManager $entityPluginManager Database entity plugin manager
     * @param PersistenceManager  $persistenceManager  Entity persistence manager
     */
    public function __construct(
        EntityManager $entityManager,
        EntityPluginManager $entityPluginManager,
        PersistenceManager $persistenceManager,
        protected SessionManager $sessionManager,
        protected HandlerInterface $sessionHandler,
    ) {
        parent::__construct($entityManager, $entityPluginManager, $persistenceManager);
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
        $entity->setSessionId($this->sessionManager->getId());
        $this->persistenceManager->persistEntity($entity);
    }

    /**
     * Destroy data for a given session.
     *
     * @param string $sessionId Session ID.
     *
     * @return void
     */
    public function destroySession(string $sessionId): void
    {
        $parameters = ['sessionId' => $sessionId];
        $dql = 'DELETE FROM ' . $this->getEntityClass() . ' e WHERE e.sessionId = :sessionId';
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);
        $query->execute();
    }

    /**
     * Delete expired records. Allows setting a limit so that rows can be deleted in small batches.
     *
     * @param ?int $limit Maximum number of rows to delete or null for no limit.
     *
     * @return int Number of rows deleted
     */
    public function deleteRowsWithoutSession(int $sleepTime = 1, ?int $limit = null, array &$orphanSessionsCache = []): int
    {
        $dql = 'SELECT DISTINCT e.sessionId '
            . 'FROM ' . $this->getEntityClass() . ' e';

        $sessionIdsQuery = $this->entityManager->createQuery($dql);

        $sessionIds = array_map(fn ($row) => $row['sessionId'], $sessionIdsQuery->getResult());

        foreach ($sessionIds as $sessionId) {
            if (!key_exists($sessionId, $orphanSessionsCache)) {
                $orphanSessionsCache[$sessionId] = empty($this->sessionHandler->read($sessionId));
            }
        }

        $sessionsIdsToDelete = array_filter($sessionIds, fn ($sessionId) => $orphanSessionsCache[$sessionId]);

        $total = 0;
        foreach (array_chunk($sessionsIdsToDelete, 10) as $sessionIdsChunk) {
            do {
                $subQueryBuilder = $this->entityManager->createQueryBuilder();
                $subQueryBuilder->select('e.id')
                    ->from($this->getEntityClass(), 'e')
                    ->where('e.sessionId IN (:sessionIds)')
                    ->setParameter('sessionIds', $sessionIdsChunk);
                if ($limit) {
                    $subQueryBuilder->setMaxResults($limit);
                }
                $queryBuilder = $this->entityManager->createQueryBuilder();
                $queryBuilder->delete($this->getEntityClass(), 'e')
                    ->where('e.id IN (:rows)')
                    ->setParameter('rows', $subQueryBuilder->getQuery()->getResult());
                $count = $queryBuilder->getQuery()->execute();
                if ($count > 0) {
                    $total += $count;
                    // Be nice to others and wait between batches
                    usleep($sleepTime * 1000);
                }
            } while ($count > 0);
        }

        return $total;
    }
}
