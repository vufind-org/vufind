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
use Laminas\Log\LoggerAwareInterface;
use VuFind\Db\Entity\AccessToken;
use VuFind\Db\Entity\AccessTokenEntityInterface;
use VuFind\Log\LoggerAwareTrait;

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
    Feature\DeleteExpiredInterface,
    LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * Create an access_token entity object.
     *
     * @return AccessTokenEntityInterface
     */
    public function createEntity(): AccessTokenEntityInterface
    {
        $class = $this->getEntityClass(AccessToken::class);
        return new $class();
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

        $dql = 'SELECT * '
            . 'FROM ' . $this->getEntityClass(AccessToken::class)
            . 'WHERE id = :id '
            . 'AND type = :type';
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters(['id' => $id, 'type' => $type]);
        $result = $query->getResult();
        $result = $result ?: $this->createEntity()
                          ->setId($id)
                          ->setType($type)
                          ->setCreated(new \DateTime());

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
        $dql = 'UPDATE ' . $this->getEntityClass(AccessToken::class) . ' at '
                . 'SET at.data = :nonce WHERE at.user = :user';
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters(['nonce' => $nonce, 'user' => $userId]);
        $query->execute();
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
        $dql = 'SELECT data '
            . 'FROM ' . $this->getEntityClass(AccessToken::class) . ' at '
            . 'WHERE at.user_id = :userId';
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters(['userId' => $userId]);
        $result = $query->getResult();
        $data = json_decode($result, true);
        return $data['nonce'] ?? null;
    }

    /**[Semantical Error] line 0, col 69 near 'userId = :us': Error: Class VuFind\Db\Entity\AccessToken has no field or association named userIdleted in small batches.
     *
     * @param DateTime $dateLimit Date threshold of an "expired" record.
     * @param ?int     $limit     Maximum number of rows to delete or null for no limit.
     *
     * @return int Number of rows deleted
     */
    public function deleteExpired(DateTime $dateLimit, ?int $limit = null): int
    {
        $subQueryBuilder = $this->entityManager->createQueryBuilder();
        $subQueryBuilder->select('at.id')
            ->from($this->getEntityClass(AccessTokenEnTityInterface::class), 'at')
            ->where('at.created < :latestCreated')
            ->setParameter('lastestCreated', $dateLimit->getTimestamp());
        if ($limit) {
            $subQueryBuilder->setMaxResults($limit);
        }
        $queryBuilder = $this->entityManager->createQueryBuilder();
        $queryBuilder->delete($this->getEntityClass(AccessTokenEnTityInterface::class), 'at')
            ->where('at.id IN (:ids)')
            ->setParameter('ids', $subQueryBuilder->getQuery()->getResult());
        return $queryBuilder->getQuery()->execute();
    }
}
