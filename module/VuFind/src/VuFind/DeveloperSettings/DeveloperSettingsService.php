<?php

/**
 * Service for managing API keys
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
 * @package  Service
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */

namespace VuFind\DeveloperSettings;

use DateTime;
use VuFind\Db\Entity\ApiKeyEntityInterface;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Service\ApiKeyService;
use VuFindApi\Controller\ApiInterface;

use function count;
use function in_array;

/**
 * Service for managing API keys
 *
 * @category VuFind
 * @package  Service
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
class DeveloperSettingsService
{
    /**
     * Limit for how many API keys can a user have. Default is 10.
     *
     * @var int
     */
    protected int $keyLimitPerUser;

    /**
     * Update interval to update last_used values in the database.
     *
     * @var int
     */
    protected int $updateInterval = 5;

    /**
     * Constructor.
     *
     * @param ApiKeyService $apiKeyService  API key database service
     * @param array         $apiKeySettings Section API_Keys from main configuration.
     */
    public function __construct(
        protected ApiKeyService $apiKeyService,
        protected array $apiKeySettings
    ) {
        $this->keyLimitPerUser = $apiKeySettings['key_limit'] ?? 5;
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
            throw new \Exception('DeveloperSettingsService: Salt missing');
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
     * Retrieve API keys for user.
     *
     * @param UserEntityInterface $user User
     *
     * @return ApiKeyEntityInterface[]
     */
    public function getApiKeysForUser(UserEntityInterface $user): array
    {
        return $this->apiKeyService->getApiKeysForUser($user);
    }

    /**
     * Get API key using token.
     *
     * @param string $token Token to search for.
     *
     * @return ?ApiKeyEntityInterface
     */
    public function getApiKeyByToken(string $token): ?ApiKeyEntityInterface
    {
        $apiKey = $this->apiKeyService->getByToken($token);
        if ($apiKey) {
            $this->updateApiKeyTimeStamp($apiKey);
        }
        return $apiKey;
    }

    /**
     * Generate an API key for a user.
     *
     * @param UserEntityInterface $user  User
     * @param string              $title Title for the API key
     *
     * @return ApiKeyEntityInterface|false API key entity on success, false on failure.
     */
    public function generateApiKeyForUser(UserEntityInterface $user, string $title): ApiKeyEntityInterface|false
    {
        $tokens = $this->apiKeyService->getApiKeysForUser($user);
        if ($this->apiKeysBlocked($tokens)) {
            return false;
        }
        // Generate unique id from date and users id.
        $newKey = $this->apiKeyService->createEntity();
        $newKey->setToken($this->createRandomToken($user))
            ->setUser($user)
            ->setCreated(new DateTime())
            ->setLastUsed(new DateTime())
            ->setTitle($title);
        $this->apiKeyService->persistEntity($newKey);
        return $newKey;
    }

    /**
     * Set the last used value to the API key, do this only every 5 minutes
     * to avoid excessive database queries.
     *
     * @param ApiKeyEntityInterface $apiKey API key
     *
     * @return void
     */
    public function updateApiKeyTimeStamp(ApiKeyEntityInterface $apiKey): void
    {
        if (time() - $apiKey->getCreated()->getTimestamp() < $this->updateInterval * 60) {
        }
    }

    /**
     * Can the user generate more API keys.
     *
     * @param ApiKeyEntityInterface[] $keys Users keys
     *
     * @return bool
     */
    public function apiKeysBlocked(array $keys): bool
    {
        foreach ($keys as $key) {
            if ($key->isRevoked()) {
                return true;
            }
        }
        return count($keys) >= $this->keyLimitPerUser;
    }

    /**
     * Delete an API key for a user
     *
     * @param UserEntityInterface $user User
     * @param int                 $id   API key id
     *
     * @return bool
     */
    public function deleteApiKeyForUser(UserEntityInterface $user, int $id): bool
    {
        $key = $this->apiKeyService->getByUserAndId(
            $user,
            $id
        );
        if (false === $key?->isRevoked()) {
            $this->apiKeyService->deleteEntity($key);
            return true;
        }
        return false;
    }

    /**
     * Check if API keys are enabled.
     *
     * @return bool
     */
    public function apiKeysEnabled(): bool
    {
        return in_array(
            $this->apiKeySettings['mode'] ?? ApiInterface::API_KEYS_DISABLED,
            [
                ApiInterface::API_KEYS_ENABLED,
                ApiInterface::API_KEYS_ENFORCED,
            ]
        );
    }

    /**
     * Check provided token.
     *
     * @param ?string $token Token to search for API key
     *
     * @return bool
     */
    public function isTokenValid(?string $token): bool
    {
        $apiKey = $token ? $this->getApiKeyByToken($token) : null;
        if ($this->apiKeySettings['mode'] === ApiInterface::API_KEYS_ENABLED) {
            return true;
        }
        if ($this->apiKeySettings['mode'] === ApiInterface::API_KEYS_ENFORCED) {
            return $apiKey && !$apiKey->isRevoked();
        }
        return false;
    }
}
