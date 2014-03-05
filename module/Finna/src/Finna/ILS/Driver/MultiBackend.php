<?php
/**
 * Multiple Backend Driver.
 *
 * PHP version 5
 *
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
 * @category VuFind
 * @package  ILSdrivers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_an_ils_driver Wiki
 */
namespace Finna\ILS\Driver;

use VuFind\Exception\ILS as ILSException,
    Zend\Session\Container as SessionContainer;

/**
 * Multiple Backend Driver.
 *
 * This driver allows to use multiple backends determined by a record id or
 * user id prefix (e.g. source.12345).
 *
 * @category VuFind
 * @package  ILSdrivers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_an_ils_driver Wiki
 */
class MultiBackend extends \VuFind\ILS\Driver\MultiBackend
{
    /**
     * Container for storing cached ILS data.
     *
     * @var SessionContainer
     */
    protected $session;
    
    /**
     * Initialize the driver.
     *
     * Validate configuration and perform all resource-intensive tasks needed to
     * make the driver active.
     *
     * @throws ILSException
     * @return void
     */
    public function init()
    {
        parent::init();
        
        // Establish a namespace in the session for persisting cached data
        $this->session = new SessionContainer('MultiBackend');
    }
    
    /**
     * Get the drivers (data source IDs) enabled in MultiBackend for login
     *
     * @return string[]
     */
    public function getLoginDrivers()
    {
        return isset($this->config['Login']['drivers'])
            ? $this->config['Login']['drivers']
            : array();
    }

    /**
     * Get the default driver (data source ID) for login
     *
     * @return string
     */
    public function getDefaultLoginDriver()
    {
        return isset($this->config['Login']['default_driver']) 
            ? $this->config['Login']['default_driver'] 
            : $this->config['General']['default_driver'];
    }

    /**
     * Get Status
     *
     * This is responsible for retrieving the status information of a certain
     * record.
     *
     * @param string $id The record id to retrieve the holdings for
     *
     * @return mixed     On success, an associative array with the following keys:
     * id, availability (boolean), status, location, reserve, callnumber;
     */
    public function getStatus($id)
    {
        return $this->getHolding($id);
    }

    /**
     * Get Statuses
     *
     * This is responsible for retrieving the status information for a
     * collection of records.
     *
     * @param array $ids The array of record ids to retrieve the status for
     *
     * @return mixed     An array of getStatus() return values
     */
    public function getStatuses($ids)
    {
        $items = array();
        foreach ($ids as $id) {
            $items[] = $this->getHolding($id);
        }
        return $items;
    }

    /**
     * Get Holding
     *
     * This is responsible for retrieving the holding information of a certain
     * record.
     *
     * @param string $id     The record id to retrieve the holdings for
     * @param array  $patron Patron data
     *
     * @return mixed     An associative array with the following keys:
     * id, availability (boolean), status, location, reserve, callnumber, duedate,
     * number, barcode.
     */
    public function getHolding($id, $patron = false)
    {
        $source = $this->getSource($id);
        $driver = $this->getDriver($source);
        if ($driver) {
            $holdings = $driver->getHolding(
                $this->getLocalId($id), 
                $patron ? $this->stripIdPrefixes($patron, $source) : false
            );
            if ($holdings) {
                return $this->addIdPrefixes($holdings, $source);
            }
        }
        return array();
    }

    /**
     * Get Purchase History
     *
     * This is responsible for retrieving the acquisitions history data for the
     * specific record (usually recently received issues of a serial).
     *
     * @param string $id The record id to retrieve the info for
     *
     * @return mixed     An array with the acquisitions data.
     */
    public function getPurchaseHistory($id)
    {
        $source = $this->getSource($id);
        $driver = $this->getDriver($source);
        if ($driver) {
            return $driver->getPurchaseHistory($this->getLocalId($id));
        }
        return array();
    }

