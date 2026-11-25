<?php

/**
 * Zotero authorization callback action.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2025-2026.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Action\Zotero;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\ActionHelper\FlashMessagesHelper;
use VuFind\ActionHelper\LoginHelper;
use VuFind\ActionHelper\RedirectHelper;
use VuFind\Exception\ZoteroException;

use function is_string;

/**
 * Zotero authorization callback action.
 *
 * @category VuFind
 * @package  Action
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class AuthCallbackAction extends AbstractZoteroAction
{
    /**
     * Handle Zotero OAuth authorization response.
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
        // Require logged-in user so that we can manage the access token:
        if (!($user = $this->authManager->getUserObject())) {
            return $this->getHelper(LoginHelper::class)->forceLogin($request, $response);
        }

        $flashMessagesHelper = $this->getHelper(FlashMessagesHelper::class);
        try {
            $result = $this->zoteroService->handleAuthCallback($user, $request->getQueryParams());
            if (is_string($result)) {
                return $this->getHelper(RedirectHelper::class)->redirectToUrl($response, $result);
            }
            $flashMessagesHelper->addSuccessMessage(
                $this->translate('export_records_complete', ['count' => $result], null, true)
            );
        } catch (ZoteroException $e) {
            $flashMessagesHelper->addErrorMessage($e->getMessage());
        }

        if ($url = $this->getAndClearReferrer()) {
            return $this->getHelper(RedirectHelper::class)->redirectToUrl($response, $url);
        }
        // Fallback just in case we are missing the referrer:
        return $this->renderTemplate($request, $response, template: 'zotero/export');
    }
}
