<?php

/**
 * VuFind Action Helper - Storage Retrieval Requests Support Methods.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2014-2026.
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
 * @package  Action_Helper
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\ActionHelper;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\ILS\Connection;

use function in_array;

/**
 * Action helper to perform storage retrieval request related actions.
 *
 * @category VuFind
 * @package  Action_Helper
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class StorageRetrievalRequestsHelper extends AbstractRequestBase
{
    /**
     * Update ILS details with cancellation-specific information, if appropriate.
     *
     * @param Connection $catalog      ILS connection object
     * @param array      $ilsDetails   Details from ILS driver's getMyStorageRetrievalRequests() method
     * @param array      $cancelStatus Cancel settings from ILS driver's checkFunction() method
     * @param array      $patron       ILS patron
     *
     * @return array $ilsDetails with cancellation info added
     */
    public function addCancelDetails(
        Connection $catalog,
        array $ilsDetails,
        array $cancelStatus,
        array $patron = []
    ): array {
        // Generate form details for cancelling requests if enabled
        if ($cancelStatus) {
            if ($cancelStatus['function'] == 'getCancelStorageRetrievalRequestsLink') {
                // Build OPAC URL
                $ilsDetails['cancel_link'] = $catalog->getCancelStorageRetrievalRequestLink($ilsDetails, $patron);
            } else {
                // Form Details
                $ilsDetails['cancel_details'] = $catalog->getCancelStorageRetrievalRequestDetails($ilsDetails, $patron);
                $this->rememberValidId($ilsDetails['cancel_details']);
            }
        }

        return $ilsDetails;
    }

    /**
     * Process cancel request.
     *
     * @param ServerRequestInterface $request  Request
     * @param ResponseInterface      $response Response
     * @param Connection             $catalog  ILS connection object
     * @param array                  $patron   Current logged in patron
     *
     * @return array                          The result of the cancellation, an
     * associative array keyed by item ID (empty if no cancellations performed)
     */
    public function cancelStorageRetrievalRequests(
        ServerRequestInterface $request,
        ResponseInterface $response,
        Connection $catalog,
        array $patron
    ): array|ResponseInterface {
        $postParams = $request->getParsedBody();

        // Pick IDs to cancel based on which button was pressed:
        $all = $postParams['cancelAll'] ?? null;
        $selected = $postParams['cancelSelected'] ?? null;
        if ($all) {
            $details = $postParams['cancelAllIDS'] ?? null;
        } elseif ($selected) {
            $details = $postParams['cancelSelectedIDS'] ?? null;
        } else {
            // No button pushed -- no action needed
            return [];
        }

        if (!empty($details)) {
            // Confirm?
            if (($postParams['confirm'] ?? null) === '0') {
                $targetUrl = $this->routeHelper->getUrlFromRoute('myresearch-storageretrievalrequests');
                if ($all !== null) {
                    return $this->forwardHelper->forwardToConfirm(
                        $request,
                        $response,
                        'storage_retrieval_request_cancel_all',
                        $targetUrl,
                        $targetUrl,
                        'confirm_storage_retrieval_request_cancel_all_text',
                        [
                            'cancelAll' => 1,
                            'cancelAllIDS' => $details,
                        ]
                    );
                } else {
                    return $this->forwardHelper->forwardToConfirm(
                        $request,
                        $response,
                        'storage_retrieval_request_cancel_selected',
                        $targetUrl,
                        $targetUrl,
                        'confirm_storage_retrieval_request_cancel_selected_text',
                        [
                            'cancelSelected' => 1,
                            'cancelSelectedIDS' => $details,
                        ]
                    );
                }
            }

            foreach ($details as $info) {
                // If the user input contains a value not found in the session
                // legal list, something has been tampered with -- abort the process.
                if (!in_array($info, $this->getValidIds())) {
                    $this->flashMessenger->addErrorMessage('error_inconsistent_parameters');
                    return [];
                }
            }

            // Add Patron Data to Submitted Data
            $cancelResults = $catalog->cancelStorageRetrievalRequests(compact('details', 'patron'));
            if ($cancelResults == false) {
                $this->flashMessenger->addErrorMessage('storage_retrieval_request_cancel_fail');
            } else {
                $failed = 0;
                foreach ($cancelResults['items'] ?? [] as $item) {
                    if (!$item['success']) {
                        ++$failed;
                    }
                }
                if ($failed) {
                    $this->flashMessenger->addErrorMessage(
                        [
                            'msg' => 'storage_retrieval_request_cancel_fail_items',
                            'tokens' => ['%%count%%' => $failed],
                        ]
                    );
                }
                if ($cancelResults['count'] > 0) {
                    $this->flashMessenger->addSuccessMessage(
                        [
                            'msg' => 'storage_retrieval_request_cancel_success_items',
                            'tokens' => ['%%count%%' => $cancelResults['count']],
                        ]
                    );
                }
                return $cancelResults;
            }
        } else {
            $this->flashMessenger->addErrorMessage('storage_retrieval_request_empty_selection');
        }
        return [];
    }
}