    /**
     * Get New Items
     *
     * Retrieve the IDs of items recently added to the catalog.
     *
     * @param int $page    Page number of results to retrieve (counting starts at 1)
     * @param int $limit   The size of each page of results to retrieve
     * @param int $daysOld The maximum age of records to retrieve in days (max. 30)
     * @param int $fundId  optional fund ID to use for limiting results (use a value
     * returned by getFunds, or exclude for no limit); note that "fund" may be a
     * misnomer - if funds are not an appropriate way to limit your new item
     * results, you can return a different set of values from getFunds. The
     * important thing is that this parameter supports an ID returned by getFunds,
     * whatever that may mean.
     *
     * @return array       Associative array with 'count' and 'results' keys
     */
    public function getNewItems($page, $limit, $daysOld, $fundId = null)
    {
        $driver = $this->getDriver($this->config['General']['defaultDriver']);
        if ($driver) {
            return $driver->getNewItems($page, $limit, $daysOld, $fundId);
        }
        return array();
    }

    /**
     * Find Reserves
     *
     * Obtain information on course reserves.
     *
     * @param string $course ID from getCourses (empty string to match all)
     * @param string $inst   ID from getInstructors (empty string to match all)
     * @param string $dept   ID from getDepartments (empty string to match all)
     *
     * @return mixed An array of associative arrays representing reserve items
     */
    public function findReserves($course, $inst, $dept)
    {
        $driver = $this->getDriver($this->config['General']['defaultDriver']);
        if ($driver) {
            return $driver->findReserves($course, $inst, $dept);
        }
        return array();
    }

    /**
     * Get Patron Profile
     *
     * This is responsible for retrieving the profile for a specific patron.
     *
     * @param array $user The patron array
     *
     * @return mixed      Array of the patron's profile data
     */
    public function getMyProfile($user)
    {
        $source = $this->getSource($user['cat_username']);
        $driver = $this->getDriver($source);
        if ($driver) {
            $profile = $driver->getMyProfile($this->stripIdPrefixes($user, $source));
            return $this->addIdPrefixes($profile, $source);
        }
        return array();
    }

    /**
     * Patron Login
     *
     * This is responsible for authenticating a patron against the catalog.
     *
     * @param string $username The patron user id or barcode
     * @param string $password The patron password
     *
     * @return mixed           Associative array of patron info on successful login,
     * null on unsuccessful login.
     */
    public function patronLogin($username, $password)
    {
        $hash = md5($username . $password);
        if (isset($this->session->logins[$hash])) {
            return unserialize($this->session->logins[$hash]);
        }
        $source = $this->getSource($username);
        if (!$source) {
            $source = $this->getDefaultLoginDriver();
        }
        $driver = $this->getDriver($source);
        if ($driver) {
            $patron = $driver->patronLogin($this->getLocalId($username), $password);
            $patron = $this->addIdPrefixes($patron, $source);
            if (!isset($this->session->logins)) {
                $this->session->logins = array();
            }
            $this->session->logins[$hash] = serialize($patron);
            return $patron;
        }
        throw new ILSException('No suitable backend driver found');
    }

    /**
     * Get Patron Transactions
     *
     * This is responsible for retrieving all transactions (i.e. checked out items)
     * by a specific patron.
     *
     * @param array $user The patron array from patronLogin
     *
     * @return mixed      Array of the patron's transactions
     */
    public function getMyTransactions($user)
    {
        $source = $this->getSource($user['cat_username']);
        $driver = $this->getDriver($source);
        if ($driver) {
            $transactions = $driver->getMyTransactions(
                $this->stripIdPrefixes($user, $source)
            );
            $transactions = $this->addIdPrefixes($transactions, $source);
            return $transactions;
        }
        throw new ILSException('No suitable backend driver found');
    }

    /**
     * Get Renew Details
     *
     * In order to renew an item, the ILS requires information on the item and
     * patron. This function returns the information as a string which is then used
     * as submitted form data in checkedOut.php. This value is then extracted by
     * the RenewMyItems function.
     *
     * @param array $checkoutDetails An array of item data
     *
     * @return string Data for use in a form field
     */
    public function getRenewDetails($checkoutDetails)
    {
        $source = $this->getSource($checkoutDetails['id']);
        $driver = $this->getDriver($source);
        if ($driver) {
            $details = $driver->getRenewDetails(
                $this->stripIdPrefixes($checkoutDetails, $source)
            );
            return $this->addIdPrefixes($details, $source);
        }
        throw new ILSException('No suitable backend driver found');
    }

