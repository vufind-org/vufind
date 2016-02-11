<?php
/**
 * VuFind Action Helper - Holds Support Methods
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
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
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
namespace VuFind\Controller\Plugin;

/**
 * Zend action helper to perform holds-related actions
 *
 * @category VuFind2
 * @package  Controller_Plugins
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class Holds extends AbstractRequestBase
{
    /**
     * Update ILS details with cancellation-specific information, if appropriate.
     *
     * @param \VuFind\ILS\Connection $catalog      ILS connection object
     * @param array                  $ilsDetails   Hold details from ILS driver's
     * getMyHolds() method
     * @param array                  $cancelStatus Cancel settings from ILS driver's
     * checkFunction() method
     *
     * @return array $ilsDetails with cancellation info added
     */
    public function addCancelDetails($catalog, $ilsDetails, $cancelStatus)
    {
        // Generate Form Details for cancelling Holds if Cancelling Holds
        // is enabled
        if ($cancelStatus) {
            if ($cancelStatus['function'] == "getCancelHoldLink") {
                // Build OPAC URL
                $ilsDetails['cancel_link']
                    = $catalog->getCancelHoldLink($ilsDetails);
            } else {
                // Form Details
                $ilsDetails['cancel_details']
                    = $catalog->getCancelHoldDetails($ilsDetails);
                $this->rememberValidId($ilsDetails['cancel_details']);
            }
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
    public function cancelHolds($catalog, $patron)
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
                if ($params->fromPost('cancelAll') !== null) {
                    return $this->getController()->confirm(
                        'hold_cancel_all',
                        $this->getController()->url()->fromRoute('myresearch-holds'),
                        $this->getController()->url()->fromRoute('myresearch-holds'),
                        'confirm_hold_cancel_all_text',
                        [
                            'cancelAll' => 1,
                            'cancelAllIDS' => $params->fromPost('cancelAllIDS')
                        ]
                    );
                } else {
                    return $this->getController()->confirm(
                        'hold_cancel_selected',
                        $this->getController()->url()->fromRoute('myresearch-holds'),
                        $this->getController()->url()->fromRoute('myresearch-holds'),
                        'confirm_hold_cancel_selected_text',
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
                    $flashMsg->addMessage('error_inconsistent_parameters', 'error');
                    return [];
                }
            }

            // Add Patron Data to Submitted Data
            $cancelResults = $catalog->cancelHolds(
                ['details' => $details, 'patron' => $patron]
            );
            if ($cancelResults == false) {
                $flashMsg->addMessage('hold_cancel_fail', 'error');
            } else {
                if ($cancelResults['count'] > 0) {
                    // TODO : add a mechanism for inserting tokens into translated
                    // messages so we can avoid a double translation here.
                    $msg = $this->getController()
                        ->translate('hold_cancel_success_items');
                    $flashMsg->addMessage(
                        $cancelResults['count'] . ' ' . $msg, 'success'
                    );
                }
                return $cancelResults;
            }
        } else {
             $flashMsg->addMessage('hold_empty_selection', 'error');
        }
        return [];
    }
}
