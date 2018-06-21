<?php
/**
 * Holds trait (for subclasses of AbstractRecord)
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
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace VuFind\Controller;

/**
 * Holds trait (for subclasses of AbstractRecord)
 *
 * @category VuFind2
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
trait HoldsTrait
{
    /**
     * Action for dealing with blocked holds.
     *
     * @return mixed
     */
    public function blockedholdAction()
    {
        $this->flashMessenger()->addMessage('hold_error_blocked', 'error');
        return $this->redirectToRecord('#top');
    }

    /**
     * Action for dealing with holds.
     *
     * @return mixed
     */
    public function holdAction()
    {
        $driver = $this->loadRecord();

        // Stop now if the user does not have valid catalog credentials available:
        if (!is_array($patron = $this->catalogLogin())) {
            return $patron;
        }

        // If we're not supposed to be here, give up now!
        $catalog = $this->getILS();
        $checkHolds = $catalog->checkFunction(
            'Holds',
            [
                'id' => $driver->getUniqueID(),
                'patron' => $patron
            ]
        );
        if (!$checkHolds) {
            return $this->redirectToRecord();
        }

        // Do we have valid information?
        // Sets $this->logonURL and $this->gatheredDetails
        $gatheredDetails = $this->holds()->validateRequest($checkHolds['HMACKeys']);
        if (!$gatheredDetails) {
            return $this->redirectToRecord();
        }

        // Block invalid requests:
        if (!$catalog->checkRequestIsValid(
            $driver->getUniqueID(), $gatheredDetails, $patron
        )) {
            return $this->blockedholdAction();
        }

        // Send various values to the view so we can build the form:
        $pickup = $catalog->getPickUpLocations($patron, $gatheredDetails);
        $requestGroups = $catalog->checkCapability(
            'getRequestGroups', [$driver->getUniqueID(), $patron]
        ) ? $catalog->getRequestGroups($driver->getUniqueID(), $patron) : [];
        $extraHoldFields = isset($checkHolds['extraHoldFields'])
            ? explode(":", $checkHolds['extraHoldFields']) : [];

        // Process form submissions if necessary:
        if (!is_null($this->params()->fromPost('placeHold'))) {
            // If the form contained a pickup location or request group, make sure
            // they are valid:
            $valid = $this->holds()->validateRequestGroupInput(
                $gatheredDetails, $extraHoldFields, $requestGroups
            );
            if (!$valid) {
                $this->flashMessenger()
                    ->addMessage('hold_invalid_request_group', 'error');
            } elseif (!$this->holds()->validatePickUpInput(
                $gatheredDetails['pickUpLocation'], $extraHoldFields, $pickup
            )) {
                $this->flashMessenger()->addMessage('hold_invalid_pickup', 'error');
            } else {
                // If we made it this far, we're ready to place the hold;
                // if successful, we will redirect and can stop here.

                // Add Patron Data to Submitted Data
                $holdDetails = $gatheredDetails + ['patron' => $patron];

                // Attempt to place the hold:
                $function = (string)$checkHolds['function'];
                $results = $catalog->$function($holdDetails);

                // Success: Go to Display Holds
                if (isset($results['success']) && $results['success'] == true) {
                    $msg = [
                        'html' => true,
                        'msg' => 'hold_place_success_html',
                        'tokens' => [
                            '%%url%%' => $this->url()->fromRoute('myresearch-holds')
                        ],
                    ];
                    $this->flashMessenger()->addMessage($msg, 'success');
                    return $this->redirectToRecord('#top');
                } else {
                    // Failure: use flash messenger to display messages, stay on
                    // the current form.
                    if (isset($results['status'])) {
                        $this->flashMessenger()
                            ->addMessage($results['status'], 'error');
                    }
                    if (isset($results['sysMessage'])) {
                        $this->flashMessenger()
                            ->addMessage($results['sysMessage'], 'error');
                    }
                }
            }
        }

        // Find and format the default required date:
        $defaultRequired = $this->holds()->getDefaultRequiredDate(
            $checkHolds, $catalog, $patron, $gatheredDetails
        );
        $defaultRequired = $this->getServiceLocator()->get('VuFind\DateConverter')
            ->convertToDisplayDate("U", $defaultRequired);
        try {
            $defaultPickup
                = $catalog->getDefaultPickUpLocation($patron, $gatheredDetails);
        } catch (\Exception $e) {
            $defaultPickup = false;
        }
        try {
            $defaultRequestGroup = empty($requestGroups)
                ? false
                : $catalog->getDefaultRequestGroup($patron, $gatheredDetails);
        } catch (\Exception $e) {
            $defaultRequestGroup = false;
        }

        $requestGroupNeeded = in_array('requestGroup', $extraHoldFields)
            && !empty($requestGroups)
            && (empty($gatheredDetails['level'])
                || $gatheredDetails['level'] != 'copy');

        $view = $this->createViewModel(
            [
                'gatheredDetails' => $gatheredDetails,
                'pickup' => $pickup,
                'defaultPickup' => $defaultPickup,
                'homeLibrary' => $this->getUser()->home_library,
                'extraHoldFields' => $extraHoldFields,
                'defaultRequiredDate' => $defaultRequired,
                'requestGroups' => $requestGroups,
                'defaultRequestGroup' => $defaultRequestGroup,
                'requestGroupNeeded' => $requestGroupNeeded,
                'helpText' => isset($checkHolds['helpText'])
                    ? $checkHolds['helpText'] : null
            ]
        );
        $view->setTemplate('record/hold');
        return $view;
    }
}
