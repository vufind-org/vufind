<?php

/**
 * Database service for access tokens.
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Database
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */

namespace VuFind\Db\Service;

use DateTime;
use VuFind\Db\Entity\AccessTokenEntityInterface;
use VuFind\Db\Entity\User;

/**
 * Database service for access tokens.
 *
 * @category VuFind
 * @package  Database
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
class AccessTokenService extends AbstractDbService implements
    AccessTokenServiceInterface,
    Feature\DeleteExpiredInterface
{
    /**
     * Create an access_token entity object.
     *
     * @return AccessTokenEntityInterface
     */
    public function createEntity(): AccessTokenEntityInterface
    {
        return $this->entityPluginManager->get(AccessTokenEntityInterface::class);
    }

    /**
     * Retrieve an object from the database based on id and type; create a new
     * row if no existing match is found.
     *
     * @param string $id     Token ID
     * @param string $type   Token type
     * @param bool   $create Should we create rows that don't already exist?
     *
     * @return ?AccessTokenEntityInterface
     */
    public function getByIdAndType(
        string $id,
        string $type,
        bool $create = true
    ): ?AccessTokenEntityInterface {
        $dql = 'SELECT at '
            . 'FROM ' . AccessTokenEntityInterface::class . ' at '
            . 'WHERE at.id = :id '
            . 'AND at.type = :type';
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters(compact('id', 'type'));
        $result = $query->getOneOrNullResult();
        if ($result === null && $create) {
            $result = $this->createEntity()
                ->setId($id)
                ->setType($type)
                ->setCreated(new DateTime());
            $this->persistEntity($result);
        }

        return $result;
    }

    /**
     * Add or replace an OpenID nonce for a user
     *
     * @param int     $userId User ID
     * @param ?string $nonce  Nonce
     *
     * @return void
     */
    public function storeNonce(int $userId, ?string $nonce): void
    {
        $type = 'openid_nonce';
        $token = $this->getByIdAndType((string)$userId, $type);
        $token->setUser($this->entityManager->getReference(User::class, $userId));
        $token->setData($nonce);
        $this->persistEntity($token);
    }

    /**
     * Retrieve an OpenID nonce for a user
     *
     * @param int $userId User ID
     *
     * @return ?string
     */
    public function getNonce(int $userId): ?string
    {
        $type = 'openid_nonce';
        $token = $this->getByIdAndType((string)$userId, $type, false);
        return $token?->getData();
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
        $subQueryBuilder->select('CONCAT(a.id, a.type)')
            ->from(AccessTokenEntityInterface::class, 'a')
            ->where('a.created < :latestCreated')
            ->setParameter('latestCreated', $dateLimit->format('Y-m-d H:i:s'));
        if ($limit) {
            $subQueryBuilder->setMaxResults($limit);
        }
        $queryBuilder = $this->entityManager->createQueryBuilder();
        $queryBuilder->delete(AccessTokenEntityInterface::class, 'a')
            ->where('concat(a.id, a.type) IN (:ids)')
            ->setParameter('ids', $subQueryBuilder->getQuery()->getResult());
        return $queryBuilder->getQuery()->execute();
    }
}
