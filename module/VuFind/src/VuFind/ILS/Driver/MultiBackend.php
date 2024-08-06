<?php

/**
 * Multiple Backend Driver.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2012-2021.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */

namespace VuFind\ILS\Driver;

use VuFind\Exception\ILS as ILSException;

use function call_user_func_array;
use function func_get_args;
use function in_array;
use function is_array;
use function is_callable;
use function is_int;
use function is_string;
use function strlen;

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
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
class MultiBackend extends AbstractMultiDriver
{
    use \VuFind\Log\LoggerAwareTrait {
        logError as error;
    }

    /**
     * ID fields in holds
     */
    public const HOLD_ID_FIELDS = ['id', 'item_id', 'cat_username'];

    /**
     * The default driver to use
     *
     * @var string
     */
    protected $defaultDriver;

    /**
     * ILS authenticator
     *
     * @var \VuFind\Auth\ILSAuthenticator
     */
    protected $ilsAuth;

    /**
     * An array of methods that should determine source from a specific parameter
     * field
     *
     * @var array
     */
    protected $sourceCheckFields = [
        'cancelHolds' => 'cat_username',
        'cancelILLRequests' => 'cat_username',
        'cancelStorageRetrievalRequests' => 'cat_username',
        'changePassword' => 'cat_username',
        'getCancelHoldDetails' => 'cat_username',
        'getCancelILLRequestDetails' => 'cat_username',
        'getCancelStorageRetrievalRequestDetails' => 'cat_username',
        'getMyFines' => 'cat_username',
        'getMyProfile' => 'cat_username',
        'getMyTransactionHistory' => 'cat_username',
        'getMyTransactions' => 'cat_username',
        'renewMyItems' => 'cat_username',
    ];

    /**
     * Methods that don't have parameters that allow the correct source to be
     * determined. These methods are only supported for the default driver.
     */
    protected $methodsWithNoSourceSpecificParameters = [
        'findReserves',
        'getCourses',
        'getDepartments',
        'getFunds',
        'getInstructors',
        'getNewItems',
        'getOfflineMode',
        'getSuppressedAuthorityRecords',
        'getSuppressedRecords',
        'loginIsHidden',
    ];

    /**
     * Constructor
     *
     * @param \VuFind\Config\PluginManager  $configLoader Configuration loader
     * @param \VuFind\Auth\ILSAuthenticator $ilsAuth      ILS authenticator
     * @param PluginManager                 $dm           ILS driver manager
     */
    public function __construct(
        \VuFind\Config\PluginManager $configLoader,
        \VuFind\Auth\ILSAuthenticator $ilsAuth,
        PluginManager $dm
    ) {
        parent::__construct($configLoader, $dm);
        $this->ilsAuth = $ilsAuth;
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
        parent::init();
        $this->defaultDriver = $this->config['General']['default_driver'] ?? null;
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
        if ($driver = $this->getDriver($source)) {
            $status = $driver->getStatus($this->getLocalId($id));
            return $this->addIdPrefixes($status, $source);
        }
        // Return an empty array if driver is not available; id can point to an ILS
        // that's not currently configured.
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
        // Group records by source and request statuses from the drivers
        $grouped = [];
        foreach ($ids as $id) {
            $source = $this->getSource($id);
            if (!isset($grouped[$source])) {
                $driver = $this->getDriver($source);
                $grouped[$source] = [
                    'driver' => $driver,
                    'ids' => [],
                ];
            }
            $grouped[$source]['ids'][] = $id;
        }

        // Process each group
        $results = [];
        foreach ($grouped as $source => $current) {
            // Get statuses only if a driver is configured for this source
            if ($current['driver']) {
                $localIds = array_map(
                    function ($id) {
                        return $this->getLocalId($id);
                    },
                    $current['ids']
                );
                try {
                    $statuses = $current['driver']->getStatuses($localIds);
                } catch (ILSException $e) {
                    $statuses = array_map(
                        function ($id) {
                            return [
                                ['id' => $id, 'error' => 'An error has occurred'],
                            ];
                        },
                        $localIds
                    );
                }
                $statuses = array_map(
                    function ($status) use ($source) {
                        return $this->addIdPrefixes($status, $source);
                    },
                    $statuses
                );
                $results = array_merge($results, $statuses);
            }
        }
        return $results;
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
        $source = $this->getSource($id);
        if ($driver = $this->getDriver($source)) {
            // If the patron belongs to another source, just pass on an empty array
            // to indicate that the patron has logged in but is not available for the
            // current catalog.
            if (
                $patron
                && !$this->driverSupportsSource($source, $patron['cat_username'])
            ) {
                $patron = [];
            }
            $holdings = $driver->getHolding(
                $this->getLocalId($id),
                $this->stripIdPrefixes($patron, $source),
                $options
            );
            return $this->addIdPrefixes($holdings, $source);
        }
        // Return an empty array if driver is not available; id can point to an ILS
        // that's not currently configured.
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
        if ($driver = $this->getDriver($source)) {
            return $driver->getPurchaseHistory($this->getLocalId($id));
        }
        // Return an empty array if driver is not available; id can point to an ILS
        // that's not currently configured.
        return [];
    }

