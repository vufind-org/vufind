<?php

/**
 * Delete API key action.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2025.
 * Copyright (C) The National Library of Finland 2026.
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
 * @package  Action
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Action\DeveloperSettings;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\ActionHelper\FlashMessagesHelper;
use VuFind\ActionHelper\LoginHelper;
use VuFind\ActionHelper\PermissionHelper;
use VuFind\ActionHelper\RedirectHelper;
use VuFind\Exception\Forbidden as ForbiddenException;

/**
 * Delete API key action.
 *
 * @category VuFind
 * @package  Action
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class DeleteApiKeyAction extends AbstractDeveloperSettingsAction
{
    /**
     * Generate a new API key.
     *
     * @param ServerRequestInterface $request  Server request
     * @param ResponseInterface      $response Response
     *
     * @return ResponseInterface
     */
    public function action(
        ServerRequestInterface $request,
        ResponseInterface $response,
    ): ResponseInterface {
        if (!$user = $this->authManager->getUserObject()) {
            return $this->getHelper(LoginHelper::class)->forceLogin($request, $response);
        }
        if (
            !$this->developerSettingsService->apiKeysEnabled()
            || !$this->getHelper(PermissionHelper::class)->isAuthorized('feature.Developer')
        ) {
            throw new ForbiddenException('Access denied.');
        }

        if ($this->getPostOrQueryParam('confirm') === '1') {
            $flashMessagesHelper = $this->getHelper(FlashMessagesHelper::class);
            $id = $this->getPostOrQueryParam('id');
            if ($id && $this->developerSettingsService->deleteApiKeyForUser($user, $id)) {
                $flashMessagesHelper->addSuccessMessage('Developer::api_key_deletion_success');
            } else {
                $flashMessagesHelper->addErrorMessage('An error has occurred');
            }
        }
        return $this->getHelper(RedirectHelper::class)->redirectToRoute($response, 'developersettings-displaysettings');
    }
}
