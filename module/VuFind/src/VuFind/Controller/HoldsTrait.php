<?php
/**
 * Holds trait (for subclasses of AbstractRecord)
 *
 * PHP version 7
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
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
namespace VuFind\Controller;

/**
 * Holds trait (for subclasses of AbstractRecord)
 *
 * @category VuFind
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
trait HoldsTrait
{
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
        $validRequest = $catalog->checkRequestIsValid(
            $driver->getUniqueID(), $gatheredDetails, $patron
        );
        if ((is_array($validRequest) && !$validRequest['valid']) || !$validRequest) {
            $this->flashMessenger()->addErrorMessage(
                is_array($validRequest)
                    ? $validRequest['status'] : 'hold_error_blocked'
            );
            return $this->redirectToRecord('#top');
        }

        // Send various values to the view so we can build the form:
        $requestGroups = $catalog->checkCapability(
            'getRequestGroups', [$driver->getUniqueID(), $patron, $gatheredDetails]
        ) ? $catalog->getRequestGroups(
            $driver->getUniqueID(), $patron, $gatheredDetails
        ) : [];
        $extraHoldFields = isset($checkHolds['extraHoldFields'])
            ? explode(":", $checkHolds['extraHoldFields']) : [];

        $requestGroupNeeded = in_array('requestGroup', $extraHoldFields)
            && !empty($requestGroups)
            && (empty($gatheredDetails['level'])
                || ($gatheredDetails['level'] != 'copy'
                    || count($requestGroups) > 1));

        $pickupDetails = $gatheredDetails;
        if (!$requestGroupNeeded && !empty($requestGroups)
            && count($requestGroups) == 1
        ) {
            // Request group selection is not required, but we have a single request
            // group, so make sure pickup locations match with the group
            $pickupDetails['requestGroupId'] = $requestGroups[0]['id'];
        }
        $pickup = $catalog->getPickUpLocations($patron, $pickupDetails);

        // Process form submissions if necessary:
        if (null !== $this->params()->fromPost('placeHold')) {
            // If the form contained a pickup location, request group, start date or
            // required by date, make sure they are valid:
            $validGroup = $this->holds()->validateRequestGroupInput(
                $gatheredDetails, $extraHoldFields, $requestGroups
            );
            $validPickup = $validGroup && $this->holds()->validatePickUpInput(
                $gatheredDetails['pickUpLocation'] ?? null,
                $extraHoldFields, $pickup
            );
            $dateValidationResults = $this->holds()->validateDates(
                $gatheredDetails['startDate'] ?? null,
                $gatheredDetails['requiredBy'] ?? null,
                $extraHoldFields
            );
            if (!$validGroup) {
                $this->flashMessenger()
                    ->addErrorMessage('hold_invalid_request_group');
            }
            if (!$validPickup) {
                $this->flashMessenger()->addErrorMessage('hold_invalid_pickup');
            }
            foreach ($dateValidationResults['errors'] as $msg) {
                $this->flashMessenger()->addErrorMessage($msg);
            }
            if ($validGroup && $validPickup && !$dateValidationResults['errors']) {
                // If we made it this far, we're ready to place the hold;
                // if successful, we will redirect and can stop here.

                // Pass start date to the driver only if it's in the future:
                if (!empty($gatheredDetails['startDate'])
                    && $dateValidationResults['startDateTS'] < strtotime('+1 day')
                ) {
                    $gatheredDetails['startDate'] = '';
                    $dateValidationResults['startDateTS'] = 0;
                }

                // Add patron data and converted dates to submitted data
                $holdDetails = $gatheredDetails + [
                    'patron' => $patron,
                    'startDateTS' => $dateValidationResults['startDateTS'],
                    'requiredByTS' => $dateValidationResults['requiredByTS'],
                ];

                // Attempt to place the hold:
                $function = (string)$checkHolds['function'];
                $results = $catalog->$function($holdDetails);

                // Success: Go to Display Holds
                if (isset($results['success']) && $results['success'] == true) {
                    $msg = [
                        'html' => true,
                        'msg' => 'hold_place_success_html',
                        'tokens' => [
                            '%%url%%' => $this->url()->fromRoute('holds-list')
                        ],
                    ];
                    $this->flashMessenger()->addMessage($msg, 'success');
                    if (!empty($results['warningMessage'])) {
                        $this->flashMessenger()
                            ->addWarningMessage($results['warningMessage']);
                    }
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

        // Set default start date to today:
        $dateConverter = $this->serviceLocator->get(\VuFind\Date\Converter::class);
        $defaultStartDate = $dateConverter->convertToDisplayDate('U', time());

        // Find and format the default required date:
        $defaultRequiredDate = $dateConverter->convertToDisplayDate(
            'U',
            $this->holds()->getDefaultRequiredDate(
                $checkHolds,
                $catalog,
                $patron,
                $gatheredDetails
            )
        );
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

        $config = $this->getConfig();
        $homeLibrary = ($config->Account->set_home_library ?? true)
            ? $this->getUser()->home_library : '';
        $helpText = $checkHolds['helpText'] ?? null;

        $view = $this->createViewModel(
            compact(
                'gatheredDetails', 'pickup', 'defaultPickup', 'homeLibrary',
                'extraHoldFields', 'defaultStartDate', 'defaultRequiredDate',
                'requestGroups', 'defaultRequestGroup', 'requestGroupNeeded',
                'helpText'
            )
        );
        $view->setTemplate('record/hold');
        return $view;
    }
}
