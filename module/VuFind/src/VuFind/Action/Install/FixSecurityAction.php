<?php

/**
 * Install "fix security" action.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010, 2022.
 * Copyright (C) The National Library of Finland 2026.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.    See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Action\Install;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\ActionHelper\FlashMessagesHelper;
use VuFind\ActionHelper\ForwardHelper;
use VuFind\ActionHelper\RedirectHelper;

use function count;

/**
 * Install "fix security" action.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class FixSecurityAction extends AbstractInstallAction
{
    /**
     * Display repair instructions for security problems.
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
        $flashMessagesHelper = $this->getHelper(FlashMessagesHelper::class);
        $redirectHelper = $this->getHelper(RedirectHelper::class);
        // If the user doesn't want to proceed, abort now:
        $userConfirmation = $this->getPostParam('fix-user-table');
        if ($userConfirmation === 'No') {
            $msg = 'Security upgrade aborted.';
            $flashMessagesHelper->addErrorMessage($msg);
            return $redirectHelper->redirectToRoute($response, 'install-home');
        }

        // If we don't need to prompt the user, or if they confirmed, do the fix:
        try {
            $userRows = $this->userService->getInsecureRows();
            $cardRows = $this->userCardService->getInsecureRows();
        } catch (\Throwable $e) {
            $flashMessagesHelper
                ->addErrorMessage('Cannot connect to database; please configure database before fixing security.');
            return $redirectHelper->redirectToRoute($response, 'install-home');
        }
        if (count($userRows) + count($cardRows) == 0 || $userConfirmation === 'Yes') {
            return $this->getHelper(ForwardHelper::class)->forwardTo($request, $response, 'Install/performsecurityfix');
        }

        // If we got this far, we need to ask permission to proceed:
        return $this->renderTemplate($request, $response, ['confirmUserFix' => true]);
    }
}
