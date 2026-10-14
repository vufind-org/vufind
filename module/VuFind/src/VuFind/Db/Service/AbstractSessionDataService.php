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
    public function deleteExpired(?int $limit = null): int
    {
        $dql = 'SELECT e.id '
            . 'FROM ' . $this->getEntityClass() . ' e '
            . 'LEFT JOIN ' . SessionEntityInterface::class . ' s '
            . 'WITH e.sessionId = s.sessionId '
            . 'WHERE s.sessionId IS NULL';

        $subQueryBuilder = $this->entityManager->createQuery($dql);
        if ($limit) {
            $subQueryBuilder->setMaxResults($limit);
        }

        $queryBuilder = $this->entityManager->createQueryBuilder();
        $queryBuilder->delete($this->getEntityClass(), 'e')
            ->where('e.id IN (:rows)')
            ->setParameter('rows', $subQueryBuilder->getResult());

        return $queryBuilder->getQuery()->execute();
    }
}
