<?php
/**
 * Multiple Backend Driver.
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2012.
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
namespace VuFind\ILS\Driver;

use VuFind\Exception\ILS as ILSException,
    Zend\ServiceManager\ServiceLocatorAwareInterface,
    Zend\ServiceManager\ServiceLocatorInterface;

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
class MultiBackend extends AbstractBase
    implements ServiceLocatorAwareInterface, \Zend\Log\LoggerAwareInterface
{
    use \VuFind\Log\LoggerAwareTrait {
        logError as error;
    }
    use \Zend\ServiceManager\ServiceLocatorAwareTrait;

    /**
     * The array of configured driver names.
     *
     * @var string[]
     */
    protected $drivers = [];

    /**
     * The default driver to use
     *
     * @var string
     */
    protected $defaultDriver;

    /**
     * The array of cached pre-instantiated drivers
     *
     * @var object[]
     */
    protected $cache = [];

    /**
     * The array of booleans letting us know if a
     * driver in the cache has been initialized.
     *
     * @var boolean[]
     */
    protected $isInitialized = [];

    /**
     * The array of driver configuration options.
     *
     * @var string[]
     */
    protected $config = [];

    /**
     * Configuration loader
     *
     * @var \VuFind\Config\PluginManager
     */
    protected $configLoader;

    /**
     * ILS authenticator
     *
     * @param \VuFind\Auth\ILSAuthenticator
     */
    protected $ilsAuth;

    /**
     * Constructor
     *
     * @param \VuFind\Config\PluginManager  $configLoader Configuration loader
     * @param \VuFind\Auth\ILSAuthenticator $ilsAuth      ILS authenticator
     */
    public function __construct(\VuFind\Config\PluginManager $configLoader,
        \VuFind\Auth\ILSAuthenticator $ilsAuth
    ) {
        $this->configLoader = $configLoader;
        $this->ilsAuth = $ilsAuth;
    }

    /**
     * Set the driver configuration.
     *
     * @param Config $config The configuration to be set
     *
     * @return void
     */
    public function setConfig($config)
    {
        $this->config = $config;
    }

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
        if (empty($this->config)) {
            throw new ILSException('Configuration needs to be set.');
        }
        $this->drivers = $this->config['Drivers'];
        $this->defaultDriver = isset($this->config['General']['default_driver'])
            ? $this->config['General']['default_driver']
            : null;
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
        $source = $this->getSource($id);
        $driver = $this->getDriver($source);
        if ($driver) {
            $status = $driver->getStatus($this->getLocalId($id));
            return $this->addIdPrefixes($status, $source);
        }
        return [];
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
        $items = [];
        foreach ($ids as $id) {
            $items[] = $this->getStatus($id);
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
     * @return array         On success, an associative array with the following
     * keys: id, availability (boolean), status, location, reserve, callnumber,
     * duedate, number, barcode.
     */
    public function getHolding($id, array $patron = null)
    {
        $source = $this->getSource($id);
        $driver = $this->getDriver($source);
        if ($driver) {
            $holdings = $driver->getHolding(
                $this->getLocalId($id),
                $this->stripIdPrefixes($patron, $source)
            );
            return $this->addIdPrefixes($holdings, $source);
        }
        return [];
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
        $source = $this->getSource($id);
        $driver = $this->getDriver($source);
        if ($driver) {
            return $driver->getPurchaseHistory($this->getLocalId($id));
        }
        return [];
    }

    /**
     * Get available login targets (drivers enabled for login)
     *
     * @return string[] Source ID's
     */
    public function getLoginDrivers()
    {
        return isset($this->config['Login']['drivers'])
            ? $this->config['Login']['drivers']
            : [];
    }

    /**
     * Get default login driver
     *
     * @return string Default login driver or empty string
     */
    public function getDefaultLoginDriver()
    {
        if (isset($this->config['Login']['default_driver'])) {
            return $this->config['Login']['default_driver'];
        }
        $drivers = $this->getLoginDrivers();
        if ($drivers) {
            return $drivers[0];
        }
        return '';
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
        $driver = $this->getDriver($this->defaultDriver);
        if ($driver) {
            $result = $driver->getNewItems($page, $limit, $daysOld, $fundId);
            if (isset($result['results'])) {
                $result['results']
                    = $this->addIdPrefixes($result['results'], $this->defaultDriver);
            }
            return $result;
        }
        return [];
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
        $driver = $this->getDriver($this->defaultDriver);
        if ($driver) {
            return $driver->getDepartments();
        }
        return [];
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
        $driver = $this->getDriver($this->defaultDriver);
        if ($driver) {
            return $driver->getInstructors();
        }
        return [];
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
        $driver = $this->getDriver($this->defaultDriver);
        if ($driver) {
            return $driver->getCourses();
        }
        return [];
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
        $driver = $this->getDriver($this->defaultDriver);
        if ($driver) {
            return $this->addIdPrefixes(
                $driver->findReserves($course, $inst, $dept),
                $this->defaultDriver,
                ['BIB_ID']
            );
        }
        return [];
    }

    /**
     * Get Patron Profile
     *
     * This is responsible for retrieving the profile for a specific patron.
     *
     * @param array $patron The patron array
     *
     * @return mixed      Array of the patron's profile data
     */
    public function getMyProfile($patron)
    {
        $source = $this->getSource($patron['cat_username']);
        $driver = $this->getDriver($source);
        if ($driver) {
            $profile = $driver
                ->getMyProfile($this->stripIdPrefixes($patron, $source));
            return $this->addIdPrefixes($profile, $source);
        }
        return [];
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
        $source = $this->getSource($username);
        if (!$source) {
            $source = $this->getDefaultLoginDriver();
        }
        $driver = $this->getDriver($source);
        if ($driver) {
            $patron = $driver->patronLogin(
                $this->getLocalId($username), $password
            );
            $patron = $this->addIdPrefixes($patron, $source);
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
     * @param array $patron The patron array from patronLogin
     *
     * @return mixed      Array of the patron's transactions
     */
    public function getMyTransactions($patron)
    {
        $source = $this->getSource($patron['cat_username']);
        $driver = $this->getDriver($source);
        if ($driver) {
            $transactions = $driver->getMyTransactions(
                $this->stripIdPrefixes($patron, $source)
            );
            return $this->addIdPrefixes($transactions, $source);
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
     * @return array An array of renewal information keyed by item ID
     */
    public function renewMyItems($renewDetails)
    {
        $source = $this->getSource($renewDetails['patron']['cat_username']);
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
     * @param array $patron The patron array from patronLogin
     *
     * @return mixed      Array of the patron's fines
     */
    public function getMyFines($patron)
    {
        $source = $this->getSource($patron['cat_username']);
        $driver = $this->getDriver($source);
        if ($driver) {
            $fines = $driver->getMyFines($this->stripIdPrefixes($patron, $source));
            return $this->addIdPrefixes($fines, $source);
        }
        throw new ILSException('No suitable backend driver found');
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
        $source = $this->getSource($patron['cat_username']);
        $driver = $this->getDriver($source);
        if ($driver) {
            $holds = $driver->getMyHolds($this->stripIdPrefixes($patron, $source));
            return $this->addIdPrefixes(
                $holds, $source, ['id', 'item_id', 'cat_username']
            );
        }
        throw new ILSException('No suitable backend driver found');
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
        $source = $this->getSource($patron['cat_username']);
        $driver = $this->getDriver($source);
        if ($driver) {
            if (!$this->methodSupported(
                $driver, 'getMyStorageRetrievalRequests', compact('patron')
            )) {
                // Return empty array if not supported by the driver
                return [];
            }
            $requests = $driver->getMyStorageRetrievalRequests(
                $this->stripIdPrefixes($patron, $source)
            );
            return $this->addIdPrefixes($requests, $source);
        }
        throw new ILSException('No suitable backend driver found');
    }

    /**
     * Check whether a hold or recall request is valid
     *
     * This is responsible for determining if an item is requestable
     *
     * @param string $id     The Bib ID
     * @param array  $data   An Array of item data
     * @param patron $patron An array of patron data
     *
     * @return bool True if request is valid, false if not
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
     * Check whether a storage retrieval request is valid
     *
     * This is responsible for determining if an item is requestable
     *
     * @param string $id     The Bib ID
     * @param array  $data   An Array of item data
     * @param patron $patron An array of patron data
     *
     * @return bool True if request is valid, false if not
     */
    public function checkStorageRetrievalRequestIsValid($id, $data, $patron)
    {
        $source = $this->getSource($patron['cat_username']);
        $driver = $this->getDriver($source);
        if ($driver) {
            if ($this->getSource($id) != $source
                || !is_callable(
                    [$driver, 'checkStorageRetrievalRequestIsValid']
                )
            ) {
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
                    return [];
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
     * Get request groups
     *
     * @param integer $id     BIB ID
     * @param array   $patron Patron information returned by the patronLogin
     * method.
     *
     * @return array  An array of associative arrays with requestGroupId and
     * name keys
     */
    public function getRequestGroups($id, $patron)
    {
        $source = $this->getSource($id);
        $driver = $this->getDriver($source);
        if ($driver) {
            if ($this->getSource($patron['cat_username']) != $source
                || !$this->methodSupported(
                    $driver, 'getRequestGroups', compact('id', 'patron')
                )
            ) {
                // Return empty array since the sources don't match or the method
                // isn't supported by the driver
                return [];
            }
            $groups = $driver->getRequestGroups(
                $this->stripIdPrefixes($id, $source),
                $this->stripIdPrefixes($patron, $source)
            );
            return $groups;
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
    public function getDefaultRequestGroup($patron, $holdDetails = null)
    {
        $source = $this->getSource($patron['cat_username']);
        $driver = $this->getDriver($source);
        if ($driver) {
            if (!empty($holdDetails)) {
                if ($this->getSource($holdDetails['id']) != $source
                    || !$this->methodSupported(
                        $driver, 'getDefaultRequestGroup',
                        compact('patron', 'holdDetails')
                    )
                ) {
                    // Return false since the sources don't match or the method
                    // isn't supported by the driver
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
                return [
                    "success" => false,
                    "sysMessage" => 'hold_wrong_user_institution'
                ];
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
        $source = $this->getSource($details['patron']['cat_username']);
        $driver = $this->getDriver($source);
        if ($driver
            && is_callable([$driver, 'placeStorageRetrievalRequest'])
        ) {
            if ($this->getSource($details['id']) != $source) {
                return [
                    "success" => false,
                    "sysMessage" => 'hold_wrong_user_institution'
                ];
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
        if ($driver
            && $this->methodSupported(
                $driver, 'cancelStorageRetrievalRequests', compact('cancelDetails')
            )
        ) {
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
     *
     * @return string Data for use in a form field
     */
    public function getCancelStorageRetrievalRequestDetails($details)
    {
        $source = $this->getSource($details['id']);
        $driver = $this->getDriver($source);
        if ($driver
            && $this->methodSupported(
                $driver, 'getCancelStorageRetrievalRequestDetails',
                compact('details')
            )
        ) {
            $details = $this->stripIdPrefixes($details, $source);
            return $driver->getCancelStorageRetrievalRequestDetails($details);
        }
        throw new ILSException('No suitable backend driver found');
    }

    /**
     * Check whether an ILL request is valid
     *
     * This is responsible for determining if an item is requestable
     *
     * @param string $id     The Bib ID
     * @param array  $data   An Array of item data
     * @param patron $patron An array of patron data
     *
     * @return bool True if request is valid, false if not
     */
    public function checkILLRequestIsValid($id, $data, $patron)
    {
        $source = $this->getSource($id);
        $driver = $this->getDriver($source);
        if ($driver
            && $this->methodSupported(
                $driver, 'checkILLRequestIsValid', compact('id', 'data', 'patron')
            )
        ) {
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
        if ($driver
            && $this->methodSupported(
                $driver, 'getILLPickupLibraries', compact('id', 'patron')
            )
        ) {
            // Patron is not stripped so that the correct library can be determined
            return $driver->getILLPickupLibraries(
                $this->stripIdPrefixes($id, $source, ['id']),
                $patron
            );
        }
        throw new ILSException('No suitable backend driver found');
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
        if ($driver
            && $this->methodSupported(
                $driver, 'getILLPickupLocations',
                compact('id', 'pickupLib', 'patron')
            )
        ) {
            // Patron is not stripped so that the correct library can be determined
            return $driver->getILLPickupLocations(
                $this->stripIdPrefixes($id, $source, ['id']),
                $pickupLib,
                $patron
            );
        }
        throw new ILSException('No suitable backend driver found');
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
     */
    public function placeILLRequest($details)
    {
        $source = $this->getSource($details['id']);
        $driver = $this->getDriver($source);
        if ($driver
            && $this->methodSupported($driver, 'placeILLRequest', compact($details))
        ) {
            $details = $this->stripIdPrefixes($details, $source, ['id']);
            return $driver->placeILLRequest($details);
        }
        throw new ILSException('No suitable backend driver found');
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
        $source = $this->getSource($patron['cat_username']);
        $driver = $this->getDriver($source);
        if ($driver) {
            if (!$this->methodSupported(
                $driver, 'getMyILLRequests', compact('patron')
            )) {
                // Return empty array if not supported by the driver
                return [];
            }
            $requests = $driver->getMyILLRequests(
                $this->stripIdPrefixes($patron, $source)
            );
            return $this->addIdPrefixes(
                $requests, $source, ['id', 'item_id', 'cat_username']
            );
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
        if ($driver
            && $this->methodSupported(
                $driver, 'cancelILLRequests', compact('cancelDetails')
            )
        ) {
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
        if ($driver
            && $this->methodSupported(
                $driver, 'getCancelILLRequestDetails', compact('details')
            )
        ) {
            return $driver->getCancelILLRequestDetails(
                $this->stripIdPrefixes($details, $source)
            );
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
        if ($driver
            && $this->methodSupported($driver, 'changePassword', compact('details'))
        ) {
            return $driver->changePassword(
                $this->stripIdPrefixes($details, $source)
            );
        }
        throw new ILSException('No suitable backend driver found');
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
        $source = null;
        if (!empty($params)) {
            $source = $this->getSourceFromParams($params);
        }
        if (!$source) {
            $patron = $this->ilsAuth->storedCatalogLogin();
            if ($patron && isset($patron['cat_username'])) {
                $source = $this->getSource($patron['cat_username']);
            }
        }

        $driver = $this->getDriver($source);

        // If we have resolved the needed driver, just getConfig and return.
        if ($driver && $this->methodSupported($driver, 'getConfig', $params)) {
            return $driver->getConfig(
                $function, $this->stripIdPrefixes($params, $source)
            );
        }

        // If driver not available, return an empty array
        return [];
    }

    /**
     * Helper method to determine whether or not a certain method can be
     * called on this driver.  Required method for any smart drivers.
     *
     * @param string $method The name of the called method.
     * @param array  $params Array of passed parameters.
     *
     * @return bool True if the method can be called with the given parameters,
     * false otherwise.
     */
    public function supportsMethod($method, $params)
    {
        if ($method == 'getLoginDrivers' || $method == 'getDefaultLoginDriver') {
            return true;
        }

        $source = $this->getSourceFromParams($params);
        if (!$source && $this->defaultDriver) {
            $source = $this->defaultDriver;
        }
        if (!$source) {
            // If we can't determine the source, assume we are capable to handle
            // the request. This might happen e.g. when the user hasn't yet done
            // a catalog login.
            return true;
        }

        $driver = $this->getDriver($source);
        return $driver && $this->methodSupported($driver, $method, $params);
    }

    /**
     * Extract local ID from the given prefixed ID
     *
     * @param string $id The id to be split
     *
     * @return string  Local ID
     */
    protected function getLocalId($id)
    {
        $pos = strpos($id, '.');
        if ($pos > 0) {
            return substr($id, $pos + 1);
        }
        $this->debug("Could not find local id in '$id'");
        return $id;
    }

    /**
     * Extract source from the given ID
     *
     * @param string $id The id to be split
     *
     * @return string  Source
     */
    protected function getSource($id)
    {
        $pos = strpos($id, '.');
        if ($pos > 0) {
            return substr($id, 0, $pos);
        }

        $this->debug("Could not find source id in '$id'");
        return '';
    }

    /**
     * Get source from method parameters
     *
     * @param array $params Parameters of a driver method call
     *
     * @return string Source id or empty string if not found
     */
    protected function getSourceFromParams($params)
    {
        if (!is_array($params)) {
            if (is_string($params)) {
                $source = $this->getSource($params);
                if ($source && isset($this->drivers[$source])) {
                    return $source;
                }
            }
            return '';
        }
        foreach ($params as $key => $value) {
            $source = false;
            if (is_array($value) && (is_int($key) || $key === 'patron')) {
                $source = $this->getSourceFromParams($value);
            } elseif ($key === 0 || $key === 'id' || $key === 'cat_username') {
                $source = $this->getSource($value);
            }
            if ($source && isset($this->drivers[$source])) {
                return $source;
            }
        }
        $this->debug(
            'Could not find source id in params: ' . print_r($params, true)
        );
        return '';
    }

    /**
     * Find the correct driver for the correct configuration file for the
     * given source and cache an initialized copy of it.
     *
     * @param string $source The source name of the driver to get.
     *
     * @return mixed  On success a driver object, otherwise null.
     */
    protected function getDriver($source)
    {
        if (!$source) {
            // Check for default driver
            if ($this->defaultDriver) {
                $this->debug('Using default driver ' . $this->defaultDriver);
                $source = $this->defaultDriver;
            }
        }

        if (!isset($this->isInitialized[$source])
            || !$this->isInitialized[$source]
        ) {
            $driverInst = null;

            // And we don't have a copy in our cache...
            if (!isset($this->cache[$source])) {
                // Get an uninitialized copy
                $driverInst = $this->getUninitializedDriver($source);
            } else {
                // Otherwise, use the uninitialized cached copy
                $driverInst = $this->cache[$source];
            }

            // If we have a driver, initialize it.  That version has already
            // been cached.
            if ($driverInst) {
                $this->initializeDriver($driverInst, $source);
            } else {
                $this->debug("Could not initialize driver for source '$source'");
                return null;
            }
        }
        return $this->cache[$source];
    }

    /**
     * Find the correct driver for the correct configuration file
     * for the given source.  For performance reasons, we do not
     * want to initialize the driver yet if it hasn't been already.
     *
     * @param string $source the source title for the driver.
     *
     * @return mixed On success an uninitialized driver object, otherwise null.
     */
    protected function getUninitializedDriver($source)
    {
        // We don't really care if it's initialized here.  If it is, then there's
        // still no added overhead of returning an initialized driver.
        if (isset($this->cache[$source])) {
            return $this->cache[$source];
        }

        if (isset($this->drivers[$source])) {
            $driver = $this->drivers[$source];
            $config = $this->getDriverConfig($source);
            if (!$config) {
                $this->error("No configuration found for source '$source'");
                return null;
            }
            $driverInst = clone($this->getServiceLocator()->get($driver));
            $driverInst->setConfig($config);
            $this->cache[$source] = $driverInst;
            $this->isInitialized[$source] = false;
            return $driverInst;
        }

        return null;
    }

    /**
     * Initialize an uninitialized driver.
     *
     * @param object $driver The driver object to be initialized
     * @param string $source The source related to the driver for caching purposes.
     *
     * @return void
     */
    protected function initializeDriver($driver, $source)
    {
        if (!isset($this->isInitialized[$source])
            || !$this->isInitialized[$source]
        ) {
            $driver->init();
            $this->isInitialized[$source] = true;
            $this->cache[$source] = $driver;
        }
    }

    /**
     * Get configuration for the ILS driver.  We will load an .ini file named
     * after the driver class and number if it exists;
     * otherwise we will return an empty array.
     *
     * @param string $source The source id to use for determining the
     * configuration file
     *
     * @return array   The configuration of the driver
     */
    protected function getDriverConfig($source)
    {
        // Determine config file name based on class name:
        try {
            $config = $this->configLoader->get($source);
        } catch (\Zend\Config\Exception\RuntimeException $e) {
            // Configuration loading failed; probably means file does not
            // exist -- just return an empty array in that case:
            $this->error("Could not load config for $source");
            return [];
        }
        return $config->toArray();
    }

    /**
     * Change local ID's to global ID's in the given array
     *
     * @param mixed  $data         The data to be modified, normally
     * array or array of arrays
     * @param string $source       Source code
     * @param array  $modifyFields Fields to be modified in the array
     *
     * @return mixed     Modified array or empty/null if that input was
     *                   empty/null
     */
    protected function addIdPrefixes($data, $source,
        $modifyFields = ['id', 'cat_username']
    ) {
        if (!isset($data) || empty($data) || !is_array($data)) {
            return $data;
        }

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->addIdPrefixes(
                    $value, $source, $modifyFields
                );
            } else {
                if (!is_numeric($key)
                    && $value !== ''
                    && in_array($key, $modifyFields)
                ) {
                    $data[$key] = "$source.$value";
                }
            }
        }
        return $data;
    }

    /**
    * Change global ID's to local ID's in the given array
    *
    * @param mixed  $data         The data to be modified, normally
    * array or array of arrays
    * @param string $source       Source code
    * @param array  $modifyFields Fields to be modified in the array
    *
    * @return mixed     Modified array or empty/null if that input was
    *                   empty/null
    */
    protected function stripIdPrefixes($data, $source,
        $modifyFields = ['id', 'cat_username']
    ) {
        if (!isset($data) || empty($data)) {
            return $data;
        }
        $array = is_array($data) ? $data : [$data];

        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $array[$key] = $this->stripIdPrefixes(
                    $value, $source, $modifyFields
                );
            } else {
                $prefixLen = strlen($source) + 1;
                if ((!is_array($data) || in_array($key, $modifyFields))
                    && strncmp("$source.", $value, $prefixLen) == 0
                ) {
                    $array[$key] = substr($value, $prefixLen);
                }
            }
        }
        return is_array($data) ? $array : $array[0];
    }

    /**
     * Check whether the given driver supports the given method
     *
     * @param object $driver ILS Driver
     * @param string $method Method name
     * @param array  $params Array of passed parameters
     *
     * @return bool
     */
    protected function methodSupported($driver, $method, $params = null)
    {
        if (is_callable([$driver, $method])) {
            if (method_exists($driver, 'supportsMethod')) {
                return $driver->supportsMethod($method, $params ?: []);
            }
            return true;
        }
        return false;
    }
}
