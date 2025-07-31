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

namespace VuFind\ApiKey;

use VuFind\Config\Config;
use VuFind\Db\Entity\AccessTokenEntityInterface;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Service\AccessTokenService;
use VuFind\Db\Service\DbServiceAwareInterface;
use VuFind\Db\Service\DbServiceAwareTrait;

/**
 * Database service for api keys.
 *
 * @category VuFind
 * @package  Database
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
class ApiKeyService implements DbServiceAwareInterface
{
    use DbServiceAwareTrait;

    /**
     * Constructor.
     *
     * @param Config $config Main config
     */
    public function __construct(
        protected Config $config
    ) {
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
            $user->getEmailVerified()->format('Y-m-d'),
            $user->getFirstname(),
            $user->getLastname(),
            (string)strtotime('now'),
            $salt,
        ];
        return hash('sha256', implode('|', $valuesForToken));
    }

    /**
     * Retrieve an Api Key for a user. Return associative array containing token, revoked or empty
     * array if not found.
     *
     * @param UserEntityInterface $user User
     *
     * @return ?AccessTokenEntityInterface
     */
    public function getApiKeyForUser(UserEntityInterface $user): ?AccessTokenEntityInterface
    {
        return $this->getDbService(AccessTokenService::class)->getByIdAndType(
            (string)$user->getId(),
            AccessTokenService::TYPE_API_KEY,
            false
        );
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
        $token = $this->getDbService(AccessTokenService::class)->getByDataAndType(
            $token,
            AccessTokenService::TYPE_API_KEY
        );
        return $token && !$token->isRevoked();
    }

    /**
     * Validate user can use api keys. It is expected that the user has a verified email address.
     *
     * @param UserEntityInterface $user User
     *
     * @return bool
     */
    public function isUserValid(UserEntityInterface $user): bool
    {
        return $user->getEmailVerified() !== null;
    }

    /**
     * Generate an Api Key for a user.
     *
     * @param UserEntityInterface $user User
     *
     * @return string|false API key token on success, false on failure
     */
    public function generateApiKeyForUser(UserEntityInterface $user): string|false
    {
        // Check if the user has an existing token and the token has not been revoked.
        $token = $this->getDbService(AccessTokenService::class)->getByIdAndType(
            (string)$user->getId(),
            AccessTokenService::TYPE_API_KEY
        );
        if (!$token || $token->isRevoked()) {
            return false;
        }
        $tokenHash = $this->createRandomToken($user);
        $token->setData($tokenHash);
        $token->setUser($user);
        $this->getDbService(AccessTokenService::class)->persistEntity($token);
        return $tokenHash;
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
        $token = $this->getDbService(AccessTokenService::class)->getByIdAndType(
            (string)$user->getId(),
            AccessTokenService::TYPE_API_KEY
        );
        if ($token && !$token->isRevoked()) {
            $this->getDbService(AccessTokenService::class)->deleteEntity($token);
            return true;
        }
        return false;
    }
}
