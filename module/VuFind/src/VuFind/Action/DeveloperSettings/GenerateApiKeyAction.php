<?php

/**
 * Generate API key action.
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
use VuFind\ActionHelper\FormHelper;
use VuFind\ActionHelper\LoginHelper;
use VuFind\ActionHelper\PermissionHelper;
use VuFind\Exception\Forbidden as ForbiddenException;
use VuFind\I18n\Translator\TranslatorAwareInterface;
use VuFind\I18n\Translator\TranslatorAwareTrait;

/**
 * Generate API key action.
 *
 * @category VuFind
 * @package  Action
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class GenerateApiKeyAction extends AbstractDeveloperSettingsAction implements TranslatorAwareInterface
{
    use TranslatorAwareTrait;

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

        if ($this->getHelper(FormHelper::class)->formWasSubmitted($request)) {
            $flashMessagesHelper = $this->getHelper(FlashMessagesHelper::class);
            if ($title = $this->getPostOrQueryParam('title')) {
                if ($apiKey = $this->developerSettingsService->generateApiKeyForUser($user, $title)) {
                    $successMsg = $this->translate(
                        'Developer::api_key_generation_success',
                        ['%%TOKEN%%' => $apiKey->getToken()]
                    );
                    $flashMessagesHelper->addSuccessMessage($successMsg);
                    return $this->renderTemplate($request, $response);
                }
            }
            $flashMessagesHelper->addErrorMessage('An error has occurred');
        }

        return $this->renderTemplate($request, $response);
    }
}