    /**
     * Get available login targets (drivers enabled for login)
     *
     * @return string[] Source ID's
     */
    public function getLoginDrivers()
    {
        return $this->config['Login']['drivers'] ?? [];
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
        if ($driver = $this->getDriver($this->defaultDriver)) {
            $result = $driver->getNewItems($page, $limit, $daysOld, $fundId);
            if (isset($result['results'])) {
                $result['results']
                    = $this->addIdPrefixes($result['results'], $this->defaultDriver);
            }
            return $result;
        }
        throw new ILSException('No suitable backend driver found');
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
        if ($driver = $this->getDriver($this->defaultDriver)) {
            return $driver->getDepartments();
        }
        throw new ILSException('No suitable backend driver found');
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
        if ($driver = $this->getDriver($this->defaultDriver)) {
            return $driver->getInstructors();
        }
        throw new ILSException('No suitable backend driver found');
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
        if ($driver = $this->getDriver($this->defaultDriver)) {
            return $driver->getCourses();
        }
        throw new ILSException('No suitable backend driver found');
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
        if ($driver = $this->getDriver($this->defaultDriver)) {
            return $this->addIdPrefixes(
                $driver->findReserves($course, $inst, $dept),
                $this->defaultDriver,
                ['BIB_ID']
            );
        }
        throw new ILSException('No suitable backend driver found');
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
        $source = $this->getSource($patron['cat_username']);
        if ($driver = $this->getDriver($source)) {
            return $this->addIdPrefixes(
                $driver->getMyProfile($this->stripIdPrefixes($patron, $source)),
                $source
            );
        }
        // Return an empty array if driver is not available; cat_username can point
        // to an ILS that's not currently configured.
        return [];
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
        $holds = $this->callMethodIfSupported(
            $source,
            __FUNCTION__,
            func_get_args(),
            true,
            false
        );
        return $this->addIdPrefixes($holds, $source, self::HOLD_ID_FIELDS);
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
        if ($driver = $this->getDriver($source)) {
            $params = [
                $this->stripIdPrefixes($patron, $source),
            ];
            if (!$this->driverSupportsMethod($driver, __FUNCTION__, $params)) {
                // Return empty array if not supported by the driver
                return [];
            }
            $requests = $driver->getMyStorageRetrievalRequests(...$params);
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
     * @param array  $patron An array of patron data
     *
     * @return mixed An array of data on the request including
     * whether or not it is valid and a status message. Alternatively a boolean
     * true if request is valid, false if not.
     */
    public function checkRequestIsValid($id, $data, $patron)
    {
        if (!isset($patron['cat_username'])) {
            return false;
        }
        $source = $this->getSource($patron['cat_username']);
        if ($driver = $this->getDriver($source)) {
            if (!$this->driverSupportsSource($source, $id)) {
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
     * @param array  $patron An array of patron data
     *
     * @return mixed An array of data on the request including
     * whether or not it is valid and a status message. Alternatively a boolean
     * true if request is valid, false if not.
     */
    public function checkStorageRetrievalRequestIsValid($id, $data, $patron)
    {
        $source = $this->getSource($patron['cat_username']);
        if ($driver = $this->getDriver($source)) {
            if (
                !$this->driverSupportsSource($source, $id)
                || !is_callable([$driver, 'checkStorageRetrievalRequestIsValid'])
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
        $source = $this->getSource(
            $patron['cat_username'] ?? $holdDetails['id'] ?? $holdDetails['item_id']
            ?? ''
        );
        if ($driver = $this->getDriver($source)) {
            if ($id = ($holdDetails['id'] ?? $holdDetails['item_id'] ?? '')) {
                if (!$this->driverSupportsSource($source, $id)) {
                    // Return empty array since the sources don't match
                    return [];
                }
            }
            $locations = $driver->getPickUpLocations(
                $this->stripIdPrefixes($patron, $source),
                $this->stripIdPrefixes(
                    $holdDetails,
                    $source,
                    self::HOLD_ID_FIELDS
                )
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
     * placeHold, minus the patron data. May be used to limit the pickup options
     * or may be ignored.
     *
     * @return string A location ID
     */
    public function getDefaultPickUpLocation($patron = false, $holdDetails = null)
    {
        $source = $this->getSource($patron['cat_username']);
        if ($driver = $this->getDriver($source)) {
            if ($holdDetails) {
                if (!$this->driverSupportsSource($source, $holdDetails['id'])) {
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
        // Get source from patron as that will work also with the Demo driver:
        $source = $this->getSource($patron['cat_username']);
        if ($driver = $this->getDriver($source)) {
            $params = [
                $this->stripIdPrefixes($id, $source),
                $this->stripIdPrefixes($patron, $source),
                $this->stripIdPrefixes($holdDetails, $source),
            ];
            if (
                !$this->driverSupportsSource($source, $id)
                || !$this->driverSupportsMethod($driver, __FUNCTION__, $params)
            ) {
                // Return empty array since the sources don't match or the method
                // isn't supported by the driver
                return [];
            }
            $groups = $driver->getRequestGroups(...$params);
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
     * placeHold, minus the patron data. May be used to limit the request group
     * options or may be ignored.
     *
     * @return string A location ID
     */
    public function getDefaultRequestGroup($patron, $holdDetails = null)
    {
        $source = $this->getSource($patron['cat_username']);
        if ($driver = $this->getDriver($source)) {
            $params = [
                $this->stripIdPrefixes($patron, $source),
                $this->stripIdPrefixes($holdDetails, $source),
            ];
            if (!empty($holdDetails)) {
                if (
                    !$this->driverSupportsSource($source, $holdDetails['id'])
                    || !$this->driverSupportsMethod($driver, __FUNCTION__, $params)
                ) {
                    // Return false since the sources don't match or the method
                    // isn't supported by the driver
                    return false;
                }
            }
            $locations = $driver->getDefaultRequestGroup(...$params);
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
        if ($driver = $this->getDriver($source)) {
            if (!$this->driverSupportsSource($source, $holdDetails['id'])) {
                return [
                    'success' => false,
                    'sysMessage' => 'ILSMessages::hold_wrong_user_institution',
                ];
            }
            $holdDetails = $this->stripIdPrefixes($holdDetails, $source);
            return $driver->placeHold($holdDetails);
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
     * @param array $hold   A single hold array from getMyHolds
     * @param array $patron Patron information from patronLogin
     *
     * @return string Data for use in a form field
     */
    public function getCancelHoldDetails($hold, $patron = [])
    {
        $source = $this->getSource(
            $patron['cat_username'] ?? $hold['id'] ?? $hold['item_id'] ?? ''
        );
        $params = [
            $this->stripIdPrefixes(
                $hold,
                $source,
                self::HOLD_ID_FIELDS
            ),
            $this->stripIdPrefixes($patron, $source),
        ];
        return $this->callMethodIfSupported($source, __FUNCTION__, $params, false);
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
        if (
            $driver
            && is_callable([$driver, 'placeStorageRetrievalRequest'])
        ) {
            if (!$this->driverSupportsSource($source, $details['id'])) {
                return [
                    'success' => false,
                    'sysMessage' => 'ILSMessages::storage_wrong_user_institution',
                ];
            }
            return $driver->placeStorageRetrievalRequest(
                $this->stripIdPrefixes($details, $source)
            );
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
     * @param array  $patron An array of patron data
     *
     * @return mixed An array of data on the request including
     * whether or not it is valid and a status message. Alternatively a boolean
     * true if request is valid, false if not.
     */
    public function checkILLRequestIsValid($id, $data, $patron)
    {
        $source = $this->getSource($id);
        // Patron is not stripped so that the correct library can be determined
        $params = [
            $this->stripIdPrefixes($id, $source),
            $this->stripIdPrefixes($data, $source),
            $patron,
        ];
        return $this->callMethodIfSupported(
            $source,
            __FUNCTION__,
            $params,
            false,
            false
        );
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
        // Patron is not stripped so that the correct library can be determined
        $params = [
            $this->stripIdPrefixes($id, $source, ['id']),
            $patron,
        ];
        return $this->callMethodIfSupported(
            $source,
            __FUNCTION__,
            $params,
            false,
            false
        );
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
        // Patron is not stripped so that the correct library can be determined
        $params = [
            $this->stripIdPrefixes($id, $source, ['id']),
            $pickupLib,
            $patron,
        ];
        return $this->callMethodIfSupported(
            $source,
            __FUNCTION__,
            $params,
            false,
            false
        );
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
        $source = $this->getSource($details['id']);
        // Patron is not stripped so that the correct library can be determined
        $params = [$this->stripIdPrefixes($details, $source, ['id'], ['patron'])];
        return $this->callMethodIfSupported(
            $source,
            __FUNCTION__,
            $params,
            false,
            false
        );
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
        if ($driver = $this->getDriver($source)) {
            $params = [
                $this->stripIdPrefixes($patron, $source),
            ];
            if (!$this->driverSupportsMethod($driver, __FUNCTION__, $params)) {
                // Return empty array if not supported by the driver
                return [];
            }
            $requests = $driver->getMyILLRequests(...$params);
            return $this->addIdPrefixes(
                $requests,
                $source,
                ['id', 'item_id', 'cat_username']
            );
        }
        throw new ILSException('No suitable backend driver found');
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
        $source = $this->getSource($patron['cat_username']);
        if ($driver = $this->getDriver($source)) {
            $params = [
                $this->stripIdPrefixes($patron, $source),
            ];
            if (!$this->driverSupportsMethod($driver, __FUNCTION__, $params)) {
                return false;
            }
            return $driver->getRequestBlocks(...$params);
        }
        throw new ILSException('No suitable backend driver found');
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
        $source = $this->getSource($patron['cat_username']);
        if ($driver = $this->getDriver($source)) {
            $params = [
                $this->stripIdPrefixes($patron, $source),
            ];
            if (!$this->driverSupportsMethod($driver, __FUNCTION__, $params)) {
                return false;
            }
            return $driver->getAccountBlocks(...$params);
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
    public function getConfig($function, $params = [])
    {
        $source = null;
        if (!empty($params)) {
            $source = $this->getSourceForMethod($function, $params);
        }
        if (!$source) {
            try {
                $patron = $this->ilsAuth->getStoredCatalogCredentials();
                if ($patron && isset($patron['cat_username'])) {
                    $source = $this->getSource($patron['cat_username']);
                }
            } catch (ILSException $e) {
                return [];
            }
        }

        $driver = $this->getDriver($source);

        // If we have resolved the needed driver, call getConfig and return.
        if ($driver && $this->driverSupportsMethod($driver, 'getConfig', $params)) {
            return $driver->getConfig(
                $function,
                $this->stripIdPrefixes($params, $source)
            );
        }

        // If driver not available, return an empty array
        return [];
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
    public function supportsMethod(string $method, array $params)
    {
        if ($method == 'getLoginDrivers' || $method == 'getDefaultLoginDriver') {
            return true;
        }

        $source = $this->getSourceForMethod($method, $params);
        if (!$source && $this->defaultDriver) {
            $source = $this->defaultDriver;
        }
        if (!$source) {
            // If we can't determine the source, assume we are capable of handling
            // the request unless the method is one that doesn't have parameters that
            // allow the correct source to be determined.
            return !in_array($method, $this->methodsWithNoSourceSpecificParameters);
        }

        $driver = $this->getDriver($source);
        return $driver && $this->driverSupportsMethod($driver, $method, $params);
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
        return $this->callMethodIfSupported(null, $methodName, $params);
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
     * @return string Source
     */
    protected function getSource($id)
    {
        $pos = strpos($id, '.');
        if ($pos > 0) {
            return substr($id, 0, $pos);
        }

        return '';
    }

    /**
     * Get source for a method and parameters
     *
     * @param string $method Method
     * @param array  $params Parameters
     *
     * @return string
     */
    protected function getSourceForMethod(string $method, array $params): string
    {
        $source = '';
        $checkFields = $this->sourceCheckFields[$method] ?? null;
        if ($checkFields) {
            $source = $this->getSourceFromParams($params, (array)$checkFields);
        } else {
            $source = $this->getSourceFromParams($params);
        }
        return $source;
    }

    /**
     * Get source from method parameters
     *
     * @param array $params      Parameters of a driver method call
     * @param array $allowedKeys Keys to use for source identification
     *
     * @return string Source id or empty string if not found
     */
    protected function getSourceFromParams(
        $params,
        $allowedKeys = [0, 'id', 'cat_username']
    ) {
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
                $source = $this->getSourceFromParams($value, $allowedKeys);
            } elseif (in_array($key, $allowedKeys)) {
                $source = $this->getSource($value);
            }
            if ($source && isset($this->drivers[$source])) {
                return $source;
            }
        }
        return '';
    }

    /**
     * Find the correct driver for the correct configuration file for the
     * given source and cache an initialized copy of it.
     *
     * @param string $source The source name of the driver to get.
     *
     * @return mixed On success a driver object, otherwise null.
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
        return parent::getDriver($source);
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
    protected function addIdPrefixes(
        $data,
        $source,
        $modifyFields = ['id', 'cat_username']
    ) {
        if (empty($source) || empty($data) || !is_array($data)) {
            return $data;
        }

        foreach ($data as $key => $value) {
            if (null === $value) {
                continue;
            }
            if (is_array($value)) {
                $data[$key] = $this->addIdPrefixes(
                    $value,
                    $source,
                    $modifyFields
                );
            } else {
                if (
                    !ctype_digit((string)$key)
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
     * @param array  $ignoreFields Fields to be ignored during recursive processing
     *
     * @return mixed     Modified array or empty/null if that input was
     *                   empty/null
     */
    protected function stripIdPrefixes(
        $data,
        $source,
        $modifyFields = ['id', 'cat_username'],
        $ignoreFields = []
    ) {
        if (!isset($data) || empty($data)) {
            return $data;
        }
        $array = is_array($data) ? $data : [$data];

        foreach ($array as $key => $value) {
            if (null === $value) {
                continue;
            }
            if (is_array($value)) {
                if (in_array($key, $ignoreFields)) {
                    continue;
                }
                $array[$key] = $this->stripIdPrefixes(
                    $value,
                    $source,
                    $modifyFields
                );
            } else {
                $prefixLen = strlen($source) + 1;
                if (
                    (!is_array($data)
                    || (!ctype_digit((string)$key) && in_array($key, $modifyFields)))
                    && strncmp("$source.", $value, $prefixLen) == 0
                ) {
                    $array[$key] = substr($value, $prefixLen);
                }
            }
        }
        return is_array($data) ? $array : $array[0];
    }

    /**
     * Check if the given ILS driver supports the source of a record
     *
     * @param string $driverSource Driver's source identifier
     * @param string $id           Prefixed identifier to compare with
     *
     * @return bool
     */
    protected function driverSupportsSource(string $driverSource, string $id): bool
    {
        // Same source is always ok:
        if ($this->getSource($id) === $driverSource) {
            return true;
        }
        // Demo driver supports any record source:
        $driver = $this->getDriver($driverSource);
        return $driver instanceof \VuFind\ILS\Driver\Demo;
    }

    /**
     * Check that the requested method is supported and call it.
     *
     * @param string $source        Source ID or null to determine from parameters
     * @param string $method        Method name
     * @param array  $params        Method parameters
     * @param bool   $stripPrefixes Whether to strip ID prefixes from all input
     * parameters
     * @param bool   $addPrefixes   Whether to add ID prefixes to the call result
     *
     * @return mixed
     * @throws ILSException
     */
    protected function callMethodIfSupported(
        ?string $source,
        string $method,
        array $params,
        bool $stripPrefixes = true,
        bool $addPrefixes = true
    ) {
        if (null === $source) {
            $source = $this->getSourceForMethod($method, $params);
        }
        $driver = $this->getDriver($source);
        if ($driver) {
            if ($stripPrefixes) {
                foreach ($params as &$param) {
                    $param = $this->stripIdPrefixes($param, $source);
                }
                unset($param);
            }
            if ($this->driverSupportsMethod($driver, $method, $params)) {
                $result = call_user_func_array([$driver, $method], $params);
                if ($addPrefixes) {
                    $result = $this->addIdPrefixes($result, $source);
                }
                return $result;
            }
        }
        throw new ILSException('No suitable backend driver found');
    }
}