    /**
     * Renew My Items
     *
     * Function for attempting to renew a patron's items. The data in
     * $renewDetails['details'] is determined by getRenewDetails().
     *
     * @param array $renewDetails An array of data required for renewing items
     * including the Patron ID and an array of renewal IDS
     *
     * @return array              An array of renewal information keyed by item ID
     */
    public function renewMyItems($renewDetails)
    {
        $source = $this->getSource($renewDetails['patron']['id']);
        $driver = $this->getDriver($source);
        if ($driver) {
            $details = $driver->renewMyItems(
                $this->stripIdPrefixes($renewDetails, $source)
            );
            return $this->addIdPrefixes($details, $source);
        }
        throw new ILSException('No suitable backend driver found');
    }

    /**
     * Get Patron Fines
     *
     * This is responsible for retrieving all fines by a specific patron.
     *
     * @param array $user The patron array from patronLogin
     *
     * @return mixed      Array of the patron's fines
     */
    public function getMyFines($user)
    {
        $source = $this->getSource($user['cat_username']);
        $driver = $this->getDriver($source);
        if ($driver) {
            $fines = $driver->getMyFines($this->stripIdPrefixes($user, $source));
            return $this->addIdPrefixes($fines, $source);
        }
        throw new ILSException('No suitable backend driver found');
    }

    /**
     * Get Patron Holds
     *
     * This is responsible for retrieving all holds by a specific patron.
     *
     * @param array $user The patron array from patronLogin
     *
     * @return mixed      Array of the patron's holds
     */
    public function getMyHolds($user)
    {
        $source = $this->getSource($user['cat_username']);
        $driver = $this->getDriver($source);
        if ($driver) {
            $holds = $driver->getMyHolds($this->stripIdPrefixes($user, $source));
            $holds = $this->addIdPrefixes(
                $holds, $source, array('id', 'item_id', 'cat_username')
            );
            return $holds;
        }
        throw new ILSException('No suitable backend driver found');
    }

    /**
     * Get Patron Call Slips
     *
     * This is responsible for retrieving all call slips by a specific patron.
     *
     * @param array $user The patron array from patronLogin
     *
     * @return mixed      Array of the patron's holds
     */
    public function getMyStorageRetrievalRequests($user)
    {
        $source = $this->getSource($user['cat_username']);
        $driver = $this->getDriver($source);
        if ($driver) {
            if (method_exists($driver, 'getMyStorageRetrievalRequests')) {
                $holds = $driver->getMyStorageRetrievalRequests(
                    $this->stripIdPrefixes($user, $source)
                );
                return $this->addIdPrefixes($holds, $source);
            }
            return array();
        }
        throw new ILSException('No suitable backend driver found');
    }

    /**
     * checkRequestIsValid
     *
     * This is responsible for determining if an item is requestable
     *
     * @param string $id     The Bib ID
     * @param array  $data   An Array of item data
     * @param patron $patron An array of patron data
     *
     * @return string True if request is valid, false if not
     */
    public function checkRequestIsValid($id, $data, $patron)
    {
        if (!isset($patron['cat_username'])) {
            return false;
        }
        $source = $this->getSource($patron['cat_username']);
        $driver = $this->getDriver($source);
        if ($driver) {
            if ($this->getSource($id) != $source) {
                return false;
            }
            return $driver->checkRequestIsValid(
                $this->stripIdPrefixes($id, $source),
                $this->stripIdPrefixes($data, $source), 
                $this->stripIdPrefixes($patron, $source)
            );
        }
        return false;
    }

    /**
     * checkStorageRetrievalRequestIsValid
     *
     * This is responsible for determining if an item is requestable
     *
     * @param string $id     The Bib ID
     * @param array  $data   An Array of item data
     * @param patron $patron An array of patron data
     *
     * @return string True if request is valid, false if not
     */
    public function checkStorageRetrievalRequestIsValid($id, $data, $patron)
    {
        $source = $this->getSource($patron['cat_username']);
        $driver = $this->getDriver($source);
        if ($driver) {
            if ($this->getSource($id) != $source) {
                return false;
            }
            return $driver->checkStorageRetrievalRequestIsValid(
                $this->stripIdPrefixes($id, $source),
                $this->stripIdPrefixes($data, $source),
                $this->stripIdPrefixes($patron, $source)
            );
        }
        return false;
    }

