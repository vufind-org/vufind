<?php

/**
 * Database service interface for API keys.
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
 * Database service interface for API keys.
 *
 * @category VuFind
 * @package  Database
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
interface ApiKeyServiceInterface extends DbServiceInterface
{
    /**
     * Create an api_key entity object.
     *
     * @return ApiKeyEntityInterface
     */
    public function createEntity(): ApiKeyEntityInterface;

    /**
     * Get API keys for a user.
     *
     * @param UserEntityInterface $user User
     *
     * @return ApiKeyEntityInterface[]
     */
    public function getApiKeysForUser(UserEntityInterface $user): array;

    /**
     * Get an API key with user and id.
     *
     * @param UserEntityInterface $user User
     * @param int                 $id   API key id
     *
     * @return ?ApiKeyEntityInterface
     */
    public function getByUserAndId(UserEntityInterface $user, int $id): ?ApiKeyEntityInterface;

    /**
     * Retrieve an API key from the database based on token.
     *
     * @param string $token API key token.
     *
     * @return ?ApiKeyEntityInterface
     */
    public function getByToken(string $token): ?ApiKeyEntityInterface;

    /**
     * Delete API key
     *
     * @param ApiKeyEntityInterface $apiKey API key entity
     *
     * @return void
     */
    public function deleteApiKey(ApiKeyEntityInterface $apiKey): void;
}
