<?php

/**
 * Database service for search.
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

use DateTime;
use Exception;
use VuFind\Db\Entity\SearchEntityInterface;
use VuFind\Db\Entity\UserEntityInterface;

/**
 * Database service for search.
 *
 * @category VuFind
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
class SearchService extends AbstractDbService implements
    SearchServiceInterface,
    Feature\DeleteExpiredInterface
{
    /**
     * Create a search entity.
     *
     * @return SearchEntityInterface
     */
    public function createEntity(): SearchEntityInterface
    {
        return $this->entityPluginManager->get(SearchEntityInterface::class);
    }

    /**
     * Create a search entity containing the specified checksum, persist it to the database,
     * and return a fully populated object. Throw an exception if something goes wrong during
     * the process.
     *
     * @param int $checksum Checksum
     *
     * @return SearchEntityInterface
     * @throws Exception
     */
    public function createAndPersistEntityWithChecksum(int $checksum): SearchEntityInterface
    {
        $entity = $this->createEntity();
        $entity->setCreated(new \DateTime());
        $entity->setChecksum($checksum);

        $this->persistEntity($entity);
        $this->entityManager->flush();

        $id = $entity->getId();
        $retrieved = $this->getSearchById($id);

        if (!$retrieved) {
            throw new \Exception('Cannot find id ' . $id);
        }

        return $retrieved;
    }

    /**
     * Destroy unsaved searches belonging to the specified session/user.
     *
     * @param string                       $sessionId Session ID of current user.
     * @param UserEntityInterface|int|null $userOrId  User entity or ID of current user (optional).
     *
     * @return void
     */
    public function destroySession(string $sessionId, UserEntityInterface|int|null $userOrId = null): void
    {
        $userId = $userOrId instanceof UserEntityInterface ? $userOrId->getId() : $userOrId;
        $parameters = ['saved' => false, 'sessionId' => $sessionId];
        $dql = 'DELETE FROM ' . SearchEntityInterface::class . ' s '
            . 'WHERE s.saved = :saved AND (s.sessionId = :sessionId';
        if ($userId !== null) {
            $dql .= ' OR s.user = :userId';
            $parameters['userId'] = $userId;
        }
        $dql .= ')';
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);
        $query->execute();
    }

    /**
     * Get a SearchEntityInterface object by ID.
     *
     * @param int $id Search identifier
     *
     * @return ?SearchEntityInterface
     */
    public function getSearchById(int $id): ?SearchEntityInterface
    {
        return $this->entityManager->find(SearchEntityInterface::class, $id);
    }

    /**
     * Get a SearchEntityInterface object by ID and owner.
     *
     * @param int                          $id        Search identifier
     * @param string                       $sessionId Session ID of current user.
     * @param UserEntityInterface|int|null $userOrId  User entity or ID of current user (optional).
     *
     * @return ?SearchEntityInterface
     */
    public function getSearchByIdAndOwner(
        int $id,
        string $sessionId,
        UserEntityInterface|int|null $userOrId
    ): ?SearchEntityInterface {
        $userId = $userOrId instanceof UserEntityInterface ? $userOrId->getId() : $userOrId;
        $entityClass = SearchEntityInterface::class;

        $dql = 'SELECT s FROM ' . $entityClass . ' s WHERE s.id = :id AND (s.sessionId = :sessionId';
        $parameters = [
            'id' => $id,
            'sessionId' => $sessionId,
        ];

        if ($userId !== null) {
            $dql .= ' OR s.user = :userId';
            $parameters['userId'] = $userId;
        }

        $dql .= ')';

        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);

        return $query->getOneOrNullResult();
    }

    /**
     * Get an array of rows for the specified user.
     *
     * @param ?string                      $sessionId Session ID of current user or null to ignore searches in session.
     * @param UserEntityInterface|int|null $userOrId  User entity or ID of current user (optional).
     *
     * @return SearchEntityInterface[]
     */
    public function getSearches(?string $sessionId, UserEntityInterface|int|null $userOrId = null): array
    {
        $userId = $userOrId instanceof UserEntityInterface ? $userOrId->getId() : $userOrId;

        if ($sessionId === null && $userId === null) {
            return [];
        }

        $entityClass = SearchEntityInterface::class;
        $dql = 'SELECT s FROM ' . $entityClass . ' s';
        $conditions = [];
        $params = [];

        if ($sessionId !== null) {
            $conditions[] = '(s.sessionId = :sessionId AND s.saved = :saved)';
            $params['sessionId'] = $sessionId;
            $params['saved'] = false;
        }

        if ($userId !== null) {
            $conditions[] = 's.user = :userId';
            $params['userId'] = $userId;
        }

        if ($conditions) {
            $dql .= ' WHERE ' . implode(' OR ', $conditions);
        }

        $dql .= ' ORDER BY s.created ASC';

        return $this->entityManager
            ->createQuery($dql)
            ->setParameters($params)
            ->getResult();
    }

    /**
     * Get scheduled searches.
     *
     * @return SearchEntityInterface[]
     */
    public function getScheduledSearches(): array
    {
        $entityClass = SearchEntityInterface::class;
        $dql = 'SELECT s FROM ' . $entityClass
            . ' s WHERE s.saved = :saved'
            . ' AND s.notificationFrequency > 0'
            . ' ORDER BY s.user ASC';

        $query = $this->entityManager->createQuery($dql);
        $query->setParameter('saved', true);
        return $query->getResult();
    }

    /**
     * Retrieve all searches matching the specified checksum and belonging to the user specified by session or user
     * entity/ID.
     *
     * @param int                          $checksum  Checksum to match
     * @param string                       $sessionId Current session ID
     * @param UserEntityInterface|int|null $userOrId  Entity or ID representing current user (optional).
     *
     * @return SearchEntityInterface[]
     * @throws Exception
     */
    public function getSearchesByChecksumAndOwner(
        int $checksum,
        string $sessionId,
        UserEntityInterface|int|null $userOrId = null
    ): array {
        $userId = $userOrId instanceof UserEntityInterface ? $userOrId->getId() : $userOrId;
        $dql = 'SELECT s FROM ' . SearchEntityInterface::class . ' s '
            . 'WHERE s.checksum = :checksum AND ';
        $extraClauses = ['(s.sessionId = :sessionId AND s.saved = :saved)'];
        $params = ['checksum' => $checksum, 'saved' => false, 'sessionId' => $sessionId];
        if ($userId !== null) {
            $extraClauses[] = 's.user = :userId';
            $params['userId'] = $userId;
        }
        $dql .= '(' . implode(' OR ', $extraClauses) . ')';
        return $this->entityManager
            ->createQuery($dql)
            ->setParameters($params)
            ->getResult();
    }

    /**
     * Get saved searches with missing checksums (used for cleaning up legacy data).
     *
     * @return SearchEntityInterface[]
     */
    public function getSavedSearchesWithMissingChecksums(): array
    {
        $dql = 'SELECT s FROM ' . SearchEntityInterface::class . ' s '
            . 'WHERE s.checksum IS NULL AND s.saved = :saved';

        $query = $this->entityManager->createQuery($dql);
        $query->setParameter('saved', true);
        return $query->getResult();
    }

    /**
     * Delete a search entity.
     *
     * @param SearchEntityInterface|int $searchOrId Search entity object or ID to delete
     *
     * @return void
     */
    public function deleteSearch(SearchEntityInterface|int $searchOrId): void
    {
        $searchId = $searchOrId instanceof SearchEntityInterface ? $searchOrId->getId() : $searchOrId;
        $dql = 'DELETE FROM ' . SearchEntityInterface::class . ' s'
            . ' WHERE s.id = :searchId';
        $query = $this->entityManager->createQuery($dql);
        $query->setParameter('searchId', $searchId);
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
        $subQueryBuilder->select('s.id')
            ->from(SearchEntityInterface::class, 's')
            ->where('s.created < :dateLimit AND s.saved = :saved')
            ->setParameter('dateLimit', $dateLimit)
            ->setParameter('saved', false);

        if ($limit) {
            $subQueryBuilder->setMaxResults($limit);
        }
        $queryBuilder = $this->entityManager->createQueryBuilder();
        $queryBuilder->delete(SearchEntityInterface::class, 's')
            ->where('s.id IN (:searches)')
            ->setParameter('searches', $subQueryBuilder->getQuery()->getResult());

        return $queryBuilder->getQuery()->execute();
    }
}