    /**
     * Get Pick Up Locations
     *
     * This is responsible get a list of valid library locations for holds / recall
     * retrieval
     *
     * @param array $patron      Patron information returned by the patronLogin
     * method.
     * @param array $holdDetails Optional array, only passed in when getting a list
     * in the context of placing a hold; contains most of the same values passed to
     * placeHold, minus the patron data.  May be used to limit the pickup options
     * or may be ignored.  The driver must not add new options to the return array
     * based on this data or other areas of VuFind may behave incorrectly.
     *
     * @return array        An array of associative arrays with locationID and
     * locationDisplay keys
     */
    public function getPickUpLocations($patron = false, $holdDetails = null)
    {
        $source = $this->getSource($patron['cat_username']);
        $driver = $this->getDriver($source);
        if ($driver) {
            if ($holdDetails) {
                if ($this->getSource($holdDetails['id']) != $source) {
                    // Return empty array since the sources don't match
                    return array();
                }
            }
            $locations = $driver->getPickUpLocations(
                $this->stripIdPrefixes($patron, $source),
                $this->stripIdPrefixes($holdDetails, $source)
            );
            return $this->addIdPrefixes($locations, $source);
        }
        throw new ILSException('No suitable backend driver found');
    }

    /**
     * Get request groups
     *
     * @param integer $bibId    BIB ID
     * @param array   $patronId Patron information returned by the patronLogin
     * method.
     *
     * @return array  An array of associative arrays with requestGroupId and
     * name keys
     */
    public function getRequestGroups($bibId, $patronId)
    {
        $source = $this->getSource($bibId);
        $driver = $this->getDriver($source);
        if ($driver) {
            if ($this->getSource($patronId) != $source) {
                // Return empty array since the sources don't match
                return array();
            }
            $groups = $driver->getRequestGroups(
                $this->stripIdPrefixes($bibId, $source),
                $this->stripIdPrefixes($patronId, $source)
            );
            return $groups;
        }
        throw new ILSException('No suitable backend driver found');
    }

    /**
     * Get Default Pick Up Location
     *
     * Returns the default pick up location
     *
     * @param array $patron      Patron information returned by the patronLogin
     * method.
     * @param array $holdDetails Optional array, only passed in when getting a list
     * in the context of placing a hold; contains most of the same values passed to
     * placeHold, minus the patron data.  May be used to limit the pickup options
     * or may be ignored.
     *
     * @return string A location ID
     */
    public function getDefaultPickUpLocation($patron = false, $holdDetails = null)
    {
        $source = $this->getSource($patron['cat_username']);
        $driver = $this->getDriver($source);
        if ($driver) {
            if ($holdDetails) {
                if ($this->getSource($holdDetails['id']) != $source) {
                    // Return false since the sources don't match
                    return false;
                }
            }
            $locations = $driver->getDefaultPickUpLocation(
                $this->stripIdPrefixes($patron, $source),
                $this->stripIdPrefixes($holdDetails, $source)
            );
            return $this->addIdPrefixes($locations, $source);
        }
        throw new ILSException('No suitable backend driver found');
    }

    /**
     * Get Default Request Group
     *
     * Returns the default request group
     *
     * @param array $patron      Patron information returned by the patronLogin
     * method.
     * @param array $holdDetails Optional array, only passed in when getting a list
     * in the context of placing a hold; contains most of the same values passed to
     * placeHold, minus the patron data.  May be used to limit the request group 
     * options or may be ignored.
     *
     * @return string A location ID
     */
    public function getDefaultRequestGroup($patron = false, $holdDetails = null)
    {
        $source = $this->getSource($patron['cat_username']);
        $driver = $this->getDriver($source);
        if ($driver) {
            if ($holdDetails) {
                if ($this->getSource($holdDetails['id']) != $source) {
                    // Return false since the sources don't match
                    return false;
                }
            }
            $locations = $driver->getDefaultRequestGroup(
                $this->stripIdPrefixes($patron, $source),
                $this->stripIdPrefixes($holdDetails, $source)
            );
            return $this->addIdPrefixes($locations, $source);
        }
        throw new ILSException('No suitable backend driver found');
    }

