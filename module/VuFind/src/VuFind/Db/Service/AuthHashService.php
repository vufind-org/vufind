<?php

/**
 * Database service for auth_hash table.
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
use VuFind\Db\Entity\AuthHashEntityInterface;

/**
 * Database service for auth_hash table.
 *
 * @category VuFind
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
class AuthHashService extends AbstractDbService implements
    AuthHashServiceInterface,
    Feature\DeleteExpiredInterface
{
    /**
     * Create an auth_hash entity object.
     *
     * @return AuthHashEntityInterface
     */
    public function createEntity(): AuthHashEntityInterface
    {
        return $this->entityPluginManager->get(AuthHashEntityInterface::class);
    }

    /**
     * Delete an auth_hash entity object.
     *
     * @param AuthHashEntityInterface|int $authHashOrId Object or ID value representing auth_hash to delete
     *
     * @return void
     */
    public function deleteAuthHash(AuthHashEntityInterface|int $authHashOrId): void
    {
        $dql = 'DELETE FROM ' . AuthHashEntityInterface::class . ' ah '
            . 'WHERE ah.id = :id';
        $query = $this->entityManager->createQuery($dql);
        $authHashId = $authHashOrId instanceof AuthHashEntityInterface ? $authHashOrId->getId() : $authHashOrId;
        $query->setParameter('id', $authHashId);
        $query->execute();
    }

    /**
     * Retrieve an object from the database based on hash and type; possibly create a new
     * row if no existing match is found.
     *
     * @param string $hash   Hash
     * @param string $type   Hash type
     * @param bool   $create Should we create rows that don't already exist?
     *
     * @return ?AuthHashEntityInterface
     */
    public function getByHashAndType(string $hash, string $type, bool $create = true): ?AuthHashEntityInterface
    {
        $dql = 'SELECT ah '
            . 'FROM ' . AuthHashEntityInterface::class . ' ah '
            . 'WHERE ah.hash = :hash '
            . 'AND ah.type = :type';
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters(compact('hash', 'type'));
        $result = $query->getOneOrNullResult();
        if ($result === null && $create) {
            $result = $this->createEntity()
                ->setHash($hash)
                ->setHashType($type)
                ->setCreated(new DateTime());
            $this->persistEntity($result);
        }

        return $result;
    }

    /**
     * Retrieve last object from the database based on session id.
     *
     * @param string $sessionId Session ID
     *
     * @return ?AuthHashEntityInterface
     */
    public function getLatestBySessionId(string $sessionId): ?AuthHashEntityInterface
    {
        $dql = 'SELECT ah '
            . 'FROM ' . AuthHashEntityInterface::class . ' ah '
            . 'WHERE ah.sessionId = :sessionId '
            . 'ORDER BY ah.created DESC';
        $query = $this->entityManager->createQuery($dql);
        $query->setParameter('sessionId', $sessionId);
        $result = $query->getOneOrNullResult();
        return $result;
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
        $subQueryBuilder->select('ah.id')
            ->from(AuthHashEntityInterface::class, 'ah')
            ->where('ah.created < :dateLimit')
            ->setParameter('dateLimit', $dateLimit->format('Y-m-d H:i:s'));
        if ($limit) {
            $subQueryBuilder->setMaxResults($limit);
        }
        $queryBuilder = $this->entityManager->createQueryBuilder();
        $queryBuilder->delete(AuthHashEntityInterface::class, 'ah')
            ->where('ah.id IN (:hashes)')
            ->setParameter('hashes', $subQueryBuilder->getQuery()->getResult());
        return $queryBuilder->getQuery()->execute();
    }
}
