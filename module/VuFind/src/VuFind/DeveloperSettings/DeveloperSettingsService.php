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
 * @package  Developer_Settings
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */

namespace VuFind\DeveloperSettings;

use DateTime;
use VuFind\Db\Entity\ApiKeyEntityInterface;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Service\ApiKeyServiceInterface;

use function count;

/**
 * Service for managing API keys
 *
 * @category VuFind
 * @package  Developer_Settings
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
     * Update interval to update last_used values in minutes.
     *
     * @var int
     */
    protected int $updateInterval = 60;

    /**
     * Constructor.
     *
     * @param ApiKeyServiceInterface $apiKeyService  API key database service
     * @param array                  $apiKeySettings Section API_Keys from main configuration.
     */
    public function __construct(
        protected ApiKeyServiceInterface $apiKeyService,
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
    protected function createNewToken(UserEntityInterface $user): string
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
        $date = new DateTime();
        $newKey->setToken($this->createNewToken($user))
            ->setUser($user)
            ->setCreated($date)
            ->setLastUsed($date)
            ->setTitle($title);
        $this->apiKeyService->persistEntity($newKey);
        return $newKey;
    }

    /**
     * Set the last used value to the API key. By default this will be only updated once every hour.
     *
     * @param ApiKeyEntityInterface $apiKey API key
     *
     * @return void
     */
    protected function updateLastUsed(ApiKeyEntityInterface $apiKey): void
    {
        if (time() - $apiKey->getLastUsed()->getTimestamp() < $this->updateInterval * 60) {
            $apiKey->setLastUsed(new \DateTime());
            $this->apiKeyService->persistEntity($apiKey);
        }
    }

    /**
     * Is the user blocked from generating new API keys?
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
            $this->apiKeyService->deleteApiKey($key);
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
        return DeveloperSettingsStatus::settingEnabled($this->apiKeySettings['mode'] ?? '');
    }

    /**
     * Check if the provided token is valid. If user provides an API key token
     * in optional mode, it will act the same way even if
     *
     * @param ?string $token Token to search for API key
     *
     * @return bool
     */
    public function isTokenValid(?string $token): bool
    {
        if (!$this->apiKeysEnabled()) {
            return true;
        }
        if ($apiKey = $this->apiKeyService->getByToken((string)$token)) {
            $this->updateLastUsed($apiKey);
            return !$apiKey->isRevoked();
        }
        // The token counts as valid if user did not provide one and mode is optional.
        return null === $token && $this->apiKeySettings['mode'] === DeveloperSettingsStatus::OPTIONAL->value;
    }
}