    /**
     * Place Hold
     *
     * Attempts to place a hold or recall on a particular item and returns
     * an array with result details
     *
     * @param array $holdDetails An array of item and patron data
     *
     * @return mixed An array of data on the request including
     * whether or not it was successful and a system message (if available)
     */
    public function placeHold($holdDetails)
    {
        $source = $this->getSource($holdDetails['patron']['cat_username']);
        $driver = $this->getDriver($source);
        if ($driver) {
            if ($this->getSource($holdDetails['id']) != $source) {
                return array(
                    "success" => false,
                    "sysMessage" => 'hold_wrong_user_institution'
                );
            }
            $holdDetails = $this->stripIdPrefixes($holdDetails, $source);
            return $driver->placeHold($holdDetails);
        }
        throw new ILSException('No suitable backend driver found');
    }

    /**
     * Cancel Holds
     *
     * Attempts to Cancel a hold or recall on a particular item. The
     * data in $cancelDetails['details'] is determined by getCancelHoldDetails().
     *
     * @param array $cancelDetails An array of item and patron data
     *
     * @return array               An array of data on each request including
     * whether or not it was successful and a system message (if available)
     */
    public function cancelHolds($cancelDetails)
    {
        $source = $this->getSource($cancelDetails['patron']['cat_username']);
        $driver = $this->getDriver($source);
        if ($driver) {
            return $driver->cancelHolds(
                $this->stripIdPrefixes($cancelDetails, $source)
            );
        }
        throw new ILSException('No suitable backend driver found');
    }

    /**
     * Get Cancel Hold Details
     *
     * In order to cancel a hold, the ILS requires some information on the hold.
     * This function returns the required information, which is then submitted
     * as form data in Hold.php. This value is then extracted by the CancelHolds
     * function.
     *
     * @param array $holdDetails An array of item data
     *
     * @return string Data for use in a form field
     */
    public function getCancelHoldDetails($holdDetails)
    {
        $source = $this->getSource(
            $holdDetails['id'] ? $holdDetails['id'] : $holdDetails['item_id']
        );
        $driver = $this->getDriver($source);
        if ($driver) {
            $holdDetails = $this->stripIdPrefixes($holdDetails, $source);
            return $driver->getCancelHoldDetails($holdDetails);
        }
        throw new ILSException('No suitable backend driver found');
    }

    /**
     * Place Call Slip Request
     *
     * Attempts to place a call slip request on a particular item and returns
     * an array with result details
     *
     * @param array $details An array of item and patron data
     *
     * @return mixed An array of data on the request including
     * whether or not it was successful and a system message (if available)
     */
    public function placeStorageRetrievalRequest($details)
    {
        $source = $this->getSource($details['patron']['cat_username']);
        $driver = $this->getDriver($source);
        if ($driver) {
            if ($this->getSource($details['id']) != $source) {
                return array(
                    "success" => false,
                    "sysMessage" => 'hold_wrong_user_institution'
                );
            }
            $details = $this->stripIdPrefixes($details, $source);
            return $driver->placeStorageRetrievalRequest($details);
        }
        throw new ILSException('No suitable backend driver found');
    }

    /**
     * Cancel Call Slips
     *
     * Attempts to Cancel a call slip on a particular item. The
     * data in $cancelDetails['details'] is determined by 
     * getCancelStorageRetrievalRequestDetails().
     *
     * @param array $cancelDetails An array of item and patron data
     *
     * @return array               An array of data on each request including
     * whether or not it was successful and a system message (if available)
     */
    public function cancelStorageRetrievalRequests($cancelDetails)
    {
        $source = $this->getSource($cancelDetails['patron']['cat_username']);
        $driver = $this->getDriver($source);
        if ($driver) {
            return $driver->cancelStorageRetrievalRequests(
                $this->stripIdPrefixes($cancelDetails, $source)
            );
        }
        throw new ILSException('No suitable backend driver found');
    }

    /**
     * Get Cancel Call Slip Details
     *
     * In order to cancel a call slip, the ILS requires some information on it.
     * This function returns the required information, which is then submitted
     * as form data. This value is then extracted by the
     * CancelStorageRetrievalRequests function.
     *
     * @param array $details An array of item data
     * @param array $patron  Patron from patronLogin
     *
     * @return string Data for use in a form field
     */
    public function getCancelStorageRetrievalRequestDetails($details, $patron)
    {
        $source = $this->getSource($details['id']);
        $driver = $this->getDriver($source);
        if ($driver) {
            $details = $this->stripIdPrefixes($details, $source);
            return $driver->getCancelStorageRetrievalRequestDetails($details);
        }
        throw new ILSException('No suitable backend driver found');
    }

