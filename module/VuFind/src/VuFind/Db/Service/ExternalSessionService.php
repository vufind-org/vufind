<?php

/**
 * Database service for external_session table.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2024.
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
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */

namespace VuFind\Db\Service;

use DateTime;
use VuFind\Db\Entity\ExternalSessionEntityInterface;

/**
 * Database service for external_session table.
 *
 * @category VuFind
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
class ExternalSessionService extends AbstractDbService implements
    ExternalSessionServiceInterface,
    Feature\DeleteExpiredInterface
{
    /**
     * Create a new external session entity.
     *
     * @return ExternalSessionEntityInterface
     */
    public function createEntity(): ExternalSessionEntityInterface
    {
        return $this->entityPluginManager->get(ExternalSessionEntityInterface::class);
    }

    /**
     * Add a mapping between local and external session id's; return the newly-created entity.
     *
     * @param string $localSessionId    Local (VuFind) session id
     * @param string $externalSessionId External session id
     *
     * @return ExternalSessionEntityInterface
     */
    public function addSessionMapping(
        string $localSessionId,
        string $externalSessionId
    ): ExternalSessionEntityInterface {
        $this->destroySession($localSessionId);
        $row = $this->createEntity()
            ->setSessionId($localSessionId)
            ->setExternalSessionId($externalSessionId)
            ->setCreated(new DateTime());
        $this->persistEntity($row);
        return $row;
    }

    /**
     * Retrieve objects from the database based on an external session ID
     *
     * @param string $sid External session ID to retrieve
     *
     * @return ExternalSessionEntityInterface[]
     */
    public function getAllByExternalSessionId(string $sid): array
    {
        $dql = 'SELECT es '
            . 'FROM ' . ExternalSessionEntityInterface::class . ' es '
            . 'WHERE es.externalSessionId = :esid ';
        $query = $this->entityManager->createQuery($dql);
        $query->setParameter('esid', $sid);
        $result = $query->getResult();
        return $result;
    }

    /**
     * Destroy data for the given session ID.
     *
     * @param string $sid Session ID to erase
     *
     * @return void
     */
    public function destroySession(string $sid): void
    {
        $dql = 'DELETE FROM ' . ExternalSessionEntityInterface::class . ' es'
            . ' WHERE es.sessionId = :sid';
        $query = $this->entityManager->createQuery($dql);
        $query->setParameter('sid', $sid);
        $query->execute();
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
        $subQueryBuilder->select('es.id')
            ->from(ExternalSessionEntityInterface::class, 'es')
            ->where('es.created < :dateLimit')
            ->setParameter('dateLimit', $dateLimit);
        if ($limit) {
            $subQueryBuilder->setMaxResults($limit);
        }
        $queryBuilder = $this->entityManager->createQueryBuilder();
        $queryBuilder->delete(ExternalSessionEntityInterface::class, 'es')
            ->where('es.id IN (:ids)')
            ->setParameter('ids', $subQueryBuilder->getQuery()->getResult());
        return $queryBuilder->getQuery()->execute();
    }
}
