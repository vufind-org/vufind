<?php

/**
 * Controller for Api keys.
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
 * Controller for Api keys.
 *
 * @category VuFind
 * @package  Controller
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class ApiKeyController extends AbstractBase
{
    /**
     * Display settings for the API keys
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
            throw new Forbidden('API keys disabled.');
        }

        $apiKeyService = $this->getService(ApiKeyService::class);
        $view = $this->createViewModel();
        $view->userValid = $apiKeyService->isUserValid($user);
        $view->apiKey = $apiKeyService->getApiKeyForUser($user);
        return $view;
    }

    /**
     * Generate an API key for a user.
     *
     * @return mixed
     */
    public function generateAction()
    {
        if (!$user = $this->getUser()) {
            return $this->forceLogin();
        }
        // If not submitted, are we logged in?
        if (!$this->apiKeysEnabled()) {
            throw new Forbidden('API keys disabled.');
        }
        if (!$this->getService(ApiKeyService::class)->isUserValid($user)) {
            $this->flashMessenger()->addMessage('Developer::verify_email_address', 'error');
            return $this->inLightbox() ? $this->getRefreshResponse() : $this->redirect()->toRoute('myresearch-profile');
        }
        if ($this->getService(ApiKeyService::class)->getApiKeyForUser($user)?->isRevoked()) {
            $this->flashMessenger()->addMessage('Developer::api_key_locked', 'error');
            return $this->inLightbox() ? $this->getRefreshResponse() : $this->redirect()->toRoute('myresearch-profile');
        }
        $token = $this->getService(ApiKeyService::class)->generateApiKeyForUser($user);
        if ($token) {
            $successMsg = $this->translate('Developer::api_key_generation_success', ['%%TOKEN%%' => $token]);
            $this->flashMessenger()->addMessage($successMsg, 'success');
        } else {
            $this->flashMessenger()->addMessage('Developer::api_key_generation_failed', 'error');
        }
        return $this->redirect()->toRoute('myresearch-profile');
    }

    /**
     * Delete an API key for a user.
     *
     * @return mixed
     */
    public function deleteAction()
    {
        if (!$user = $this->getUser()) {
            return $this->forceLogin();
        }
        // If not submitted, are we logged in?
        if (!$this->apiKeysEnabled()) {
            throw new Forbidden('API keys disabled.');
        }
        if (!$this->getService(ApiKeyService::class)->isUserValid($user)) {
            $this->flashMessenger()->addMessage('Developer::verify_email_address', 'error');
            return $this->inLightbox() ? $this->getRefreshResponse() : $this->redirect()->toRoute('myresearch-profile');
        }
        if ($this->getService(ApiKeyService::class)->getApiKeyForUser($user)?->isRevoked()) {
            $this->flashMessenger()->addMessage('Developer::api_key_locked', 'error');
            return $this->inLightbox() ? $this->getRefreshResponse() : $this->redirect()->toRoute('myresearch-profile');
        }
        $result = $this->getService(ApiKeyService::class)->deleteApiKeyForUser($user);
        if ($result) {
            $this->flashMessenger()->addMessage('Developer::api_key_deletion_success', 'success');
        } else {
            $this->flashMessenger()->addMessage('Developer::api_key_deletion_failed', 'error');
        }
        return $this->redirect()->toRoute('myresearch-profile');
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