    /**
     * Change Password
     *
     * Attempts to change patron password (PIN code)
     *
     * @param array $details An array of patron id and old and new password
     *
     * @return mixed An array of data on the request including
     * whether or not it was successful and a system message (if available)
     */
    public function changePassword($details)
    {
        $source = $this->getSource($details['patron']['cat_username']);
        $driver = $this->getDriver($source);
        if ($driver) {
            $details = $this->stripIdPrefixes($details, $source);
            return $driver->changePassword($details);
        }
        throw new ILSException('No suitable backend driver found');
    }

    /**
     * checkILLRequestIsValid
     *
     * This is responsible for determining if an item is requestable
     *
     * @param string $id     The Bib ID
     * @param array  $data   An Array of item data
     * @param patron $patron An array of patron data
     *
     * @return string True if request is valid, false if not
     */
    public function checkILLRequestIsValid($id, $data, $patron)
    {
        $source = $this->getSource($id);
        $driver = $this->getDriver($source);
        if ($driver && is_callable(array($driver, 'checkILLRequestIsValid'))) {
            // Patron is not stripped so that the correct library can be determined
            return $driver->checkILLRequestIsValid(
                $this->stripIdPrefixes($id, $source),
                $this->stripIdPrefixes($data, $source),
                $patron
            );
        }
        throw new ILSException('No suitable backend driver found');
    }

    /**
     * Get ILL Pickup Libraries
     *
     * This is responsible for getting information on the possible pickup libraries
     *
     * @param string $id     Record ID
     * @param array  $patron Patron
     *
     * @return bool|array False if request not allowed, or an array of associative
     * arrays with libraries.
     */
    public function getILLPickupLibraries($id, $patron)
    {
        $source = $this->getSource($id);
        $driver = $this->getDriver($source);
        if ($driver && is_callable(array($driver, 'getILLPickupLibraries'))) {
            // Patron is not stripped so that the correct library can be determined
            return $driver->getILLPickupLibraries(
                $this->stripIdPrefixes($id, $source, array('id')),
                $patron
            );
        }
    }

    /**
     * Get ILL Pickup Locations
     *
     * This is responsible for getting a list of possible pickup locations for a
     * library
     *
     * @param string $id        Record ID
     * @param string $pickupLib Pickup library ID
     * @param array  $patron    Patron
     *
     * @return bool|array False if request not allowed, or an array of
     * locations.
     */
    public function getILLPickupLocations($id, $pickupLib, $patron)
    {
        $source = $this->getSource($id);
        $driver = $this->getDriver($source);
        if ($driver && is_callable(array($driver, 'getILLPickupLocations'))) {
            // Patron is not stripped so that the correct library can be determined
            return $driver->getILLPickupLocations(
                $this->stripIdPrefixes($id, $source, array('id')),
                $pickupLib,
                $patron
            );
        }
    }

    /**
     * Place ILL Request
     *
     * Attempts to place an ILL request on a particular item and returns
     * an array with result details or a PEAR error on failure of support classes
     *
     * @param array $details An array of item and patron data
     *
     * @return mixed An array of data on the request including
     * whether or not it was successful and a system message (if available)
     * @access public
     */
    public function placeILLRequest($details)
    {
        $source = $this->getSource($details['id']);
        $driver = $this->getDriver($source);
        if ($driver && is_callable(array($driver, 'placeILLRequest'))) {
            $details = $this->stripIdPrefixes($details, $source, array('id'));
            return $driver->placeILLRequest($details);
        }
        throw new ILSException('No suitable backend driver found');
    }

    /**
     * Get Patron ILL Requests
     *
     * This is responsible for retrieving all ILL Requests by a specific patron.
     *
     * @param array $user The patron array from patronLogin
     *
     * @return mixed      Array of the patron's holds
     */
    public function getMyILLRequests($user)
    {
        $source = $this->getSource($user['cat_username']);
        $driver = $this->getDriver($source);
        if ($driver) {
            $requests = $driver->getMyILLRequests(
                $this->stripIdPrefixes($user, $source)
            );
            $requests = $this->addIdPrefixes(
                $requests, $source, array('id', 'item_id', 'cat_username')
            );
            return $requests;
        }
        throw new ILSException('No suitable backend driver found');
    }

