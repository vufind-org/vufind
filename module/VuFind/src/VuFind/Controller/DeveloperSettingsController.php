<?php

/**
 * Controller for developer settings i.e API keys
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
 * @package  Controller
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Controller;

use VuFind\DeveloperSettings\DeveloperSettingsService;
use VuFind\Exception\Forbidden;

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
        $developerSettingsService = $this->getService(DeveloperSettingsService::class);
        if (!$developerSettingsService->apiKeysEnabled()) {
            throw new Forbidden('Developer settings disabled.');
        }
        $view = $this->createViewModel();
        $view->apiKeys = $developerSettingsService->getApiKeysForUser($user);
        $view->createAllowed = !$developerSettingsService->apiKeysBlocked($view->apiKeys);
        return $view;
    }

    /**
     * Generate an API key for a user.
     *
     * @return mixed
     */
    public function generateApiKeyAction()
    {
        if (!$user = $this->getUser()) {
            return $this->forceLogin();
        }
        $developerSettingsService = $this->getService(DeveloperSettingsService::class);
        if (!$developerSettingsService->apiKeysEnabled() || !$this->permission()->isAuthorized('feature.Developer')) {
            throw new Forbidden('Access denied.');
        }

        $view = $this->createViewModel();
        if ($this->formWasSubmitted()) {
            if ($title = $this->getParam('title', true)) {
                if ($apiKey = $developerSettingsService->generateApiKeyForUser($user, $title)) {
                    $successMsg = $this->translate(
                        'Developer::api_key_generation_success',
                        ['%%TOKEN%%' => $apiKey->getToken()]
                    );
                    $this->flashMessenger()->addSuccessMessage($successMsg);
                    return $view;
                }
            }
            $this->flashMessenger()->addErrorMessage('An error has occurred');
        }

        return $view;
    }

    /**
     * Delete an API key for a user.
     *
     * @return mixed
     */
    public function deleteApiKeyAction()
    {
        if (!$user = $this->getUser()) {
            return $this->forceLogin();
        }
        $developerSettingsService = $this->getService(DeveloperSettingsService::class);
        if (!$developerSettingsService->apiKeysEnabled() || !$this->permission()->isAuthorized('feature.Developer')) {
            throw new Forbidden('Access denied.');
        }
        if ($this->getParam('confirm') === '1') {
            $id = $this->getParam('id');
            if ($id && $developerSettingsService->deleteApiKeyForUser($user, $id)) {
                $this->flashMessenger()->addSuccessMessage('Developer::api_key_deletion_success');
            } else {
                $this->flashMessenger()->addErrorMessage('An error has occurred');
            }
        }
        return $this->redirect()->toRoute('developersettings-displaysettings');
    }
}
