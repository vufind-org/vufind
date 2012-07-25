<?php
/**
 * Record Controller
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
use VuFind\Config\Reader as ConfigReader,
    VuFind\Connection\Manager as ConnectionManager;

/**
 * Record Controller
 *
 * @category VuFind2
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class RecordController extends AbstractRecord
{
    /**
     * init
     *
     * @return void
     */
    public function __construct()
    {
        // Call standard record controller initialization:
        parent::__construct();

        // Load default tab setting:
        $config = ConfigReader::getConfig();
        $this->defaultTab = isset($config->Site->defaultRecordTab)
            ? $config->Site->defaultRecordTab : 'Holdings';
    }

    /**
     * Action for dealing with blocked holds.
     *
     * @return void
     */
    public function blockedholdAction()
    {
        $this->flashMessenger()->setNamespace('error')
            ->addMessage('hold_error_blocked');
        return $this->redirectToRecord('#top');
    }

    /**
     * Action for dealing with holds.
     *
     * @return void
     */
    public function holdAction()
    {
        // If we're not supposed to be here, give up now!
        $catalog = ConnectionManager::connectToCatalog();
        $checkHolds = $catalog->checkFunction("Holds");
        if (!$checkHolds) {
            return $this->forward()->dispatch('Record', array('action' => 'Home'));
        }

        // Stop now if the user does not have valid catalog credentials available:
        if (!is_array($patron = $this->catalogLogin())) {
            return $patron;
        }

        // Do we have valid information?
        // Sets $this->logonURL and $this->gatheredDetails
        $gatheredDetails = $this->holds()->validateRequest($checkHolds['HMACKeys']);
        if (!$gatheredDetails) {
            return $this->redirectToRecord();
        }

        // Block invalid requests:
        $driver = $this->loadRecord();
        if (!$catalog->checkRequestIsValid(
            $driver->getUniqueID(), $gatheredDetails, $patron
        )) {
            return $this->blockedholdAction();
        }

        // Send various values to the view so we can build the form:
        $pickup = $catalog->getPickUpLocations($patron, $gatheredDetails);
        $extraHoldFields = isset($checkHolds['extraHoldFields'])
            ? explode(":", $checkHolds['extraHoldFields']) : array();

        // Process form submissions if necessary:
        if (!is_null($this->params()->fromPost('placeHold'))) {
            // If the form contained a pickup location, make sure that
            // the value has not been tampered with:
            if (!$this->holds()->validatePickUpInput(
                $gatheredDetails['pickUpLocation'], $extraHoldFields, $pickup
            )) {
                $this->flashMessenger()->setNamespace('error')
                    ->addMessage('error_inconsistent_parameters');
            } else {
                // If we made it this far, we're ready to place the hold;
                // if successful, we will redirect and can stop here.

                // Add Patron Data to Submitted Data
                $holdDetails = $gatheredDetails + array('patron' => $patron);

                // Attempt to place the hold:
                $function = (string)$checkHolds['function'];
                $results = $catalog->$function($holdDetails);

                // Success: Go to Display Holds
                if (isset($results['success']) && $results['success'] == true) {
                    $this->flashMessenger()->setNamespace('info')
                        ->addMessage('hold_place_success');
                    return $this->redirect()->toRoute('myresearch-holds');
                } else {
                    // Failure: use flash messenger to display messages, stay on
                    // the current form.
                    if (isset($results['status'])) {
                        $this->flashMessenger()->setNamespace('error')
                            ->addMessage($results['status']);
                    }
                    if (isset($results['sysMessage'])) {
                        $this->flashMessenger()->setNamespace('error')
                            ->addMessage($results['sysMessage']);
                    }
                }
            }
        }

        return $this->createViewModel(
            array(
                'gatheredDetails' => $gatheredDetails,
                'pickup' => $pickup,
                'defaultPickup' => $catalog->getDefaultPickUpLocation(
                    $patron, $gatheredDetails
                ),
                'homeLibrary' => $this->getUser()->home_library,
                'extraHoldFields' => $extraHoldFields,
                'defaultRequiredDate' => $this->holds()->getDefaultRequiredDate(
                    $checkHolds
                )
            )
        );
    }
}
