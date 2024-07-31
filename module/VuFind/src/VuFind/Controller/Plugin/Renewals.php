<?php

/**
 * VuFind Action Helper - Renewals Support Methods
 *
 * PHP version 8
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Controller_Plugins
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Controller\Plugin;

use Laminas\Mvc\Controller\Plugin\AbstractPlugin;
use VuFind\Validator\CsrfInterface;

use function is_array;

/**
 * Action helper to perform renewal-related actions
 *
 * @category VuFind
 * @package  Controller_Plugins
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class Renewals extends AbstractPlugin
{
    /**
     * Update ILS details with renewal-specific information, if appropriate.
     *
     * @param \VuFind\ILS\Connection $catalog     ILS connection object
     * @param array                  $ilsDetails  Transaction details from ILS
     * driver's getMyTransactions() method
     * @param array                  $renewStatus Renewal settings from ILS driver's
     * checkFunction() method
     *
     * @return array $ilsDetails with renewal info added
     */
    public function addRenewDetails($catalog, $ilsDetails, $renewStatus)
    {
        // Only add renewal information if enabled:
        if ($renewStatus) {
            if ($renewStatus['function'] == 'renewMyItemsLink') {
                // Build OPAC URL
                $ilsDetails['renew_link'] = $catalog->renewMyItemsLink($ilsDetails);
            } else {
                // Form Details
                $ilsDetails['renew_details']
                    = $catalog->getRenewDetails($ilsDetails);
            }
        }

        // Send back the modified array:
        return $ilsDetails;
    }

    /**
     * Process renewal requests.
     *
     * @param \Laminas\Stdlib\Parameters $request       Request object
     * @param \VuFind\ILS\Connection     $catalog       ILS connection object
     * @param array                      $patron        Current logged in patron
     * @param CsrfInterface              $csrfValidator CSRF validator
     *
     * @return array                  The result of the renewal, an
     * associative array keyed by item ID (empty if no renewals performed)
     */
    public function processRenewals(
        $request,
        $catalog,
        $patron,
        $csrfValidator = null
    ) {
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

        // Retrieve the flashMessenger helper:
        $flashMsg = $this->getController()->flashMessenger();

        // If there is actually something to renew, attempt the renewal action:
        if (is_array($ids) && !empty($ids)) {
            if (null !== $csrfValidator) {
                if (!$csrfValidator->isValid($request->get('csrf'))) {
                    $flashMsg->addErrorMessage('csrf_validation_failed');
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
                        $flashMsg->addMessage($block, 'info');
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
                        $flashMsg->addMessage(
                            ['msg' => 'renew_success_summary', 'tokens' => ['count' => $good], 'icu' => true],
                            'success'
                        );
                    }
                    if ($bad > 0) {
                        $flashMsg->addMessage(
                            ['msg' => 'renew_error_summary', 'tokens' => ['count' => $bad], 'icu' => true],
                            'error'
                        );
                    }
                }

                // Send back result details:
                return $renewResult['details'];
            } else {
                // System failure:
                $flashMsg->addMessage('renew_error', 'error');
            }
        } elseif (!empty($all) || !empty($selected)) {
            // Button was clicked but no items were selected:
            $flashMsg->addMessage('renew_empty_selection', 'error');
        }

        return [];
    }
}
