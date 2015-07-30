<?php
/**
 * VuFind Action Helper - Storage Retrieval Requests Support Methods
 *
 * PHP version 5
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Controller_Plugins
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
namespace VuFind\Controller\Plugin;

/**
 * Zend action helper to perform storage retrieval request related actions
 *
 * @category VuFind2
 * @package  Controller_Plugins
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class StorageRetrievalRequests extends AbstractRequestBase
{
    /**
     * Update ILS details with cancellation-specific information, if appropriate.
     *
     * @param \VuFind\ILS\Connection $catalog      ILS connection object
     * @param array                  $ilsDetails   Details from ILS driver's
     * getMyStorageRetrievalRequests() method
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
            if ($cancelStatus['function'] == 'getCancelStorageRetrievalRequestsLink'
            ) {
                // Build OPAC URL
                $ilsDetails['cancel_link']
                    = $catalog->getCancelStorageRetrievalRequestLink(
                        $ilsDetails,
                        $patron
                    );
            } else {
                // Form Details
                $ilsDetails['cancel_details']
                    = $catalog->getCancelStorageRetrievalRequestDetails(
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
    public function cancelStorageRetrievalRequests($catalog, $patron)
    {
        // Retrieve the flashMessenger helper:
        $flashMsg = $this->getController()->flashMessenger();
        $params = $this->getController()->params();

        // Pick IDs to cancel based on which button was pressed:
        $all = $params->fromPost('cancelAll');
        $selected = $params->fromPost('cancelSelected');
        if (!empty($all)) {
            $details = $params->fromPost('cancelAllIDS');
        } else if (!empty($selected)) {
            $details = $params->fromPost('cancelSelectedIDS');
        } else {
            // No button pushed -- no action needed
            return [];
        }

        if (!empty($details)) {
            // Confirm?
            if ($params->fromPost('confirm') === "0") {
                $url = $this->getController()->url()
                    ->fromRoute('myresearch-storageretrievalrequests');
                if ($params->fromPost('cancelAll') !== null) {
                    return $this->getController()->confirm(
                        'storage_retrieval_request_cancel_all',
                        $url,
                        $url,
                        'confirm_storage_retrieval_request_cancel_all_text',
                        [
                            'cancelAll' => 1,
                            'cancelAllIDS' => $params->fromPost('cancelAllIDS')
                        ]
                    );
                } else {
                    return $this->getController()->confirm(
                        'storage_retrieval_request_cancel_selected',
                        $url,
                        $url,
                        'confirm_storage_retrieval_request_cancel_selected_text',
                        [
                            'cancelSelected' => 1,
                            'cancelSelectedIDS' =>
                                $params->fromPost('cancelSelectedIDS')
                        ]
                    );
                }
            }

            foreach ($details as $info) {
                // If the user input contains a value not found in the session
                // whitelist, something has been tampered with -- abort the process.
                if (!in_array($info, $this->getSession()->validIds)) {
                    $flashMsg->setNamespace('error')
                        ->addMessage('error_inconsistent_parameters');
                    return [];
                }
            }

            // Add Patron Data to Submitted Data
            $cancelResults = $catalog->cancelStorageRetrievalRequests(
                ['details' => $details, 'patron' => $patron]
            );
            if ($cancelResults == false) {
                $flashMsg->setNamespace('error')->addMessage(
                    'storage_retrieval_request_cancel_fail'
                );
            } else {
                if ($cancelResults['count'] > 0) {
                    // TODO : add a mechanism for inserting tokens into translated
                    // messages so we can avoid a double translation here.
                    $msg = $this->getController()->translate(
                        'storage_retrieval_request_cancel_success_items'
                    );
                    $flashMsg->setNamespace('success')->addMessage(
                        $cancelResults['count'] . ' ' . $msg
                    );
                }
                return $cancelResults;
            }
        } else {
            $flashMsg->setNamespace('error')->addMessage(
                'storage_retrieval_request_empty_selection'
            );
        }
        return [];
    }
}
