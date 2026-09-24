<?php

/**
 * Purge checkout history action.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Action\Checkouts;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\ActionHelper\FlashMessagesHelper;
use VuFind\ActionHelper\FormHelper;
use VuFind\ActionHelper\LoginHelper;
use VuFind\ActionHelper\RedirectHelper;

use function is_array;

/**
 * Purge checkout history action.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class PurgeHistoryAction extends AbstractCheckoutsAction
{
    /**
     * Purge checkout history.
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
        $this->ilsExceptionResponse = $redirectResponse
            = $this->getHelper(RedirectHelper::class)->redirectToRoute($response, 'checkouts-history');

        // Stop now if the user does not have valid catalog credentials available:
        if (!is_array($patron = $this->getHelper(LoginHelper::class)->catalogLogin($request, $response))) {
            if (!($patron instanceof ResponseInterface)) {
                throw new \Exception('Unexpected response from LoginHelper::catalogLogin');
            }
            return $patron;
        }

        $formHelper = $this->getHelper(FormHelper::class);
        $flashMessagesHelper = $this->getHelper(FlashMessagesHelper::class);
        $purgeSelected = $formHelper->formWasSubmitted($request, 'purgeSelected', false);
        $purgeAll = $formHelper->formWasSubmitted($request, 'purgeAll', false);
        if ($purgeSelected || $purgeAll) {
            $csrfToken = $this->getPostParam('csrf');
            if (!$this->csrf->isValid($csrfToken)) {
                $flashMessagesHelper->addErrorMessage('error_inconsistent_parameters');
                return $redirectResponse;
            }
            // After successful token verification, clear list to shrink session:
            $this->csrf->trimTokenList(0);
            if ($purgeAll) {
                $result = $this->ilsConnection->purgeTransactionHistory($patron, null);
            } else {
                $ids = $this->getPostParam('purgeSelectedIDs', []);
                if (!$ids) {
                    $flashMessagesHelper->addErrorMessage('no_items_selected');
                    return $redirectResponse;
                }
                if (!$this->validateRowIds($ids)) {
                    $flashMessagesHelper->addErrorMessage('error_inconsistent_parameters');
                    return $redirectResponse;
                }
                $result = $this->ilsConnection->purgeTransactionHistory($patron, $ids);
            }
            if ($result['success']) {
                $flashMessagesHelper->addSuccessMessage($result['status']);
            } else {
                $flashMessagesHelper->addErrorMessage($result['status']);
            }
        }
        return $redirectResponse;
    }
}
