<?php

/**
 * VuFind Action Helper - Holds Support Methods
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2021.
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

use VuFind\Date\DateException;

use function in_array;

/**
 * Action helper to perform holds-related actions
 *
 * @category VuFind
 * @package  Controller_Plugins
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
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
        // Generate Form Details for cancelling Holds if Cancelling Holds
        // is enabled
        if ($cancelStatus) {
            if ($cancelStatus['function'] == 'getCancelHoldLink') {
                // Build OPAC URL
                $ilsDetails['cancel_link']
                    = $catalog->getCancelHoldLink($ilsDetails, $patron);
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
                    = $catalog->getCancelHoldDetails($ilsDetails, $patron);
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
        } elseif (!empty($selected)) {
            // Include cancelSelectedIDS for backwards-compatibility:
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
                        'hold_cancel_all',
                        $this->getController()->url()->fromRoute('holds-list'),
                        $this->getController()->url()->fromRoute('holds-list'),
                        'confirm_hold_cancel_all_text',
                        [
                            'cancelAll' => 1,
                            'cancelAllIDS' => $params->fromPost('cancelAllIDS'),
                        ]
                    );
                } else {
                    return $this->getController()->confirm(
                        'hold_cancel_selected',
                        $this->getController()->url()->fromRoute('holds-list'),
                        $this->getController()->url()->fromRoute('holds-list'),
                        'confirm_hold_cancel_selected_text',
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
                    $flashMsg->addErrorMessage('error_inconsistent_parameters');
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
                $failed = 0;
                foreach ($cancelResults['items'] ?? [] as $item) {
                    if (!$item['success']) {
                        ++$failed;
                    }
                }
                if ($failed) {
                    $flashMsg->addErrorMessage(
                        ['msg' => 'hold_cancel_fail_items', 'tokens' => ['%%count%%' => $failed]]
                    );
                }
                if ($cancelResults['count'] > 0) {
                    $flashMsg->addSuccessMessage(
                        ['msg' => 'hold_cancel_success_items', 'tokens' => ['%%count%%' => $cancelResults['count']]]
                    );
                }
                return $cancelResults;
            }
        } else {
            $flashMsg->addMessage('hold_empty_selection', 'error');
        }
        return [];
    }

    /**
     * Check if the user-provided dates are valid.
     *
     * Returns validated dates and/or an array of validation errors if there are
     * problems.
     *
     * @param string $startDate         User-specified start date
     * @param string $requiredBy        User-specified required-by date
     * @param array  $enabledFormFields Hold form fields enabled by
     * configuration/driver
     *
     * @return array
     */
    public function validateDates(
        ?string $startDate,
        ?string $requiredBy,
        array $enabledFormFields
    ): array {
        $result = [
            'startDateTS' => null,
            'requiredByTS' => null,
            'errors' => [],
        ];
        if (
            !in_array('startDate', $enabledFormFields)
            && !in_array('requiredByDate', $enabledFormFields)
            && !in_array('requiredByDateOptional', $enabledFormFields)
        ) {
            return $result;
        }

        if (in_array('startDate', $enabledFormFields)) {
            try {
                $result['startDateTS'] = $startDate
                    ? (int)$this->dateConverter->convertFromDisplayDate(
                        'U',
                        $startDate
                    ) : 0;
                if ($result['startDateTS'] < strtotime('today')) {
                    $result['errors'][] = 'hold_start_date_invalid';
                }
            } catch (DateException $e) {
                $result['errors'][] = 'hold_start_date_invalid';
            }
        }

        if (
            in_array('requiredByDate', $enabledFormFields)
            || in_array('requiredByDateOptional', $enabledFormFields)
        ) {
            $optional = in_array('requiredByDateOptional', $enabledFormFields);
            try {
                if ($requiredBy) {
                    $requiredByDateTime = \DateTime::createFromFormat(
                        'U',
                        $this->dateConverter
                            ->convertFromDisplayDate('U', $requiredBy)
                    );
                    $result['requiredByTS'] = $requiredByDateTime
                        ->setTime(23, 59, 59)
                        ->getTimestamp();
                } else {
                    $result['requiredByTS'] = 0;
                }
                if (
                    (!$optional || $result['requiredByTS'])
                    && $result['requiredByTS'] < strtotime('today')
                ) {
                    $result['errors'][] = 'hold_required_by_date_invalid';
                }
            } catch (DateException $e) {
                $result['errors'][] = 'hold_required_by_date_invalid';
            }
        }

        if (
            !$result['errors']
            && in_array('startDate', $enabledFormFields)
            && !empty($result['requiredByTS'])
            && $result['startDateTS'] > $result['requiredByTS']
        ) {
            $result['errors'][] = 'hold_required_by_date_before_start_date';
        }

        return $result;
    }

    /**
     * Check if the user-provided "frozen through" date is valid.
     *
     * Returns validated date and/or an array of validation errors if there are
     * problems.
     *
     * @param string $frozenThrough   User-specified "frozen through" date
     * @param array  $extraHoldFields Hold form fields enabled by
     * configuration/driver
     *
     * @return array
     */
    public function validateFrozenThrough(
        ?string $frozenThrough,
        array $extraHoldFields
    ): array {
        $result = [
            'frozenThroughTS' => null,
            'errors' => [],
        ];
        if (!in_array('frozenThrough', $extraHoldFields) || empty($frozenThrough)) {
            return $result;
        }

        try {
            $result['frozenThroughTS']
                = $this->dateConverter->convertFromDisplayDate('U', $frozenThrough);
            if ($result['frozenThroughTS'] < time()) {
                $result['errors'][] = 'hold_frozen_through_date_invalid';
            }
        } catch (DateException $e) {
            $result['errors'][] = 'hold_frozen_through_date_invalid';
        }

        return $result;
    }
}
