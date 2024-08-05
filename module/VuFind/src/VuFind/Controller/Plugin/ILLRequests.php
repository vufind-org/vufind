<?php

/**
 * VuFind Action Helper - ILL Requests Support Methods
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2014.
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
 * @package  Controller_Plugins
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Controller\Plugin;

use function in_array;

/**
 * Action helper to perform ILL request related actions
 *
 * @category VuFind
 * @package  Controller_Plugins
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class ILLRequests extends AbstractRequestBase
{
    /**
     * Update ILS details with cancellation-specific information, if appropriate.
     *
     * @param \VuFind\ILS\Connection $catalog      ILS connection object
     * @param array                  $ilsDetails   Details from ILS driver's
     * getMyILLRequests() method
     * @param array                  $cancelStatus Cancellation settings from ILS
     * driver's checkFunction() method
     * @param array                  $patron       ILS patron
     *
     * @return array $ilsDetails with cancellation info added
     */
    public function addCancelDetails($catalog, $ilsDetails, $cancelStatus, $patron)
    {
        // Generate form details for cancelling requests if enabled
        if ($cancelStatus) {
            if (
                $cancelStatus['function'] == 'getCancelILLRequestsLink'
            ) {
                // Build OPAC URL
                $ilsDetails['cancel_link']
                    = $catalog->getCancelILLRequestLink(
                        $ilsDetails,
                        $patron
                    );
            } else {
                // Form Details
                $ilsDetails['cancel_details']
                    = $catalog->getCancelILLRequestDetails(
                        $ilsDetails,
                        $patron
                    );
                $this->rememberValidId(
                    $ilsDetails['cancel_details']
                );
            }
        }

        return $ilsDetails;
    }

    /**
     * Process cancel request.
     *
     * @param \VuFind\ILS\Connection $catalog ILS connection object
     * @param array                  $patron  Current logged in patron
     *
     * @return array                          The result of the cancellation, an
     * associative array keyed by item ID (empty if no cancellations performed)
     */
    public function cancelILLRequests($catalog, $patron)
    {
        // Retrieve the flashMessenger helper:
        $flashMsg = $this->getController()->flashMessenger();
        $params = $this->getController()->params();

        // Pick IDs to cancel based on which button was pressed:
        $all = $params->fromPost('cancelAll');
        $selected = $params->fromPost('cancelSelected');
        if (!empty($all)) {
            $details = $params->fromPost('cancelAllIDS');
        } elseif (!empty($selected)) {
            $details = $params->fromPost('cancelSelectedIDS');
        } else {
            // No button pushed -- no action needed
            return [];
        }

        if (!empty($details)) {
            // Confirm?
            if ($params->fromPost('confirm') === '0') {
                $url = $this->getController()->url()
                    ->fromRoute('myresearch-illrequests');
                if ($params->fromPost('cancelAll') !== null) {
                    return $this->getController()->confirm(
                        'ill_request_cancel_all',
                        $url,
                        $url,
                        'confirm_ill_request_cancel_all_text',
                        [
                            'cancelAll' => 1,
                            'cancelAllIDS' => $params->fromPost('cancelAllIDS'),
                        ]
                    );
                } else {
                    return $this->getController()->confirm(
                        'ill_request_cancel_selected',
                        $url,
                        $url,
                        'confirm_ill_request_cancel_selected_text',
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
                if (!in_array($info, $this->getSession()->validIds)) {
                    $flashMsg->addMessage('error_inconsistent_parameters', 'error');
                    return [];
                }
            }

            // Add Patron Data to Submitted Data
            $cancelResults = $catalog->cancelILLRequests(
                ['details' => $details, 'patron' => $patron]
            );
            if ($cancelResults == false) {
                $flashMsg->addMessage('ill_request_cancel_fail', 'error');
            } else {
                $failed = 0;
                foreach ($cancelResults['items'] ?? [] as $item) {
                    if (!$item['success']) {
                        ++$failed;
                    }
                }
                if ($failed) {
                    $flashMsg->addErrorMessage(
                        ['msg' => 'ill_request_cancel_fail_items', 'tokens' => ['%%count%%' => $failed]]
                    );
                }
                if ($cancelResults['count'] > 0) {
                    $flashMsg->addSuccessMessage(
                        [
                            'msg' => 'ill_request_cancel_success_items',
                            'tokens' => ['%%count%%' => $cancelResults['count']],
                        ]
                    );
                }
                return $cancelResults;
            }
        } else {
            $flashMsg->addMessage('ill_request_empty_selection', 'error');
        }
        return [];
    }
}
