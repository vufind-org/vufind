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

use VuFind\Config\Config;
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
     * Constructor.
     *
     * @param AccessToken $accessTokenTable Access token table
     * @param Config      $config           Main config
     */
    public function __construct(
        AccessToken $accessTokenTable,
        protected Config $config
    ) {
        parent::__construct($accessTokenTable);
    }

    /**
     * Generate a new api key token
     *
     * @param UserEntityInterface $user User to create salt for
     *
     * @return string
     */
    protected function createRandomToken(UserEntityInterface $user): string
    {
        $salt = $this->config->API_Keys->token_salt ?? null;
        if (!$salt) {
            throw new \Exception('APIKeyService: Salt missing');
        }
        $valuesForToken = [
            $user->getEmailVerified(),
            $user->getFirstname(),
            $user->getLastname(),
            strtotime('now'),
            $salt,
        ];
        return hash('sha256', implode('|', $valuesForToken));
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
        $token = $this->createRandomToken($user);
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
