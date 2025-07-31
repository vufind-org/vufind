<?php

/**
 * Api Key trait.
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
 * Api Key trait.
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
     * Is api key used?
     * - 'disabled' => Keys are not used and cannot be created
     * - 'enabled' => Keys can be created and used but not enforced
     * - 'enforced' => Keys are enforced
     *
     * @var string
     */
    protected string $apiKeyMode;

    /**
     * Request header field name for the API key
     *
     * @var string
     */
    protected string $apiKeyHeaderField = '';

    /**
     * Api key service
     *
     * @var ApiKeyService
     */
    protected ApiKeyService $apiKeyService;

    /**
     * Key for header to look for an api key
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
     * Set Api Key service
     *
     * @param ApiKeyService $apiKeyService Api Key service
     *
     * @return void
     */
    protected function setApiKeyService(ApiKeyService $apiKeyService): void
    {
        $this->apiKeyService = $apiKeyService;
    }

    /**
     * Set api key mode.
     *
     * @param $apiKeyMode Api key mode: disabled, enabled, enforced.
     *
     * @return void
     */
    protected function setApiKeyMode(string $apiKeyMode = 'disabled'): void
    {
        $this->apiKeyMode = $apiKeyMode;
    }

    /**
     * Set API key header field.
     *
     * @param string $header Header field name for the api key. Default X-API-KEY.
     *
     * @return void
     */
    protected function setApiKeyHeader(string $header = 'X-API-KEY'): void
    {
        $this->apiKeyHeaderField = $header;
    }

    /**
     * Set if API key requests can be logged.
     *
     * @param bool $log Should requests with API keys be logged.
     *
     * @return void
     */
    protected function setApiKeyLogging(bool $log = false): void
    {
        $this->logApiKeyRequests = $log;
    }

    /**
     * Is api key enabled?
     *
     * @return bool
     */
    protected function isApiKeyEnabled(): bool
    {
        return in_array($this->apiKeyMode, ['enabled', 'enforced']);
    }

    /**
     * Check request for api key if mode is not set to disabled.
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
     * Get response to display bad or missing api key.
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