    /**
     * Cancel ILL Requests
     *
     * Attempts to Cancel an ILL request on a particular item. The
     * data in $cancelDetails['details'] is determined by
     * getCancelILLRequestDetails().
     *
     * @param array $cancelDetails An array of item and patron data
     *
     * @return array               An array of data on each request including
     * whether or not it was successful and a system message (if available)
     */
    public function cancelILLRequests($cancelDetails)
    {
        $source = $this->getSource($cancelDetails['patron']['cat_username']);
        $driver = $this->getDriver($source);
        if ($driver) {
            return $driver->cancelILLRequests(
                $this->stripIdPrefixes($cancelDetails, $source)
            );
        }
        throw new ILSException('No suitable backend driver found');
    }

    /**
     * Get Cancel ILL Request Details
     *
     * In order to cancel an ILL request, the ILS requires some information on the
     * request. This function returns the required information, which is then
     * submitted as form data. This value is then extracted by the CancelILLRequests
     * function.
     *
     * @param array $details An array of item data
     *
     * @return string Data for use in a form field
     */
    public function getCancelILLRequestDetails($details)
    {
        $source = $this->getSource(
            $details['id'] ? $details['id'] : $details['item_id']
        );
        $driver = $this->getDriver($source);
        if ($driver) {
            $details = $this->stripIdPrefixes($details, $source);
            return $driver->getCancelILLRequestDetails($details);
        }
        throw new ILSException('No suitable backend driver found');
    }

    /**
     * Function which specifies renew, hold and cancel settings.
     *
     * @param string $function The name of the feature to be checked
     * @param string $id       Optional record id
     *
     * @return array An array with key-value pairs.
     */
    public function getConfig($function, $id = null)
    {
        $patron = null;

        $source = null;
        if ($id) {
            $source = $this->getSource($id);
        }
        if (!$source) {
            $parentLocator = $this->getServiceLocator()->getServiceLocator();
            $account = $parentLocator->get('VuFind\AuthManager');
            if ($account->isLoggedIn()) {
                $patron = $account->storedCatalogLogin();
                if ($patron && isset($patron['cat_username'])) {
                    $source = $this->getSource($patron['cat_username']);
                }
            }
        }

        $driver = $this->getDriver($source);

        // If we have resolved the needed driver, just getConfig and return.
        if ($driver && method_exists($driver, 'getConfig')) {
            return $driver->getConfig($function);
        }

        // If driver not available, return default values
        switch ($function) {
        case 'Holds':
            return array(
                'function' => 'placeHold',
                'HMACKeys' => 'id',
                'extraHoldFields' => 'requiredByDate:pickUpLocation',
                'defaultRequiredDate' => '1:0:0'
            );
        case 'cancelHolds':
            return array(
                'function' => 'cancelHolds',
                'HMACKeys' => 'id'
            );
        case 'Renewals':
        case 'StorageRetrievalRequests':
        case 'ILLRequests':
            return array();
        default:
            $this->error("MultiBackend: unhandled getConfig function: '$function'");
        }
        return array();
    }

    /**
     * Helper method to determine whether or not a certain method can be
     * called on this driver.  Required method for any smart drivers.
     *
     * @param string $method The name of the called method.
     * @param array  $params Array of passed parameters
     *
     * @return boolean  True if the method can be called with the given
     *                  parameters, false otherwise.
     */
    public function supportsMethod($method, $params)
    {
        // First we see if we can determine what instance the user is connected with
        $instance = $this->getInstanceFromParams($params);
        if ($instance) {
            $driverInst = $this->getUninitializedDriver($instance);
            return is_callable(array($driverInst, $method));
        }

        // Can't use a fallback driver
        return false;
    }

    /**
     * Default method -- pass along calls to the driver if available; return
     * false otherwise.  This allows custom functions to be implemented in
     * the driver without constant modification to the MultiBackend class.
     *
     * @param string $methodName The name of the called method.
     * @param array  $params     Array of passed parameters.
     *
     * @return mixed             Varies by method (false if undefined method)
     */
    public function __call($methodName, $params)
    {
        $called = false;
        // Try for the driver associated with the user
        $instName = $this->getInstanceFromParams($params);
        $funcReturn = $this->runIfPossible($instName, $methodName, $params, $called);
        if ($called) {
            return $funcReturn;
        }

        // Can't use a fallback driver
        return false;
    }
}