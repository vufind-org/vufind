<?php

/**
 * Service for managing API keys
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
 * @package  Service
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */

namespace VuFind\ApiKey;

use DateTime;
use VuFind\Db\Entity\AccessTokenEntityInterface;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Service\AccessTokenService;

use function count;

/**
 * Service for managing API keys
 *
 * @category VuFind
 * @package  Service
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
class ApiKeyService
{
    /**
     * Limit for how many API keys can a user have. Default is 10.
     *
     * @var int
     */
    protected int $tokenLimitPerUser;

    /**
     * Constructor.
     *
     * @param AccessTokenService $accessTokenService Access token service
     * @param array              $apiKeySettings     Section API_Keys from main configuration.
     */
    public function __construct(
        protected AccessTokenService $accessTokenService,
        protected array $apiKeySettings
    ) {
        $this->tokenLimitPerUser = $apiKeySettings['token_limit'] ?? 5;
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
        $salt = $this->apiKeySettings['token_salt'] ?? null;
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
     * Retrieve an API key for a user. Return associative array containing token, revoked or empty
     * array if not found.
     *
     * @param UserEntityInterface $user User
     *
     * @return AccessTokenEntityInterface[]
     */
    public function getApiKeysForUser(UserEntityInterface $user): array
    {
        return $this->accessTokenService->getTokensForUser(
            $user,
            AccessTokenService::TYPE_API_KEY
        );
    }

    /**
     * Check if API key is valid to be used.
     *
     * @param string $token Token to search for.
     *
     * @return bool If exists and the access token has not been revoked.
     */
    public function isTokenValid(string $token): bool
    {
        $token = $this->accessTokenService->getByDataAndType(
            $token,
            AccessTokenService::TYPE_API_KEY
        );
        return $token && !$token->isRevoked();
    }

    /**
     * Generate an API key for a user.
     *
     * @param UserEntityInterface $user  User
     * @param string              $title Title for the API key
     *
     * @return string|false API key token on success, false on failure
     */
    public function generateApiKeyForUser(UserEntityInterface $user, string $title): string|false
    {
        // Check if the user has any existing tokens and the tokens have not been revoked?
        $tokens = $this->accessTokenService->getTokensForUser(
            $user,
            AccessTokenService::TYPE_API_KEY
        );
        if ($this->apiKeysBlocked($tokens)) {
            return false;
        }
        // Generate unique id from date and users id.
        $date = new DateTime();
        $id = hash('sha256', $date->format('Y-m-d H:i:s') . '||' . $user->getId() . '||' . $title);
        $newToken = $this->accessTokenService->createEntity();
        $newToken->setId($id);
        $tokenHash = $this->createRandomToken($user);
        $newToken->setData($tokenHash)
            ->setUser($user)
            ->setExpires(false)
            ->setType(AccessTokenService::TYPE_API_KEY)
            ->setCreated($date)
            ->setTitle($title);
        $this->accessTokenService->persistEntity($newToken);
        return $tokenHash;
    }

    /**
     * Can the user generate more API keys
     *
     * @param AccessTokenEntityInterface[] $tokens Users tokens
     *
     * @return bool
     */
    public function apiKeysBlocked(array $tokens): bool
    {
        foreach ($tokens as $token) {
            if ($token->isRevoked()) {
                return true;
            }
        }
        return count($tokens) >= $this->tokenLimitPerUser;
    }

    /**
     * Delete an API key for a user
     *
     * @param UserEntityInterface $user User
     * @param string              $id   API key id
     *
     * @return bool
     */
    public function deleteApiKeyForUser(UserEntityInterface $user, string $id): bool
    {
        $token = $this->accessTokenService->getByUserIdAndType(
            $user,
            (string)$id,
            AccessTokenService::TYPE_API_KEY
        );
        if ($token && !$token->isRevoked()) {
            $this->accessTokenService->deleteEntity($token);
            return true;
        }
        return false;
    }
}
