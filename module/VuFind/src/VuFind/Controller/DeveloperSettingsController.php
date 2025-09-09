<?php

/**
 * Controller for developer settings i.e API keys
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
 * @package  Controller
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Controller;

use VuFind\ApiKey\ApiKeyService;
use VuFind\Exception\Forbidden;

use function in_array;

/**
 * Controller for developer settings i.e API keys
 *
 * @category VuFind
 * @package  Controller
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class DeveloperSettingsController extends AbstractBase
{
    /**
     * Display developer settings
     *
     * @return mixed
     */
    public function displaySettingsAction()
    {
        if (!$user = $this->getUser()) {
            return $this->forceLogin();
        }
        // If not submitted, are we logged in?
        if (!$this->apiKeysEnabled()) {
            throw new Forbidden('Developer settings disabled.');
        }

        $apiKeyService = $this->getService(ApiKeyService::class);
        $view = $this->createViewModel();
        $view->apiKey = $apiKeyService->getApiKeyForUser($user) ?: false;
        return $view;
    }

    /**
     * Generate an API key for a user.
     *
     * @return mixed
     */
    public function generateAPIKeyAction()
    {
        if (!$user = $this->getUser()) {
            return $this->forceLogin();
        }
        // If not submitted, are we logged in?
        if (!$this->apiKeysEnabled() || !$this->permission()->isAuthorized('feature.Developer')) {
            throw new Forbidden('Access denied.');
        }

        $apiKeyService = $this->getService(ApiKeyService::class);
        if ($apiKeyService->getApiKeyForUser($user)?->isRevoked()) {
            $this->flashMessenger()->addMessage('Developer::api_key_locked', 'error');
            return $this->inLightbox()
                ? $this->getRefreshResponse()
                : $this->redirect()->toRoute('developersettings-displaysettings');
        }
        if ($token = $apiKeyService->generateApiKeyForUser($user)) {
            $successMsg = $this->translate('Developer::api_key_generation_success', ['%%TOKEN%%' => $token]);
            $this->flashMessenger()->addMessage($successMsg, 'success');
        } else {
            $this->flashMessenger()->addMessage('Developer::api_key_generation_failed', 'error');
        }
        return $this->redirect()->toRoute('developersettings-displaysettings');
    }

    /**
     * Delete an API key for a user.
     *
     * @return mixed
     */
    public function deleteAPIKeyAction()
    {
        if (!$user = $this->getUser()) {
            return $this->forceLogin();
        }
        // If not submitted, are we logged in?
        if (!$this->apiKeysEnabled() || !$this->permission()->isAuthorized('feature.Developer')) {
            throw new Forbidden('Access denied.');
        }

        $apiKeyService = $this->getService(ApiKeyService::class);
        if ($apiKeyService->getApiKeyForUser($user)?->isRevoked()) {
            $this->flashMessenger()->addMessage('Developer::api_key_locked', 'error');
            return $this->inLightbox()
                ? $this->getRefreshResponse()
                : $this->redirect()->toRoute('developersettings-displaysettings');
        }
        if ($apiKeyService->deleteApiKeyForUser($user)) {
            $this->flashMessenger()->addMessage('Developer::api_key_deletion_success', 'success');
        } else {
            $this->flashMessenger()->addMessage('Developer::api_key_deletion_failed', 'error');
        }
        return $this->redirect()->toRoute('developersettings-displaysettings');
    }

    /**
     * Check if API keys are enabled.
     *
     * @return bool
     */
    protected function apiKeysEnabled(): bool
    {
        return in_array($this->getConfig()->API_Keys?->mode ?? 'disabled', ['enabled', 'enforced']);
    }
}
