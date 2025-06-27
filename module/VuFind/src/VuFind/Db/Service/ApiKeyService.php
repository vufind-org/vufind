<?php

/**
 * Database service for api keys.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2025.
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

use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Table\AccessToken;

/**
 * Database service for api keys.
 *
 * @category VuFind
 * @package  Database
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
class ApiKeyService extends AccessTokenService implements ApiKeyServiceInterface
{
    /**
     * Generate a random token using random_bytes and bin2hex
     *
     * @return string
     */
    protected function createRandomToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Retrieve an Api Key for a user. Return associative array containing data for the key
     *
     * @param UserEntityInterface $user User
     *
     * @return array
     */
    public function getApiKeyForUser(UserEntityInterface $user): array
    {
        $token = $this->getByIdAndType($user->getId(), AccessToken::TYPE_API_KEY, false);
        if (!$token) {
            return [];
        }
        return [
            'token' => json_decode($token->__get('data')),
            'revoked' => $token->isRevoked(),
        ];
    }

    /**
     * Check if Api Key is valid to be used.
     *
     * @param string $token Token to search for.
     *
     * @return bool If exists and the access token has not been revoked.
     */
    public function isTokenValid(string $token): bool
    {
        $row = $this->accessTokenTable->select(['type' => AccessToken::TYPE_API_KEY, 'data' => $token])->current();
        return $row && !$row->isRevoked();
    }

    /**
     * Create an Api Key for a user.
     *
     * @param UserEntityInterface $user User
     *
     * @return string|false API key token on success, false on failure
     */
    public function createApiKeyForUser(UserEntityInterface $user): string|false
    {
        // Check if the user has an existing token and the token has not been revoked.
        $row = $this->getByIdAndType($user->getId(), AccessToken::TYPE_API_KEY, false);
        if ($row?->isRevoked()) {
            return false;
        }
        $token = $this->createRandomToken();
        $this->accessTokenTable->storeData($user->getId(), AccessToken::TYPE_API_KEY, $token);
        return $token;
    }

    /**
     * Delete an Api Key for a user
     *
     * @param UserEntityInterface $user User
     *
     * @return bool
     */
    public function deleteApiKeyForUser(UserEntityInterface $user): bool
    {
        $row = $this->getByIdAndType($user->getId(), AccessToken::TYPE_API_KEY, false);
        if ($row && !$row->isRevoked()) {
            $row->delete();
            return true;
        }
        return false;
    }
}
