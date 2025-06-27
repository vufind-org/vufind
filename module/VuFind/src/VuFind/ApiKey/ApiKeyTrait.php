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
use VuFind\Db\Service\ApiKeyServiceInterface;

use function in_array;

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
     * 'enabled' => Keys can be created and used but not enforced
     * 'enforced' => Keys are enforced
     *
     * @var string
     */
    protected string $apiKeyMode;

    /**
     * Api key service
     *
     * @var ApiKeyServiceInterface
     */
    protected ApiKeyServiceInterface $apiKeyService;

    /**
     * Key for header to look for an api key
     *
     * @var string
     */
    protected string $apiKeyHeader = 'X-API-KEY';

    /**
     * Set Api Key service
     *
     * @param ApiKeyServiceInterface $apiKeyService Api Key service
     *
     * @return void
     */
    protected function setApiKeyService(ApiKeyServiceInterface $apiKeyService): void
    {
        $this->apiKeyService = $apiKeyService;
    }

    /**
     * Set api key mode.
     *
     * @param $apiKeyMode Api key mode, disabled, enabled, enforced.
     *
     * @return void
     */
    protected function setApiKeyMode(string $apiKeyMode = 'disabled'): void
    {
        $this->apiKeyMode = $apiKeyMode;
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
        if ($apiKey = $this->getRequest()->getHeader('X-API-KEY')) {
            $apiKey = $apiKey->getFieldValue();
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
}
