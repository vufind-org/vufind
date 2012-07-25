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
use VuFind\Config\Reader as ConfigReader;

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
        /* TODO
        // If we're not supposed to be here, give up now!
        $catalog = VF_Connection_Manager::connectToCatalog();
        $checkHolds = $catalog->checkFunction("Holds");
        if (!$checkHolds) {
            return $this->_forward('Home');
        }

        // Stop now if the user does not have valid catalog credentials available:
        if (!($patron = $this->catalogLogin())) {
            return;
        }

        // Do we have valid information?
        // Sets $this->logonURL and $this->gatheredDetails
        $gatheredDetails = $this->_helper->holds->validateRequest(
            $this->_request, $checkHolds['HMACKeys']
        );
        if (!$gatheredDetails) {
            return $this->redirectToRecord();
        }

        // Block invalid requests:
        $this->loadRecord();
        if (!$catalog->checkRequestIsValid(
            $this->view->driver->getUniqueID(), $gatheredDetails, $patron
        )) {
            return $this->blockedholdAction();
        }

        // Send various values to the view so we can build the form:
        $this->view->gatheredDetails = $gatheredDetails;
        $this->view->pickup = $catalog->getPickUpLocations(
            $patron, $gatheredDetails
        );
        $this->view->defaultPickup = $catalog->getDefaultPickUpLocation(
            $patron, $gatheredDetails
        );
        $this->view->homeLibrary
            = VF_Account_Manager::getInstance()->isLoggedIn()->home_library;
        $this->view->extraHoldFields = isset($checkHolds['extraHoldFields'])
            ? explode(":", $checkHolds['extraHoldFields']) : array();
        $this->view->defaultRequiredDate
            = $this->_helper->holds->getDefaultRequiredDate($checkHolds);

        // Process form submissions if necessary:
        if (!is_null($this->_request->getParam('placeHold'))) {
            // If the form contained a pickup location, make sure that
            // the value has not been tampered with:
            if (!$this->_helper->holds->validatePickUpInput(
                $gatheredDetails['pickUpLocation'], $this->view->extraHoldFields,
                $this->view->pickup
            )) {
                $this->_helper->flashMessenger->setNamespace('error')
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
                    $this->_helper->flashMessenger->setNamespace('info')
                        ->addMessage('hold_place_success');
                    return $this->_redirect('/MyResearch/Holds');
                } else {
                    // Failure: use flash messenger to display messages, stay on
                    // the current form.
                    if (isset($results['status'])) {
                        $this->_helper->flashMessenger->setNamespace('error')
                            ->addMessage($results['status']);
                    }
                    if (isset($results['sysMessage'])) {
                        $this->_helper->flashMessenger->setNamespace('error')
                            ->addMessage($results['sysMessage']);
                    }
                }
            }
        }
         */
    }
}
