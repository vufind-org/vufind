<?php

/**
 * API key trait.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library 2025.
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
 * @package  ApiKey
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */

namespace VuFind\ApiKey;

use Laminas\Http\Response;

use function in_array;
use function is_callable;

/**
 * API key trait.
 *
 * @category VuFind
 * @package  ApiKey
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
trait ApiKeyTrait
{
    /**
     * Is API key used?
     * - 'disabled' => Keys are not used and cannot be created
     * - 'enabled' => Keys can be created and used but not enforced
     * - 'enforced' => Keys are enforced
     *
     * @var string
     */
    protected string $apiKeyMode;

    /**
     * Name of HTTP header
     *
     * @var string
     */
    protected string $apiKeyHeaderField = '';

    /**
     * API key service
     *
     * @var ApiKeyService
     */
    protected ApiKeyService $apiKeyService;

    /**
     * Key for header to look for an API key
     *
     * @var string
     */
    protected string $apiKeyHeader;

    /**
     * Log requests with API keys
     *
     * @var bool
     */
    protected bool $logApiKeyRequests = false;

    /**
     * Set API key service
     *
     * @param ApiKeyService $apiKeyService API key service
     *
     * @return void
     */
    protected function setApiKeyService(ApiKeyService $apiKeyService): void
    {
        $this->apiKeyService = $apiKeyService;
    }

    /**
     * Init API key settings
     *
     * @param array $settings API key settings from config.ini
     *
     * @return void;
     */
    protected function initAPIKeySettings(array $settings): void
    {
        $this->apiKeyMode = $settings['mode'] ?? 'disabled';
        if ($this->isApiKeyEnabled() && is_callable([$this, 'getService'])) {
            $this->apiKeyService = $this->getService(\VuFind\ApiKey\ApiKeyService::class);
            $this->apiKeyHeaderField = $settings['header_field'] ?? 'X-API-KEY';
            $this->logApiKeyRequests = (bool)($settings['log_requests'] ?? false);
        }
    }
    /**
     * Is API key enabled?
     *
     * @return bool
     */
    protected function isApiKeyEnabled(): bool
    {
        return in_array($this->apiKeyMode, ['enabled', 'enforced']);
    }

    /**
     * Check request for API key if mode is not set to disabled.
     *
     * @return bool
     */
    protected function checkRequestForApiKey(): bool
    {
        if (!$this->isApiKeyEnabled()) {
            return true;
        }
        if ($apiKey = $this->getRequest()->getHeader($this->apiKeyHeaderField)) {
            $apiKey = $apiKey->getFieldValue();
            $this->logApiKeyRequest($apiKey);
        }
        return match ($this->apiKeyMode) {
            'enabled' => true,
            'enforced' => $apiKey && $this->apiKeyService->isTokenValid($apiKey),
            default => false,
        };
    }

    /**
     * Get response to display bad or missing API key.
     *
     * @return Response
     */
    protected function getBadApiKeyResponse(): Response
    {
        $response = $this->getResponse();
        $response->setStatusCode(401);
        $response->setContent('Provided API key is missing or invalid.');
        return $response;
    }

    /**
     * Log a request, which contains an API key.
     *
     * @param string $apiKey API key to log
     *
     * @return void
     */
    protected function logApiKeyRequest(string $apiKey): void
    {
        if (!$this->logApiKeyRequests || !is_callable([$this, 'debug']) || !isset($this->logger)) {
            return;
        }
        $this->debug('API_KEY_REQUEST:', ['key' => $apiKey]);
    }
}
