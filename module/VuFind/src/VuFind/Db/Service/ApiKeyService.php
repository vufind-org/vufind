<?php

/**
 * Database service for API keys.
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
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */

namespace VuFind\Db\Service;

use DateTime;
use VuFind\Db\Entity\ApiKeyEntityInterface;
use VuFind\Db\Entity\UserEntityInterface;

/**
 * Database service for API keys.
 *
 * @category VuFind
 * @package  Database
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
class ApiKeyService extends AbstractDbService implements
    ApiKeyServiceInterface,
    Feature\DeleteExpiredInterface
{
    /**
     * Create an api_key entity object.
     *
     * @return ApiKeyEntityInterface
     */
    public function createEntity(): ApiKeyEntityInterface
    {
        return $this->entityPluginManager->get(ApiKeyEntityInterface::class);
    }

    /**
     * Get API keys for a user.
     *
     * @param UserEntityInterface $user User
     *
     * @return ApiKeyEntityInterface[]
     */
    public function getApiKeysForUser(UserEntityInterface $user): array
    {
        $dql = 'SELECT ak '
            . 'FROM ' . ApiKeyEntityInterface::class . ' ak '
            . 'WHERE ak.user = :user';
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters(compact('user'));
        return $query->getResult();
    }

    /**
     * Get an API key with user and id.
     *
     * @param UserEntityInterface $user User
     * @param int                 $id   API key id
     *
     * @return ?ApiKeyEntityInterface
     */
    public function getByUserAndId(
        UserEntityInterface $user,
        int $id
    ): ?ApiKeyEntityInterface {
        $dql = 'SELECT ak '
            . 'FROM ' . ApiKeyEntityInterface::class . ' ak '
            . 'WHERE ak.id = :id AND ak.user = :user';
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters(compact('user', 'id'));
        return $query->getOneOrNullResult();
    }

    /**
     * Retrieve an API key from the database based on token.
     *
     * @param string $token API key token.
     *
     * @return ?ApiKeyEntityInterface
     */
    public function getByToken(string $token): ?ApiKeyEntityInterface
    {
        $dql = 'SELECT ak '
            . 'FROM ' . ApiKeyEntityInterface::class . ' ak '
            . 'WHERE ak.token = :token';
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters(['token' => $token]);
        return $query->getOneOrNullResult();
    }

    /**
     * Delete expired API keys. Allows setting a limit so that rows can be deleted in small batches.
     * API keys only "expire" if they are not used in a certain amount of time.
     *
     * @param DateTime $dateLimit Date threshold of an "expired" record.
     * @param ?int     $limit     Maximum number of rows to delete or null for no limit.
     *
     * @return int Number of rows deleted
     */
    public function deleteExpired(DateTime $dateLimit, ?int $limit = null): int
    {
        $dql = 'SELECT ak '
            . 'FROM ' . ApiKeyEntityInterface::class . ' ak '
            . 'WHERE ak.lastUsed < :dateLimit';
        $query = $this->entityManager->createQuery($dql);
        if ($limit) {
            $query->setMaxResults($limit);
        }
        $query->setParameters(compact('dateLimit'));
        $result = $query->getResult();
        if ($result) {
            $queryBuilder = $this->entityManager->createQueryBuilder();
            $queryBuilder->delete(ApiKeyEntityInterface::class, 'ak')
                ->where('ak IN (:apiKeys)')
                ->setParameter('apiKeys', $result);
            return $queryBuilder->getQuery()->execute();
        }
        return 0;
    }
}
