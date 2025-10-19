<?php

/**
 * Database service for API keys.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2025.
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
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */

namespace VuFind\Db\Service;

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
class ApiKeyService extends AbstractDbService implements ApiKeyServiceInterface
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
     * Delete API key
     *
     * @param ApiKeyEntityInterface $apiKey API key entity
     *
     * @return void
     */
    public function deleteApiKey(ApiKeyEntityInterface $apiKey): void
    {
        $this->deleteEntity($apiKey);
    }
}
