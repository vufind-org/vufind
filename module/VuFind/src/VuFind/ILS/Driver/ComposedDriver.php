<?php

/**
 * Composed Driver.
 *
 * PHP version 8
 *
 * Copyright (C) Hebis Verbundzentrale 2023.
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
 * @package  ILSdrivers
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */

namespace VuFind\ILS\Driver;

use VuFind\Date\DateException;
use VuFind\Exception\ILS as ILSException;

use function call_user_func_array;
use function count;
use function func_get_args;
use function in_array;

/**
 * Composed Driver.
 *
 * ILS Driver for VuFind to use multiple drivers for different tasks and
 * combine their results.
 *
 * @category VuFind
 * @package  ILSdrivers
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
class ComposedDriver extends AbstractMultiDriver
{
    /**
     * Name of the main driver
     *
     * @var string
     */
    protected $mainDriver;

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
        if (!($this->mainDriver = $this->config['General']['main_driver'] ?? false)) {
            throw new ILSException('Main driver needs to be set.');
        }
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
        return $this->defaultCall('cancelHolds', func_get_args());
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
        return $this->defaultCall('cancelILLRequests', func_get_args());
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
        return $this->defaultCall('cancelStorageRetrievalRequests', func_get_args());
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
        return $this->defaultCall('changePassword', func_get_args());
    }

    /**
     * Check whether an ILL request is valid
     *
     * This is responsible for determining if an item is requestable
     *
     * @param string $id     The Bib ID
     * @param array  $data   An Array of item data
     * @param array  $patron An array of patron data
     *
     * @return mixed An array of data on the request including
     * whether or not it is valid and a status message. Alternatively a boolean
     * true if request is valid, false if not.
     */
    public function checkILLRequestIsValid($id, $data, $patron)
    {
        return $this->defaultCall('checkILLRequestIsValid', func_get_args());
    }

    /**
     * Check whether a hold or recall request is valid
     *
     * This is responsible for determining if an item is requestable
     *
     * @param string $id     The Bib ID
     * @param array  $data   An Array of item data
     * @param array  $patron An array of patron data
     *
     * @return mixed An array of data on the request including
     * whether or not it is valid and a status message. Alternatively a boolean
     * true if request is valid, false if not.
     */
    public function checkRequestIsValid($id, $data, $patron)
    {
        return $this->defaultCall('checkRequestIsValid', func_get_args());
    }

    /**
     * Check whether a storage retrieval request is valid
     *
     * This is responsible for determining if an item is requestable
     *
     * @param string $id     The Bib ID
     * @param array  $data   An Array of item data
     * @param array  $patron An array of patron data
     *
     * @return mixed An array of data on the request including
     * whether or not it is valid and a status message. Alternatively a boolean
     * true if request is valid, false if not.
     */
    public function checkStorageRetrievalRequestIsValid($id, $data, $patron)
    {
        return $this->defaultCall('checkStorageRetrievalRequestIsValid', func_get_args());
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
        return $this->defaultCall('findReserves', func_get_args());
    }

    /**
     * Check whether the patron has any blocks on their account.
     *
     * @param array $patron Patron data from patronLogin().
     *
     * @return mixed A boolean false if no blocks are in place and an array
     * of block reasons if blocks are in place
     */
    public function getAccountBlocks($patron)
    {
        return $this->defaultCall('getAccountBlocks', func_get_args());
    }

    /**
     * Get Cancel Hold Details
     *
     * In order to cancel a hold, the ILS requires some information on the hold.
     * This function returns the required information, which is then submitted
     * as form data in Hold.php. This value is then extracted by the CancelHolds
     * function.
     *
     * @param array $hold   A single hold array from getMyHolds
     * @param array $patron Patron information from patronLogin
     *
     * @return string Data for use in a form field
     */
    public function getCancelHoldDetails($hold, $patron = [])
    {
        return $this->defaultCall('getCancelHoldDetails', func_get_args());
    }

    /**
     * Get Cancel Hold Link
     *
     * @param array $holdDetails Hold Details
     * @param array $patron      Patron
     *
     * @return string URL to native OPAC
     */
    public function getCancelHoldLink($holdDetails, $patron)
    {
        return $this->defaultCall('getCancelHoldLink', func_get_args());
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
        return $this->defaultCall('getCancelILLRequestDetails', func_get_args());
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
     *
     * @return string Data for use in a form field
     */
    public function getCancelStorageRetrievalRequestDetails($details)
    {
        return $this->defaultCall('getCancelStorageRetrievalRequestDetails', func_get_args());
    }

    /**
     * Function which specifies renew, hold and cancel settings.
     *
     * @param string $function The name of the feature to be checked
     * @param array  $params   Optional feature-specific parameters (array)
     *
     * @return array An array with key-value pairs.
     */
    public function getConfig($function, $params = null)
    {
        return $this->defaultCall('getConfig', func_get_args());
    }

    /**
     * Get Consortial Holdings
     *
     * This is responsible for retrieving the holding information of a certain
     * consortial record.
     *
     * @param string $id     The record id to retrieve the holdings for
     * @param array  $patron Patron data
     * @param array  $ids    The (consortial) source records for the record id
     *
     * @return array         On success, an associative array with the following
     *  keys: id, availability (boolean), status, location, reserve, callnumber,
     *  duedate, number, barcode.
     * @throws ILSException
     * @throws DateException
     */
    public function getConsortialHoldings($id, $patron, $ids)
    {
        return $this->combineArraysOfAssociativeArrays(
            'getConsortialHoldings',
            func_get_args(),
            ['holdings', 'electronic_holdings']
        );
    }

    /**
     * Get Courses
     *
     * Obtain a list of courses for use in limiting the reserves list.
     *
     * @return array An associative array with key = ID, value = name.
     */
    public function getCourses()
    {
        return $this->defaultCall('getCourses', func_get_args());
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
     * placeHold, minus the patron data. May be used to limit the pickup options
     * or may be ignored.
     *
     * @return string A location ID
     */
    public function getDefaultPickUpLocation($patron = false, $holdDetails = null)
    {
        return $this->defaultCall('getDefaultPickUpLocation', func_get_args());
    }

    /**
     * Get Departments
     *
     * Obtain a list of departments for use in limiting the reserves list.
     *
     * @return array An associative array with key = dept. ID, value = dept. name.
     */
    public function getDepartments()
    {
        return $this->defaultCall('getDepartments', func_get_args());
    }

    /**
     * Get Funds
     *
     * Return a list of funds which may be used to limit the getNewItems list.
     *
     * @throws ILSException
     * @return array An associative array with key = fund ID, value = fund name.
     */
    public function getFunds()
    {
        return $this->defaultCall('getFunds', func_get_args());
    }

    /**
     * Get Default "Hold Required By" Date (as Unix timestamp) or null if unsupported
     *
     * @param array $patron   Patron information returned by the patronLogin method.
     * @param array $holdInfo Contains most of the same values passed to
     * placeHold, minus the patron data.
     *
     * @return int|null
     */
    public function getHoldDefaultRequiredDate($patron, $holdInfo)
    {
        return $this->defaultCall('getHoldDefaultRequiredDate', func_get_args());
    }

    /**
     * Get Holding
     *
     * This is responsible for retrieving the holding information of a certain
     * record.
     *
     * @param string $id      The record id to retrieve the holdings for
     * @param array  $patron  Patron data
     * @param array  $options Extra options (not currently used)
     *
     * @return array         On success, an associative array with the following
     * keys: id, availability (boolean), status, location, reserve, callnumber,
     * duedate, number, barcode.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getHolding($id, array $patron = null, array $options = [])
    {
        return $this->combineArraysOfAssociativeArrays(
            'getHolding',
            func_get_args(),
            ['holdings', 'electronic_holdings']
        );
    }

    /**
     * Get Hold Link
     *
     * The goal for this method is to return a URL to a "place hold" web page on
     * the ILS OPAC. This is used for ILSs that do not support an API or method
     * to place Holds.
     *
     * @param string $id      The id of the bib record
     * @param array  $details Item details from getHoldings return array
     *
     * @return string         URL to ILS's OPAC's place hold screen.
     */
    public function getHoldLink($id, $details)
    {
        return $this->defaultCall('getHoldLink', func_get_args());
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
        return $this->defaultCall('getILLPickupLibraries', func_get_args());
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
        return $this->defaultCall('getILLPickupLocations', func_get_args());
    }

    /**
     * Get Instructors
     *
     * Obtain a list of instructors for use in limiting the reserves list.
     *
     * @return array An associative array with key = ID, value = name.
     */
    public function getInstructors()
    {
        return $this->defaultCall('getInstructors', func_get_args());
    }

    /**
     * Get Patron Fines
     *
     * This is responsible for retrieving all fines by a specific patron.
     *
     * @param array $patron The patron array from patronLogin
     *
     * @return mixed        Array of the patron's fines on success.
     */
    public function getMyFines($patron)
    {
        return $this->combineArraysOfAssociativeArrays('getMyFines', func_get_args());
    }

    /**
     * Get Patron Holds
     *
     * This is responsible for retrieving all holds by a specific patron.
     *
     * @param array $patron The patron array from patronLogin
     *
     * @return mixed      Array of the patron's holds
     */
    public function getMyHolds($patron)
    {
        return $this->combineArraysOfAssociativeArrays('getMyHolds', func_get_args());
    }

    /**
     * Get Patron ILL Requests
     *
     * This is responsible for retrieving all ILL Requests by a specific patron.
     *
     * @param array $patron The patron array from patronLogin
     *
     * @return mixed      Array of the patron's ILL requests
     */
    public function getMyILLRequests($patron)
    {
        return $this->combineArraysOfAssociativeArrays('getMyILLRequests', func_get_args());
    }

    /**
     * Get Patron Profile
     *
     * This is responsible for retrieving the profile for a specific patron.
     *
     * @param array $patron The patron array
     *
     * @return mixed Array of the patron's profile data
     */
    public function getMyProfile($patron)
    {
        return $this->mergeSingleArrayResults('getMyProfile', func_get_args());
    }

    /**
     * Get Patron Call Slips
     *
     * This is responsible for retrieving all call slips by a specific patron.
     *
     * @param array $patron The patron array from patronLogin
     *
     * @return mixed      Array of the patron's holds
     */
    public function getMyStorageRetrievalRequests($patron)
    {
        return $this->combineArraysOfAssociativeArrays('getMyStorageRetrievalRequests', func_get_args());
    }

    /**
     * Get Patron Loan History
     *
     * @param array $user   The patron array from patronLogin
     * @param array $params Parameters
     *
     * @throws DateException
     * @throws ILSException
     * @return array      Array of the patron's historic loans on success.
     */
    public function getMyTransactionHistory($user, $params = null)
    {
        return $this->combineArraysOfAssociativeArrays('getMyTransactionHistory', func_get_args());
    }

    /**
     * Get Patron Transactions
     *
     * This is responsible for retrieving all transactions (i.e. checked out items)
     * by a specific patron.
     *
     * @param array $patron The patron array from patronLogin
     *
     * @return mixed        Array of the patron's transactions on success.
     */
    public function getMyTransactions($patron)
    {
        return $this->combineArraysOfAssociativeArrays('getMyTransactions', func_get_args(), ['records']);
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
        return $this->defaultCall('getNewItems', func_get_args());
    }

    /**
     * Get Offline Mode
     *
     * This is responsible for returning the offline mode
     *
     * @return string "ils-offline" for systems where the main ILS is offline,
     * "ils-none" for systems which do not use an ILS
     */
    public function getOfflineMode()
    {
        return $this->defaultCall('getOfflineMode', func_get_args());
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
     * in the context of placing or editing a hold. When placing a hold, it contains
     * most of the same values passed to placeHold, minus the patron data. When
     * editing a hold it contains all the hold information returned by getMyHolds.
     * May be used to limit the pickup options or may be ignored. The driver must
     * not add new options to the return array based on this data or other areas of
     * VuFind may behave incorrectly.
     *
     * @return array        An array of associative arrays with locationID and
     * locationDisplay keys
     */
    public function getPickUpLocations($patron = false, $holdDetails = null)
    {
        return $this->defaultCall('getPickUpLocations', func_get_args());
    }

    /**
     * Get list of users for whom the provided patron is a proxy.
     *
     * @param array $patron The patron array with username and password
     *
     * @return array
     */
    public function getProxiedUsers($patron)
    {
        return $this->defaultCall('getProxiedUsers', func_get_args());
    }

    /**
     * Get Purchase History
     *
     * This is responsible for retrieving the acquisitions history data for the
     * specific record (usually recently received issues of a serial).
     *
     * @param string $id The record id to retrieve the info for
     *
     * @throws ILSException
     * @return array     An array with the acquisitions data on success.
     */
    public function getPurchaseHistory($id)
    {
        return $this->combineArraysOfAssociativeArrays('getPurchaseHistory', func_get_args());
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
        return $this->defaultCall('getRenewDetails', func_get_args());
    }

    /**
     * Check whether the patron is blocked from placing requests (holds/ILL/SRR).
     *
     * @param array $patron Patron data from patronLogin().
     *
     * @return mixed A boolean false if no blocks are in place and an array
     * of block reasons if blocks are in place
     */
    public function getRequestBlocks($patron)
    {
        return $this->defaultCall('getRequestBlocks', func_get_args());
    }

    /**
     * Get request groups
     *
     * @param int   $id          BIB ID
     * @param array $patron      Patron information returned by the patronLogin
     * method.
     * @param array $holdDetails Optional array, only passed in when getting a list
     * in the context of placing a hold; contains most of the same values passed to
     * placeHold, minus the patron data. May be used to limit the request group
     * options or may be ignored.
     *
     * @return array  An array of associative arrays with requestGroupId and
     * name keys
     */
    public function getRequestGroups($id, $patron, $holdDetails = null)
    {
        return $this->defaultCall('getRequestGroups', func_get_args());
    }

    /**
     * Get Status
     *
     * This is responsible for retrieving the status information of a certain
     * record.
     *
     * @param string $id The record id to retrieve the holdings for
     *
     * @throws ILSException
     * @return mixed     On success, an associative array with the following keys:
     * id, availability (boolean), status, location, reserve, callnumber.
     */
    public function getStatus($id)
    {
        return $this->combineArraysOfAssociativeArrays('getStatus', [$id]);
    }

    /**
     * Get Statuses
     *
     * This is responsible for retrieving the status information for a
     * collection of records.
     *
     * @param array $ids The array of record ids to retrieve the status for
     *
     * @throws ILSException
     * @return array     An array of getStatus() return values on success.
     */
    public function getStatuses($ids)
    {
        return $this->combineMultipleArraysOfAssociativeArrays('getStatuses', [$ids], 'id');
    }

    /**
     * Get suppressed authority records
     *
     * @return array ID numbers of suppressed authority records in the system.
     */
    public function getSuppressedAuthorityRecords()
    {
        return $this->defaultCall('getSuppressedAuthorityRecords', func_get_args());
    }

    /**
     * Get suppressed records.
     *
     * @throws ILSException
     * @return array ID numbers of suppressed records in the system.
     */
    public function getSuppressedRecords()
    {
        return $this->defaultCall('getSuppressedRecords', func_get_args());
    }

    /**
     * Provide an array of URL data (in the same format returned by the record
     * driver's getURLs method) for the specified bibliographic record.
     *
     * @param string $id Bibliographic record ID
     *
     * @return array
     */
    public function getUrlsForRecord($id)
    {
        return $this->defaultCall('getUrlsForRecord', func_get_args());
    }

    /**
     * Has Holdings
     *
     * This is responsible for determining if holdings exist for a particular
     * bibliographic id
     *
     * @param string $id The record id to retrieve the holdings for
     *
     * @return bool True if holdings exist, False if they do not
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function hasHoldings($id)
    {
        return $this->defaultCall('hasHoldings', func_get_args());
    }

    /**
     * Get Hidden Login Mode
     *
     * This is responsible for indicating whether login should be hidden.
     *
     * @return bool true if the login should be hidden, false if not
     */
    public function loginIsHidden()
    {
        return $this->defaultCall('loginIsHidden', func_get_args());
    }

    /**
     * Patron Login
     *
     * This is responsible for authenticating a patron against the catalog.
     *
     * @param string $username The patron barcode
     * @param string $password The patron password
     *
     * @throws ILSException
     * @return mixed           Associative array of patron info on successful login,
     * null on unsuccessful login.
     */
    public function patronLogin($username, $password)
    {
        return $this->defaultCall('patronLogin', func_get_args());
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
        return $this->defaultCall('placeHold', func_get_args());
    }

    /**
     * Place ILL Request
     *
     * Attempts to place an ILL request on a particular item and returns
     * an array with result details (or throws an exception on failure of support
     * classes)
     *
     * @param array $details An array of item and patron data
     *
     * @return mixed An array of data on the request including
     * whether or not it was successful and a system message (if available)
     */
    public function placeILLRequest($details)
    {
        return $this->defaultCall('placeILLRequest', func_get_args());
    }

    /**
     * Place Storage Retrieval Request
     *
     * Attempts to place a storage retrieval request on a particular item and returns
     * an array with result details
     *
     * @param array $details An array of item and patron data
     *
     * @return mixed An array of data on the request including
     * whether or not it was successful and a system message (if available)
     */
    public function placeStorageRetrievalRequest($details)
    {
        return $this->defaultCall('placeStorageRetrievalRequest', func_get_args());
    }

    /**
     * Purge Patron Transaction History
     *
     * @param array  $patron The patron array from patronLogin
     * @param ?array $ids    IDs to purge, or null for all
     *
     * @throws ILSException
     * @return array Associative array of the results
     */
    public function purgeTransactionHistory(array $patron, ?array $ids)
    {
        return $this->defaultCall('purgeTransactionHistory', func_get_args());
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
     * @return array An array of renewal information keyed by item ID
     */
    public function renewMyItems($renewDetails)
    {
        return $this->defaultCall('renewMyItems', func_get_args());
    }

    /**
     * Renew My Items Link
     *
     * @param array $checkedOutDetails Checked Out Details
     *
     * @return string Url to a native OPAC
     */
    public function renewMyItemsLink($checkedOutDetails)
    {
        return $this->defaultCall('renewMyItemsLink', func_get_args());
    }

    /**
     * Helper method to determine whether or not a certain method can be
     * called on this driver. Required method for any smart drivers.
     *
     * @param string $method The name of the called method.
     * @param array  $params Array of passed parameters.
     *
     * @return bool True if the method can be called with the given parameters,
     * false otherwise.
     */
    public function supportsMethod($method, $params)
    {
        $driverName = $this->config[$method]['main_driver'] ?? $this->mainDriver;
        $driver = $this->getDriver($driverName);
        return $driver && $this->driverSupportsMethod($driver, $method, $params);
    }

    /**
     * Update holds
     *
     * This is responsible for changing the status of hold requests
     *
     * @param array $holdsDetails The details identifying the holds
     * @param array $fields       An associative array of fields to be updated
     * @param array $patron       Patron array
     *
     * @return array Associative array of the results
     */
    public function updateHolds($holdsDetails, $fields, $patron)
    {
        return $this->defaultCall('updateHolds', func_get_args());
    }

    /**
     * Get available login targets (drivers enabled for login)
     *
     * @return string[] Source ID's
     */
    public function getLoginDrivers()
    {
        return [$this->mainDriver];
    }

    /**
     * Get default login driver
     *
     * @return string Default login driver or empty string
     */
    public function getDefaultLoginDriver()
    {
        return $this->mainDriver;
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
     * placeHold, minus the patron data. May be used to limit the request group
     * options or may be ignored.
     *
     * @return string A location ID
     */
    public function getDefaultRequestGroup($patron, $holdDetails = null)
    {
        return $this->defaultCall('getDefaultRequestGroup', func_get_args());
    }

    /**
     * Default method -- pass along calls to the driver if a source can be determined
     * and a driver is available. Throws ILSException otherwise.
     *
     * @param string $methodName The name of the called method
     * @param array  $params     Array of passed parameters
     *
     * @throws ILSException
     * @return mixed             Varies by method
     */
    public function __call($methodName, $params)
    {
        return $this->defaultCall($methodName, $params);
    }

    /**
     * Calling a function of a driver
     *
     * @param string $driverName Name of the driver on which the method is called
     * @param string $method     Name of the method
     * @param array  $params     Parameters
     *
     * @return mixed
     */
    protected function callDriverMethod($driverName, $method, $params)
    {
        $driver = $this->getDriver($driverName);
        return call_user_func_array([$driver, $method], $params);
    }

    /**
     * Determines which driver should be used for the specified method
     *
     * @param $method string name of the method
     *
     * @return string
     */
    protected function getMainDriverNameForMethod($method)
    {
        $driverName = $this->config[$method]['main_driver'] ?? $this->mainDriver;
        return $driverName;
    }

    /**
     * Simply calls the method for the specified main driver
     *
     * @param string $methodName Name of the method to be called
     * @param array  $params     Arguments for the method call
     *
     * @return mixed
     */
    protected function defaultCall($methodName, $params)
    {
        if ($this->supportsMethod($methodName, $params)) {
            $driverName = $this->getMainDriverNameForMethod($methodName);
            return $this->callDriverMethod($driverName, $methodName, $params);
        }
        throw new ILSException('Method "' . $methodName . '" is not supported.');
    }

    /**
     * Used for methods that return associative arrays. Calls the method for the main and support drivers and merges
     * the results. Only uses the specified support fields of the support drivers.
     *
     * @param string $methodName Name of the method to be called
     * @param array  $params     Arguments for the method call
     *
     * @return array
     */
    protected function mergeSingleArrayResults($methodName, $params)
    {
        $methodConfig = $this->config[$methodName] ?? [];

        // get main results
        $mainDriverName = $this->getMainDriverNameForMethod($methodName);
        $mainResult = $this->callDriverMethod($mainDriverName, $methodName, $params);

        $supportConfig = $methodConfig['support_drivers'] ?? [];
        $supportDriverNames = array_keys($supportConfig) ?? [];

        // get support results
        $supportResults = array_map(function ($driverName) use ($methodName, $params, $supportConfig) {
            $supportKeys = explode(',', $supportConfig[$driverName] ?? '');
            return array_intersect_key(
                $this->callDriverMethod($driverName, $methodName, $params),
                array_flip($supportKeys)
            );
        }, $supportDriverNames);

        // merge results
        return array_merge($mainResult, ...$supportResults);
    }

    /**
     * Used for methods where the result is a list of items. Calls the method for
     * the main driver and all support drivers. Then adds specified fields of the
     * support drivers to the main driver's result.
     *
     * @param $methodName              string Name of the method to be called
     * @param $params                  array  Arguments for the method call
     * @param $optionalResultSubfields array  Keys of possible result subfields
     *
     * @return mixed
     */
    protected function combineArraysOfAssociativeArrays($methodName, $params, $optionalResultSubfields = [])
    {
        $methodConfig = $this->config[$methodName] ?? [];

        // get main results
        $mainDriverName = $this->getMainDriverNameForMethod($methodName);
        $mainResult = $this->callDriverMethod($mainDriverName, $methodName, $params);

        if (!empty($mergeKeys = $methodConfig['merge_keys'] ?? [])) {
            $supportConfig = $methodConfig['support_drivers'] ?? [];
            $supportDriverNames = array_keys($supportConfig) ?? [];

            // get support results
            $supportResult = array_map(
                function ($driverName) use (
                    $params,
                    $mergeKeys,
                    $methodName,
                    $supportConfig,
                    $optionalResultSubfields
                ) {
                    $result = $this->callDriverMethod($driverName, $methodName, $params);
                    $result = $this->extractResultSubfields($result, $optionalResultSubfields);
                    $mergeKey = $mergeKeys[$driverName];
                    // extract support keys
                    $supportEntry = array_map(
                        function ($fullEntry) use ($mergeKey, $supportConfig, $driverName) {
                            $usedKeys = array_merge([$mergeKey], explode(',', $supportConfig[$driverName]));
                            return array_intersect_key($fullEntry, array_flip($usedKeys));
                        },
                        $result
                    );
                    return $this->extractKey($supportEntry, $mergeKey);
                },
                array_combine($supportDriverNames, $supportDriverNames)
            );

            // merge results
            $mainResult = $this->mergeInSubfields($mainResult, $supportResult, $mergeKeys, $optionalResultSubfields);
        }
        return $mainResult;
    }

    /**
     * Used for methods where the result is a list of lists of items. Calls the method for
     * the main driver and all support drivers. Then adds specified fields of the
     * support drivers to the main driver's result.
     *
     * @param $methodName              string Name of the method to be called
     * @param $params                  array  Arguments for the method
     * @param $baseMergeKey            string Key to match arrays on the first level
     * @param $optionalResultSubfields array  Keys of possible result subfields
     *
     * @return mixed
     */
    protected function combineMultipleArraysOfAssociativeArrays(
        $methodName,
        $params,
        $baseMergeKey,
        $optionalResultSubfields = []
    ) {
        $methodConfig = $this->config[$methodName] ?? [];

        // get main results
        $mainDriverName = $this->getMainDriverNameForMethod($methodName);
        $mainResult = $this->callDriverMethod($mainDriverName, $methodName, $params);
        $subMergeKeys = $methodConfig['merge_keys'] ?? [];

        if (!empty($subMergeKeys)) {
            $supportConfig = $methodConfig['support_drivers'] ?? [];
            $supportDriverNames = array_keys($supportConfig) ?? [];

            // get support results
            $supportResults = array_map(
                function ($driverName) use (
                    $params,
                    $baseMergeKey,
                    $subMergeKeys,
                    $methodName,
                    $supportConfig,
                    $optionalResultSubfields
                ) {
                    $results = $this->callDriverMethod($driverName, $methodName, $params);
                    return array_map(
                        function ($result) use (
                            $baseMergeKey,
                            $subMergeKeys,
                            $supportConfig,
                            $driverName,
                            $optionalResultSubfields
                        ) {
                            $result = $this->extractResultSubfields($result, $optionalResultSubfields);
                            $subMergeKey = $subMergeKeys[$driverName];
                            // extract support keys
                            $supportEntry = array_map(
                                function ($fullEntry) use ($subMergeKey, $supportConfig, $driverName, $baseMergeKey) {
                                    $usedKeys = array_merge(
                                        [$baseMergeKey, $subMergeKey],
                                        explode(',', $supportConfig[$driverName])
                                    );
                                    return array_intersect_key($fullEntry, array_flip($usedKeys));
                                },
                                $result
                            );
                            return  $this->extractKey($supportEntry, $subMergeKey);
                        },
                        $results
                    );
                },
                array_combine($supportDriverNames, $supportDriverNames)
            );

            // merge all single results
            $res = [];
            for ($i = 0; $i < count($mainResult); $i++) {
                if ($baseMergeValue = $mainResult[$i][0][$baseMergeKey] ?? false) {
                    $supportResult = array_map(function ($supportResult) use ($baseMergeKey, $baseMergeValue) {
                        return current(array_filter(
                            $supportResult,
                            function ($entry) use ($baseMergeKey, $baseMergeValue) {
                                return ($entry[array_keys($entry)[0]][$baseMergeKey] ?? null) === $baseMergeValue;
                            }
                        ));
                    }, $supportResults);
                    $res[] = $this->mergeInSubfields(
                        $mainResult[$i],
                        $supportResult,
                        $subMergeKeys,
                        $optionalResultSubfields
                    );
                }
            }
            $mainResult = $res;
        }
        return $mainResult;
    }

    /**
     * Extracts results from support drivers where the result can be split into named subfields.
     *
     * @param $result                  array Result of a support driver
     * @param $optionalResultSubfields array Keys of possible result subfields
     *
     * @return array
     */
    protected function extractResultSubfields($result, $optionalResultSubfields)
    {
        $includesSubfields = false;
        foreach ($optionalResultSubfields as $subfield) {
            $includesSubfields |= in_array($subfield, array_keys($result));
        }
        if ($includesSubfields) {
            $tmpResult = [];
            foreach ($optionalResultSubfields as $key) {
                $tmpResult = array_merge($tmpResult, $result[$key] ?? []);
            }
            $result = $tmpResult;
        }
        return $result;
    }

    /**
     * Merges results where the result can be split into named subfields.
     *
     * @param $mainResult              array Result of the main driver
     * @param $supportResults          array Result of a support driver
     * @param $mergeKeys               array Merge keys
     * @param $optionalResultSubfields array Keys of possible result subfields
     *
     * @return array
     */
    protected function mergeInSubfields($mainResult, $supportResults, $mergeKeys, $optionalResultSubfields)
    {
        $includesSubfields = false;
        foreach ($optionalResultSubfields as $subfield) {
            $includesSubfields |= in_array($subfield, array_keys($mainResult));
        }
        if ($includesSubfields) {
            foreach ($optionalResultSubfields as $key) {
                $mainResult[$key] = $this->mergeAssociativeArrays($mainResult[$key], $supportResults, $mergeKeys);
            }
        } else {
            $mainResult = $this->mergeAssociativeArrays($mainResult, $supportResults, $mergeKeys);
        }
        return $mainResult;
    }

    /**
     * Merges results of the main and the support drivers on the specified key
     *
     * @param array $mainResult     Result of main driver
     * @param array $supportResults Results of support drivers
     * @param array $mergeKeys      Key on which the results are merged
     *
     * @return array
     */
    protected function mergeAssociativeArrays($mainResult, $supportResults, $mergeKeys)
    {
        $res = [];
        foreach ($mainResult as $mainEntry) {
            foreach ($supportResults as $driverName => $supportResult) {
                $mergeKey = $mergeKeys[$driverName] ?? null;
                if ($mergeKey !== null && $mainEntry[$mergeKey]) {
                    // merge entries that match on $mergeKey
                    $supportEntry = $supportResult[$mainEntry[$mergeKey]] ?? [];
                    if (!empty($supportEntry)) {
                        $mainEntry = array_merge($supportEntry, $mainEntry);
                    }
                }
            }
            $res[] = $mainEntry;
        }
        return $res;
    }

    /**
     * Takes an array of item as input and creates an associative
     * array using specified fields of the items as key
     *
     * @param array  $data Array of items
     * @param string $key  Items field to be used as key
     *
     * @return array
     */
    protected function extractKey($data, $key)
    {
        $res = [];
        foreach ($data as $entry) {
            if (!empty($entry[$key])) {
                $res[$entry[$key]] = $entry;
            }
        }
        return $res;
    }
}
