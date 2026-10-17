<?php

/**
 * VuFind Action Helper - Digitization Requests Support Methods.
 *
 * PHP version 8
 *
 * Copyright (C) Mannheim University Library 2026.
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
 * @package  Controller_Plugins
 * @author   Dennis Müller <dennis.mueller@uni-mannheim.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Controller\Plugin;

use function in_array;

/**
 * Action helper to perform digitization request related actions.
 *
 * @category VuFind
 * @package  Controller_Plugins
 * @author   Dennis Müller <dennis.mueller@uni-mannheim.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class DigitizationRequests extends AbstractRequestBase
{
    /**
     * Update ILS details with cancellation-specific information, if appropriate.
     *
     * @param \VuFind\ILS\Connection $catalog      ILS connection object
     * @param array                  $ilsDetails   Details from ILS driver's
     * getMyDigitizationRequests() method
     * @param array                  $cancelStatus Cancel settings from ILS driver's
     * checkFunction() method
     * @param array                  $patron       ILS patron
     *
     * @return array $ilsDetails with cancellation info added
     */
    public function addCancelDetails(
        $catalog,
        $ilsDetails,
        $cancelStatus,
        $patron = []
    ) {
        // Generate Form Details for cancelling if Cancelling is enabled
        if ($cancelStatus) {
            if ($cancelStatus['function'] == 'getCancelDigitizationRequestLink') {
                $ilsDetails['cancel_link']
                    = $catalog->getCancelDigitizationRequestLink($ilsDetails, $patron);
            } elseif (isset($ilsDetails['cancel_details'])) {
                // The ILS driver provided cancel details up front. If the
                // details are an empty string (flagging lack of support), we
                // should unset it to prevent confusion; otherwise, we'll leave it
                // as-is.
                if ('' === $ilsDetails['cancel_details']) {
                    unset($ilsDetails['cancel_details']);
                } else {
                    $this->rememberValidId($ilsDetails['cancel_details']);
                }
            } else {
                // Default case: ILS supports cancel but we need to look up
                // details:
                $cancelDetails
                    = $catalog->getCancelDigitizationRequestDetails($ilsDetails, $patron);
                if ($cancelDetails !== '') {
                    $ilsDetails['cancel_details'] = $cancelDetails;
                    $this->rememberValidId($ilsDetails['cancel_details']);
                }
            }
        } else {
            // Cancelling holds disabled? Make sure no details get passed back:
            unset($ilsDetails['cancel_link']);
            unset($ilsDetails['cancel_details']);
        }

        return $ilsDetails;
    }

    /**
     * Process cancellation requests.
     *
     * @param \VuFind\ILS\Connection $catalog ILS connection object
     * @param array                  $patron  Current logged in patron
     *
     * @return array                          The result of the cancellation, an
     * associative array keyed by item ID (empty if no cancellations performed)
     */
    public function cancelDigitizationRequests($catalog, $patron)
    {
        // Retrieve the flashMessenger helper:
        $flashMsg = $this->getController()->getFlashMessenger();
        $params = $this->getController()->params();

        // Pick IDs to cancel based on which button was pressed:
        $all = $params->fromPost('cancelAll');
        $selected = $params->fromPost('cancelSelected');
        if (!empty($all)) {
            $details = $params->fromPost('cancelAllIDS');
        } elseif (!empty($selected)) {
            // Include cancelSelectedIDS for backwards-compatibility with legacy code:
            $details = $params->fromPost('selectedIDS')
                ?? $params->fromPost('cancelSelectedIDS');
        } else {
            // No button pushed -- no action needed
            return [];
        }

        if (!empty($details)) {
            // Confirm?
            if ($params->fromPost('confirm') === '0') {
                if ($params->fromPost('cancelAll') !== null) {
                    return $this->getController()->confirm(
                        'digitization_request_cancel_all',
                        $this->getController()->url()->fromRoute('myresearch-digitizationrequests'),
                        $this->getController()->url()->fromRoute('myresearch-digitizationrequests'),
                        'confirm_digitization_request_cancel_all_text',
                        [
                            'cancelAll' => 1,
                            'cancelAllIDS' => $params->fromPost('cancelAllIDS'),
                        ]
                    );
                } else {
                    return $this->getController()->confirm(
                        'digitization_request_cancel_selected',
                        $this->getController()->url()->fromRoute('myresearch-digitizationrequests'),
                        $this->getController()->url()->fromRoute('myresearch-digitizationrequests'),
                        'confirm_digitization_request_cancel_selected_text',
                        [
                            'cancelSelected' => 1,
                            'cancelSelectedIDS' =>
                                $params->fromPost('cancelSelectedIDS'),
                        ]
                    );
                }
            }

            foreach ($details as $info) {
                // If the user input contains a value not found in the session
                // legal list, something has been tampered with -- abort the process.
                if (!in_array($info, $this->getValidIds())) {
                    $flashMsg->addErrorMessage('error_inconsistent_parameters');
                    return [];
                }
            }

            // Add Patron Data to Submitted Data
            $cancelResults = $catalog->cancelDigitizationRequests(
                ['details' => $details, 'patron' => $patron]
            );
            if ($cancelResults == false) {
                $flashMsg->addErrorMessage('digitization_request_cancel_fail');
            } else {
                $failed = 0;
                foreach ($cancelResults['items'] ?? [] as $item) {
                    if (!$item['success']) {
                        ++$failed;
                    }
                }
                if ($failed) {
                    $flashMsg->addErrorMessage(
                        ['msg' => 'digitization_request_cancel_fail_items', 'tokens' => ['%%count%%' => $failed]]
                    );
                }
                if ($cancelResults['count'] > 0) {
                    $flashMsg->addSuccessMessage(
                        ['msg' => 'digitization_request_cancel_success_items', 'tokens' => ['%%count%%' => $cancelResults['count']]]
                    );
                }
                return $cancelResults;
            }
        } else {
            $flashMsg->addErrorMessage('digitization_request_empty_selection');
        }
        return [];
    }
}
