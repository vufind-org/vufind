<?php

/**
 * VuFind Helper - Renewals Support Methods
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010-2025.
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
 * @package  ILS_Logic
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\ILS\Logic;

use Laminas\Mvc\Plugin\FlashMessenger\FlashMessenger;
use Laminas\Stdlib\Parameters;
use VuFind\ILS\Connection;
use VuFind\Validator\CsrfInterface;

use function is_array;

/**
 * Helper to perform renewal-related actions
 *
 * @category VuFind
 * @package  ILS_Logic
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class RenewalsHelper
{
    /**
     * Update ILS details with renewal-specific information, if appropriate.
     *
     * @param Connection $catalog     ILS connection object
     * @param array      $ilsDetails  Transaction details from ILS driver's getMyTransactions() method
     * @param array|bool $renewStatus Renewal settings from ILS driver's checkFunction() method
     *
     * @return array $ilsDetails with renewal info added
     */
    public function addRenewDetails(
        Connection $catalog,
        array $ilsDetails,
        array|bool $renewStatus
    ): array {
        // Only add renewal information if enabled:
        if ($renewStatus) {
            if ($renewStatus['function'] == 'renewMyItemsLink') {
                // Build OPAC URL
                $ilsDetails['renew_link'] = $catalog->renewMyItemsLink($ilsDetails);
            } else {
                // Form Details
                $ilsDetails['renew_details'] = $catalog->getRenewDetails($ilsDetails);
            }
        }

        // Send back the modified array:
        return $ilsDetails;
    }

    /**
     * Process renewal requests.
     *
     * @param Parameters     $request        Request object
     * @param Connection     $catalog        ILS connection object
     * @param array          $patron         Current logged in patron
     * @param FlashMessenger $flashMessenger Flash messenger for user messages
     * @param ?CsrfInterface $csrfValidator  CSRF validator
     *
     * @return array The result of the renewal, an associative array keyed by
     * item ID (empty if no renewals performed)
     */
    public function processRenewals(
        Parameters $request,
        Connection $catalog,
        array $patron,
        FlashMessenger $flashMessenger,
        ?CsrfInterface $csrfValidator = null
    ): array {
        // Pick IDs to renew based on which button was pressed:
        $all = $request->get('renewAll');
        $selected = $request->get('renewSelected');
        if (!empty($all)) {
            $ids = $request->get('renewAllIDS');
        } elseif (!empty($selected)) {
            $ids = $request->get('selectAll')
                ? $request->get('selectAllIDS')
                : $request->get('renewSelectedIDS');
        } else {
            $ids = [];
        }

        // If there is actually something to renew, attempt the renewal action:
        if (is_array($ids) && !empty($ids)) {
            if (null !== $csrfValidator) {
                if (!$csrfValidator->isValid($request->get('csrf'))) {
                    $flashMessenger->addErrorMessage('csrf_validation_failed');
                    return [];
                }
                // After successful token verification, clear list to shrink session
                // and prevent double submit:
                $csrfValidator->trimTokenList(0);
            }

            $renewResult = $catalog->renewMyItems(
                ['details' => $ids, 'patron' => $patron]
            );
            if ($renewResult !== false) {
                // Assign Blocks to the Template
                if (is_array($renewResult['blocks'] ?? null)) {
                    foreach ($renewResult['blocks'] as $block) {
                        $flashMessenger->addInfoMessage($block);
                    }
                } elseif (is_array($renewResult['details'] ?? null)) {
                    $bad = $good = 0;
                    foreach ($renewResult['details'] as $next) {
                        if ($next['success'] ?? false) {
                            $good++;
                        } else {
                            $bad++;
                        }
                    }
                    if ($good > 0) {
                        $flashMessenger->addSuccessMessage(
                            [
                                'msg' => 'renew_success_summary',
                                'tokens' => [
                                    'count' => $good,
                                ],
                                'icu' => true,
                            ]
                        );
                    }
                    if ($bad > 0) {
                        $flashMessenger->addErrorMessage(
                            [
                                'msg' => 'renew_error_summary',
                                'tokens' => [
                                    'count' => $bad,
                                ],
                                'icu' => true,
                            ]
                        );
                    }
                }

                // Send back result details:
                return $renewResult['details'];
            } else {
                // System failure:
                $flashMessenger->addErrorMessage('renew_error');
            }
        } elseif (!empty($all) || !empty($selected)) {
            // Button was clicked but no items were selected:
            $flashMessenger->addErrorMessage('renew_empty_selection');
        }

        return [];
    }
}
