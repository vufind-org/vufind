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
use VuFind\ILS\Connection;
use Zend\Mvc\Controller\Plugin\AbstractPlugin, Zend\Session\Container;

/**
 * Zend action helper to perform holds-related actions
 *
 * @category VuFind2
 * @package  Controller_Plugins
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class Holds extends AbstractPlugin
{
    /**
     * Session data
     *
     * @var Container
     */
    protected $session;

    /**
     * HMAC generator
     *
     * @var \VuFind\Crypt\HMAC
     */
    protected $hmac;

    /**
     * Constructor
     *
     * @param \VuFind\Crypt\HMAC $hmac HMAC generator
     */
    public function __construct(\VuFind\Crypt\HMAC $hmac)
    {
        $this->hmac = $hmac;
    }

    /**
     * Grab the Container object for storing helper-specific session
     * data.
     *
     * @return Container
     */
    protected function getSession()
    {
        if (!isset($this->session)) {
            $this->session = new Container('Holds_Helper');
        }
        return $this->session;
    }

    /**
     * Reset the array of valid IDs in the session (used for form submission
     * validation)
     *
     * @return void
     */
    public function resetValidation()
    {
        $this->getSession()->validIds = array();
    }

    /**
     * Add an ID to the validation array.
     *
     * @param string $id ID to remember
     *
     * @return void
     */
    public function rememberValidId($id)
    {
        // The session container doesn't allow modification of entries (as of
        // ZF2beta5 anyway), so we have to do this in a roundabout way.
        $existingArray = $this->getSession()->validIds;
        $existingArray[] = $id;
        $this->getSession()->validIds = $existingArray;
    }

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
            return array();
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
                        array(
                            'cancelAll' => 1,
                            'cancelAllIDS' => $params->fromPost('cancelAllIDS')
                        )
                    );
                } else {
                    return $this->getController()->confirm(
                        'hold_cancel_selected',
                        $this->getController()->url()->fromRoute('myresearch-holds'),
                        $this->getController()->url()->fromRoute('myresearch-holds'),
                        'confirm_hold_cancel_selected_text',
                        array(
                            'cancelSelected' => 1,
                            'cancelSelectedIDS' =>
                                $params->fromPost('cancelSelectedIDS')
                        )
                    );
                }
            }
            
            foreach ($details as $info) {
                // If the user input contains a value not found in the session
                // whitelist, something has been tampered with -- abort the process.
                if (!in_array($info, $this->getSession()->validIds)) {
                    $flashMsg->setNamespace('error')
                        ->addMessage('error_inconsistent_parameters');
                    return array();
                }
            }

            // Add Patron Data to Submitted Data
            $cancelResults = $catalog->cancelHolds(
                array('details' => $details, 'patron' => $patron)
            );
            if ($cancelResults == false) {
                $flashMsg->setNamespace('error')->addMessage('hold_cancel_fail');
            } else {
                if ($cancelResults['count'] > 0) {
                    // TODO : add a mechanism for inserting tokens into translated
                    // messages so we can avoid a double translation here.
                    $msg = $this->getController()
                        ->translate('hold_cancel_success_items');
                    $flashMsg->setNamespace('info')->addMessage(
                        $cancelResults['count'] . ' ' . $msg
                    );
                }
                return $cancelResults;
            }
        } else {
             $flashMsg->setNamespace('error')->addMessage('hold_empty_selection');
        }
        return array();
    }

    /**
     * Method for validating contents of a request; returns an array of
     * collected details if request is valid, otherwise returns false.
     *
     * @param array $linkData An array of keys to check
     *
     * @return boolean|array
     */
    public function validateRequest($linkData)
    {
        $controller = $this->getController();
        $params = $controller->params();

        $keyValueArray = array();
        foreach ($linkData as $details) {
            $keyValueArray[$details] = $params->fromQuery($details);
        }
        $hashKey = $this->hmac->generate($linkData, $keyValueArray);

        if ($params->fromQuery('hashKey') != $hashKey) {
            return false;
        }

        // Initialize gatheredDetails with any POST values we find; this will
        // allow us to repopulate the form with user-entered values if there
        // is an error.  However, it is important that we load the POST data
        // FIRST and then override it with GET values in order to ensure that
        // the user doesn't bypass the hashkey verification by manipulating POST
        // values.
        $gatheredDetails = $params->fromPost('gatheredDetails', array());

        // Make sure the bib ID is included, even if it's not loaded as part of
        // the validation loop below.
        $gatheredDetails['id'] = $params->fromRoute('id', $params->fromQuery('id'));

        // Get Values Passed from holdings.php
        $gatheredDetails = array_merge($gatheredDetails, $keyValueArray);

        return $gatheredDetails;
    }

    /**
     * Check if the user-provided pickup location is valid.
     *
     * @param string $pickup          User-specified pickup location
     * @param array  $extraHoldFields Hold form fields enabled by
     * configuration/driver
     * @param array  $pickUpLibs      Pickup library list from driver
     *
     * @return bool
     */
    public function validatePickUpInput($pickup, $extraHoldFields, $pickUpLibs)
    {
        // Not having to care for pickUpLocation is equivalent to having a valid one.
        if (!in_array('pickUpLocation', $extraHoldFields)) {
            return true;
        }

        // Check the valid pickup locations for a match against user input:
        return $this->validatePickUpLocation($pickup, $pickUpLibs);
    }

    /**
     * Check if the provided pickup location is valid.
     *
     * @param string $location   Location to check
     * @param array  $pickUpLibs Pickup locations list from driver
     *
     * @return bool
     */
    public function validatePickUpLocation($location, $pickUpLibs)
    {
        foreach ($pickUpLibs as $lib) {
            if ($location == $lib['locationID']) {
                return true;
            }
        }

        // If we got this far, something is wrong!
         return false;
    }

    /**
     * Getting a default required date based on hold settings.
     *
     * @param array      $checkHolds Hold settings returned by the ILS driver's
     * checkFunction method.
     * @param Connection $catalog    ILS connection (optional)
     * @param array      $patron     Patron details (optional)
     * @param array      $holdInfo   Hold details (optional)
     *
     * @return int A timestamp representing the default required date
     */
    public function getDefaultRequiredDate($checkHolds, $catalog = null,
        $patron = null, $holdInfo = null
    ) {
        // Load config:
        $dateArray = isset($checkHolds['defaultRequiredDate'])
             ? explode(":", $checkHolds['defaultRequiredDate'])
             : array(0, 1, 0);

        // Process special "driver" prefix and adjust default date
        // settings accordingly:
        if ($dateArray[0] == 'driver') {
            $useDriver = true;
            array_shift($dateArray);
            if (count($dateArray) < 3) {
                $dateArray = array(0, 1, 0);
            }
        } else {
            $useDriver = false;
        }

        // If the driver setting is active, try it out:
        if ($useDriver && $catalog
            && $catalog->checkCapability('getHoldDefaultRequiredDate')
        ) {
            $result = $catalog->getHoldDefaultRequiredDate($patron, $holdInfo);
            if (!empty($result)) {
                return $result;
            }
        }

        // If the driver setting is off or the driver didn't work, use the
        // standard relative date mechanism:
        return $this->getDateFromArray($dateArray);
    }

    /**
     * Support method for getDefaultRequiredDate() -- generate a date based
     * on a days/months/years offset array.
     *
     * @param array $dateArray 3-element array containing day/month/year offsets
     *
     * @return int A timestamp representing the default required date
     */
    protected function getDateFromArray($dateArray)
    {
        list($d, $m, $y) = $dateArray;
        return mktime(
            0, 0, 0, date('m')+$m, date('d')+$d, date('Y')+$y
        );
    }
}
