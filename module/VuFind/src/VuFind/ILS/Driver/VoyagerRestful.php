<?php

/**
 * Voyager ILS Driver
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2007.
 * Copyright (C) The National Library of Finland 2014-2016.
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
 * @package  ILS_Drivers
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Luke O'Sullivan <l.osullivan@swansea.ac.uk>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */

namespace VuFind\ILS\Driver;

use PDO;
use PDOException;
use VuFind\Date\DateException;
use VuFind\Exception\ILS as ILSException;

use function count;
use function in_array;
use function is_callable;

/**
 * Voyager Restful ILS Driver
 *
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Luke O'Sullivan <l.osullivan@swansea.ac.uk>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
class VoyagerRestful extends Voyager implements
    \VuFindHttp\HttpServiceAwareInterface,
    \VuFind\I18n\HasSorterInterface
{
    use \VuFind\Cache\CacheTrait {
        getCacheKey as protected getBaseCacheKey;
    }
    use \VuFindHttp\HttpServiceAwareTrait;
    use \VuFind\I18n\HasSorterTrait;

    /**
     * Web services host
     *
     * @var string
     */
    protected $ws_host;

    /**
     * Web services port
     *
     * @var string
     */
    protected $ws_port;

    /**
     * Web services app
     *
     * @var string
     */
    protected $ws_app;

    /**
     * Web services database key
     *
     * @var string
     */
    protected $ws_dbKey;

    /**
     * Web services patron home UB ID
     *
     * @var string
     */
    protected $ws_patronHomeUbId;

    /**
     * Legal pickup locations
     *
     * @var array
     */
    protected $ws_pickUpLocations;

    /**
     * Default pickup location
     *
     * @var string
     */
    protected $defaultPickUpLocation;

    /**
     * The maximum number of holds to check at a time (0 = no limit)
     *
     * @var int
     */
    protected $holdCheckLimit;

    /**
     * The maximum number of call slips to check at a time (0 = no limit)
     *
     * @var int
     */
    protected $callSlipCheckLimit;

    /**
     * Holds mode
     *
     * @var string
     */
    protected $holdsMode;

    /**
     * Title-level holds mode
     *
     * @var string
     */
    protected $titleHoldsMode;

    /**
     * Web Services cookies. Required for at least renewals (for JSESSIONID) as
     * documented at http://www.exlibrisgroup.org/display/VoyagerOI/Renew
     *
     * @var \Laminas\Http\Response\Header\SetCookie[]
     */
    protected $cookies = false;

    /**
     * Whether recalls are enabled
     *
     * @var bool
     */
    protected $recallsEnabled;

    /**
     * Whether item holds are enabled
     *
     * @var bool
     */
    protected $itemHoldsEnabled;

    /**
     * Whether request groups are enabled
     *
     * @var bool
     */
    protected $requestGroupsEnabled;

    /**
     * Default request group
     *
     * @var bool|string
     */
    protected $defaultRequestGroup;

    /**
     * Whether pickup location must belong to the request group
     *
     * @var bool
     */
    protected $pickupLocationsInRequestGroup;

    /**
     * Whether to check that items exist when placing a hold or recall request
     *
     * @var bool
     */
    protected $checkItemsExist;

    /**
     * Whether to check that items are not available when placing a hold or recall
     * request
     *
     * @var bool
     */
    protected $checkItemsNotAvailable;

    /**
     * Whether to check that the user doesn't already have the record on loan when
     * placing a hold or recall request
     *
     * @var bool
     */
    protected $checkLoans;

    /**
     * Item locations excluded from item availability check.
     *
     * @var string
     */
    protected $excludedItemLocations;

    /**
     * Whether it is allowed to cancel a request for an item that is available for
     * pickup
     *
     * @var bool
     */
    protected $allowCancelingAvailableRequests;

    /**
     * Constructor
     *
     * @param \VuFind\Date\Converter $dateConverter  Date converter object
     * @param string                 $holdsMode      Holds mode setting
     * @param string                 $titleHoldsMode Title holds mode setting
     */
    public function __construct(
        \VuFind\Date\Converter $dateConverter,
        $holdsMode = 'disabled',
        $titleHoldsMode = 'disabled'
    ) {
        parent::__construct($dateConverter);
        $this->holdsMode = $holdsMode;
        $this->titleHoldsMode = $titleHoldsMode;
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

        // Define Voyager Restful Settings
        $this->ws_host = $this->config['WebServices']['host'];
        $this->ws_port = $this->config['WebServices']['port'];
        $this->ws_app = $this->config['WebServices']['app'];
        $this->ws_dbKey = $this->config['WebServices']['dbKey'];
        $this->ws_patronHomeUbId = $this->config['WebServices']['patronHomeUbId'];
        $this->ws_pickUpLocations
            = $this->config['pickUpLocations'] ?? false;
        $this->defaultPickUpLocation
            = $this->config['Holds']['defaultPickUpLocation'] ?? '';
        if ($this->defaultPickUpLocation === 'user-selected') {
            $this->defaultPickUpLocation = false;
        }
        $this->holdCheckLimit
            = $this->config['Holds']['holdCheckLimit'] ?? '15';
        $this->callSlipCheckLimit
            = $this->config['StorageRetrievalRequests']['checkLimit'] ?? '15';

        $this->recallsEnabled
            = $this->config['Holds']['enableRecalls'] ?? true;

        $this->itemHoldsEnabled
            = $this->config['Holds']['enableItemHolds'] ?? true;

        $this->requestGroupsEnabled
            = isset($this->config['Holds']['extraHoldFields'])
            && in_array(
                'requestGroup',
                explode(':', $this->config['Holds']['extraHoldFields'])
            );
        $this->defaultRequestGroup
            = $this->config['Holds']['defaultRequestGroup'] ?? false;
        if ($this->defaultRequestGroup === 'user-selected') {
            $this->defaultRequestGroup = false;
        }
        $this->pickupLocationsInRequestGroup
            = $this->config['Holds']['pickupLocationsInRequestGroup'] ?? false;

        $this->checkItemsExist
            = $this->config['Holds']['checkItemsExist'] ?? false;
        $this->checkItemsNotAvailable
            = $this->config['Holds']['checkItemsNotAvailable'] ?? false;
        $this->checkLoans
            = $this->config['Holds']['checkLoans'] ?? false;
        $this->excludedItemLocations
            = isset($this->config['Holds']['excludedItemLocations'])
            ? str_replace(':', ',', $this->config['Holds']['excludedItemLocations'])
            : '';
        $this->allowCancelingAvailableRequests
            = $this->config['Holds']['allowCancelingAvailableRequests'] ?? true;
    }

    /**
     * Public Function which retrieves renew, hold and cancel settings from the
     * driver ini file.
     *
     * @param string $function The name of the feature to be checked
     * @param array  $params   Optional feature-specific parameters (array)
     *
     * @return array An array with key-value pairs.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getConfig($function, $params = [])
    {
        if (isset($this->config[$function])) {
            $functionConfig = $this->config[$function];
        } else {
            $functionConfig = false;
        }

        return $functionConfig;
    }

    /**
     * Add instance-specific context to a cache key suffix (to ensure that
     * multiple drivers don't accidentally share values in the cache.
     *
     * @param string $key Cache key suffix
     *
     * @return string
     */
    protected function getCacheKey($key = null)
    {
        // Override the base class formatting with Voyager-specific details
        // to ensure proper caching in a MultiBackend environment.
        return 'VoyagerRestful-'
            . md5("{$this->ws_host}|{$this->ws_dbKey}|$key");
    }

    /**
     * Support method for VuFind Hold Logic. Take an array of status strings
     * and determines whether or not an item is holdable based on the
     * valid_hold_statuses settings in configuration file
     *
     * @param array $statusArray The status codes to analyze.
     *
     * @return bool Whether an item is holdable
     */
    protected function isHoldable($statusArray)
    {
        // User defined hold behaviour
        $is_holdable = true;

        if (!empty($this->config['Holds']['valid_hold_statuses'])) {
            $valid_hold_statuses_array
                = explode(':', $this->config['Holds']['valid_hold_statuses']);

            foreach ($statusArray as $status) {
                if (!in_array($status, $valid_hold_statuses_array)) {
                    $is_holdable = false;
                }
            }
        }
        return $is_holdable;
    }

    /**
     * Support method for VuFind Hold Logic. Takes an item type id
     * and determines whether or not an item is borrowable based on the
     * non_borrowable settings in configuration file
     *
     * @param string $itemTypeID The item type id to analyze.
     *
     * @return bool Whether an item is borrowable
     */
    protected function isBorrowable($itemTypeID)
    {
        if (isset($this->config['Holds']['borrowable'])) {
            $borrowable = explode(':', $this->config['Holds']['borrowable']);
            if (!in_array($itemTypeID, $borrowable)) {
                return false;
            }
        }
        if (isset($this->config['Holds']['non_borrowable'])) {
            $nonBorrowable = explode(':', $this->config['Holds']['non_borrowable']);
            if (in_array($itemTypeID, $nonBorrowable)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Support method for VuFind Storage Retrieval Request (Call Slip) Logic.
     * Take a holdings row array and determine whether or not a call slip is
     * allowed based on the valid_call_slip_locations settings in configuration
     * file
     *
     * @param array $holdingsRow The holdings row to analyze.
     *
     * @return bool Whether an item is requestable
     */
    protected function isStorageRetrievalRequestAllowed($holdingsRow)
    {
        $holdingsRow = $holdingsRow['_fullRow'];
        if (
            !isset($holdingsRow['TEMP_ITEM_TYPE_ID'])
            || !isset($holdingsRow['ITEM_TYPE_ID'])
        ) {
            // Not a real item
            return false;
        }

        if (isset($this->config['StorageRetrievalRequests']['valid_item_types'])) {
            $validTypes = explode(
                ':',
                $this->config['StorageRetrievalRequests']['valid_item_types']
            );

            $type = $holdingsRow['TEMP_ITEM_TYPE_ID']
                ? $holdingsRow['TEMP_ITEM_TYPE_ID']
                : $holdingsRow['ITEM_TYPE_ID'];
            return in_array($type, $validTypes);
        }
        return true;
    }

    /**
     * Support method for VuFind ILL Logic. Take a holdings row array
     * and determine whether or not an ILL (UB) request is allowed.
     *
     * @param array $holdingsRow The holdings row to analyze.
     *
     * @return bool Whether an item is holdable
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function isILLRequestAllowed($holdingsRow)
    {
        return true;
    }

    /**
     * Protected support method for getHolding.
     *
     * @param array $id A Bibliographic id
     *
     * @return array Keyed data for use in an sql query
     */
    protected function getHoldingItemsSQL($id)
    {
        $sqlArray = parent::getHoldingItemsSQL($id);
        $sqlArray['expressions'][] = 'ITEM.ITEM_TYPE_ID';
        $sqlArray['expressions'][] = 'ITEM.TEMP_ITEM_TYPE_ID';

        return $sqlArray;
    }

    /**
     * Protected support method for getHolding.
     *
     * @param array $sqlRow SQL Row Data
     *
     * @return array Keyed data
     */
    protected function processHoldingRow($sqlRow)
    {
        $row = parent::processHoldingRow($sqlRow);
        $row += ['item_id' => $sqlRow['ITEM_ID'], '_fullRow' => $sqlRow];
        return $row;
    }

    /**
     * Protected support method for getHolding.
     *
     * @param array  $data   Item Data
     * @param string $id     The BIB record id
     * @param array  $patron Patron Data
     *
     * @return array Keyed data
     */
    protected function processHoldingData($data, $id, $patron = null)
    {
        $holding = parent::processHoldingData($data, $id, $patron);

        foreach ($holding as $i => $row) {
            $is_borrowable = isset($row['_fullRow']['ITEM_TYPE_ID'])
                ? $this->isBorrowable($row['_fullRow']['ITEM_TYPE_ID']) : false;
            $is_holdable = $this->itemHoldsEnabled
                && $this->isHoldable($row['_fullRow']['STATUS_ARRAY']);
            $isStorageRetrievalRequestAllowed
                = isset($this->config['StorageRetrievalRequests'])
                && $this->isStorageRetrievalRequestAllowed($row);
            $isILLRequestAllowed = isset($this->config['ILLRequests'])
                && $this->isILLRequestAllowed($row);
            // If the item cannot be borrowed or if the item is not holdable,
            // set is_holdable to false
            if (!$is_borrowable || !$is_holdable) {
                $is_holdable = false;
            }

            // Only used for driver generated hold links
            $addLink = false;
            $addStorageRetrievalLink = false;
            $holdType = '';
            $storageRetrieval = '';

            if ($is_holdable) {
                // Hold Type - If we have patron data, we can use it to determine if
                // a hold link should be shown
                if ($patron && $this->holdsMode == 'driver') {
                    // This limit is set as the api is slow to return results
                    if ($i < $this->holdCheckLimit && $this->holdCheckLimit != '0') {
                        $holdType = $this->determineHoldType(
                            $patron['id'],
                            $row['id'],
                            $row['item_id']
                        );
                        $addLink = $holdType ? $holdType : false;
                    } else {
                        $holdType = 'auto';
                        $addLink = 'check';
                    }
                } else {
                    $holdType = 'auto';
                }
            }

            if ($isStorageRetrievalRequestAllowed) {
                if ($patron) {
                    if (
                        $i < $this->callSlipCheckLimit
                        && $this->callSlipCheckLimit != '0'
                    ) {
                        $storageRetrieval = $this->checkItemRequests(
                            $patron['id'],
                            'callslip',
                            $row['id'],
                            $row['item_id']
                        );
                        $addStorageRetrievalLink = $storageRetrieval
                            ? true
                            : false;
                    } else {
                        $storageRetrieval = 'auto';
                        $addStorageRetrievalLink = 'check';
                    }
                } else {
                    $storageRetrieval = 'auto';
                }
            }

            $ILLRequest = '';
            $addILLRequestLink = false;
            // Check only that a patron has logged in
            if (null !== $patron && $isILLRequestAllowed) {
                $ILLRequest = 'auto';
                $addILLRequestLink = 'check';
            }

            $holding[$i] += [
                'is_holdable' => $is_holdable,
                'holdtype' => $holdType,
                'addLink' => $addLink,
                'level' => 'copy',
                'storageRetrievalRequest' => $storageRetrieval,
                'addStorageRetrievalRequestLink' => $addStorageRetrievalLink,
                'ILLRequest' => $ILLRequest,
                'addILLRequestLink' => $addILLRequestLink,
            ];
            unset($holding[$i]['_fullRow']);
        }
        return $holding;
    }

    /**
     * Check if request is valid
     *
     * This is responsible for determining if an item is requestable
     *
     * @param string $id     The Bib ID
     * @param array  $data   An Array of item data
     * @param array  $patron An array of patron data
     *
     * @return bool True if request is valid, false if not
     */
    public function checkRequestIsValid($id, $data, $patron)
    {
        $holdType = $data['holdtype'] ?? 'auto';
        $level = $data['level'] ?? 'copy';
        $mode = ('title' == $level) ? $this->titleHoldsMode : $this->holdsMode;
        if ('driver' == $mode && 'auto' == $holdType) {
            $itemID = $data['item_id'] ?? false;
            $result = $this->determineHoldType($patron['id'], $id, $itemID);
            if (!$result) {
                return false;
            }
        }

        if ('title' == $level && $this->requestGroupsEnabled) {
            // Verify that there are valid request groups
            if (!$this->getRequestGroups($id, $patron)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if storage retrieval request is valid
     *
     * This is responsible for determining if an item is requestable
     *
     * @param string $id     The Bib ID
     * @param array  $data   An Array of item data
     * @param array  $patron An array of patron data
     *
     * @return bool True if request is valid, false if not
     */
    public function checkStorageRetrievalRequestIsValid($id, $data, $patron)
    {
        if (
            !isset($this->config['StorageRetrievalRequests'])
            || $this->checkAccountBlocks($patron['id'])
        ) {
            return false;
        }

        $level = $data['level'] ?? 'copy';
        $itemID = ($level != 'title' && isset($data['item_id']))
            ? $data['item_id']
            : false;
        return $this->checkItemRequests($patron['id'], 'callslip', $id, $itemID);
    }

    /**
     * Protected support method for getMyTransactions.
     *
     * @param array $sqlRow An array of keyed data
     * @param array $patron An array of keyed patron data
     *
     * @return array Keyed data for display by template files
     */
    protected function processMyTransactionsData($sqlRow, $patron = false)
    {
        $transactions = parent::processMyTransactionsData($sqlRow, $patron);

        // We'll verify renewability later in getMyTransactions
        $transactions['renewable'] = true;

        return $transactions;
    }

    /**
     * Is the selected pickup location valid for the hold?
     *
     * @param string $pickUpLocation Selected pickup location
     * @param array  $patron         Patron information returned by the patronLogin
     * method.
     * @param array  $holdDetails    Details of hold being placed
     *
     * @return bool
     */
    protected function pickUpLocationIsValid($pickUpLocation, $patron, $holdDetails)
    {
        $pickUpLibs = $this->getPickUpLocations($patron, $holdDetails);
        foreach ($pickUpLibs as $location) {
            if ($location['locationID'] == $pickUpLocation) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get Pick Up Locations
     *
     * This is responsible for getting a list of valid library locations for
     * holds / recall retrieval
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
     * @throws ILSException
     * @return array        An array of associative arrays with locationID and
     * locationDisplay keys
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getPickUpLocations($patron = false, $holdDetails = null)
    {
        $pickResponse = [];
        $params = [];
        if ($this->ws_pickUpLocations) {
            foreach ($this->ws_pickUpLocations as $code => $library) {
                $pickResponse[] = [
                    'locationID' => $code,
                    'locationDisplay' => $library,
                ];
            }
        } else {
            if (
                $this->requestGroupsEnabled
                && $this->pickupLocationsInRequestGroup
                && !empty($holdDetails['requestGroupId'])
            ) {
                $sql = 'SELECT CIRC_POLICY_LOCS.LOCATION_ID as location_id, ' .
                    'NVL(LOCATION.LOCATION_DISPLAY_NAME, LOCATION.LOCATION_NAME) ' .
                    'as location_name from ' .
                    $this->dbName . ".CIRC_POLICY_LOCS, $this->dbName.LOCATION, " .
                    "$this->dbName.REQUEST_GROUP_LOCATION rgl " .
                    "where CIRC_POLICY_LOCS.PICKUP_LOCATION = 'Y' " .
                    'and CIRC_POLICY_LOCS.LOCATION_ID = LOCATION.LOCATION_ID ' .
                    'and rgl.GROUP_ID=:requestGroupId ' .
                    'and rgl.LOCATION_ID = LOCATION.LOCATION_ID';
                $params['requestGroupId'] = $holdDetails['requestGroupId'];
            } else {
                $sql = 'SELECT CIRC_POLICY_LOCS.LOCATION_ID as location_id, ' .
                    'NVL(LOCATION.LOCATION_DISPLAY_NAME, LOCATION.LOCATION_NAME) ' .
                    'as location_name from ' .
                    $this->dbName . ".CIRC_POLICY_LOCS, $this->dbName.LOCATION " .
                    "where CIRC_POLICY_LOCS.PICKUP_LOCATION = 'Y' " .
                    'and CIRC_POLICY_LOCS.LOCATION_ID = LOCATION.LOCATION_ID';
            }

            try {
                $sqlStmt = $this->executeSQL($sql, $params);
            } catch (PDOException $e) {
                $this->throwAsIlsException($e);
            }

            // Read results
            while ($row = $sqlStmt->fetch(PDO::FETCH_ASSOC)) {
                $pickResponse[] = [
                    'locationID' => $row['LOCATION_ID'],
                    'locationDisplay' => $this->utf8Encode($row['LOCATION_NAME']),
                ];
            }
        }

        // Do we need to sort pickup locations? If the setting is false, don't
        // bother doing any more work. If it's not set at all, default to
        // alphabetical order.
        $orderSetting = $this->config['Holds']['pickUpLocationOrder'] ?? 'default';
        if (count($pickResponse) > 1 && !empty($orderSetting)) {
            $locationOrder = $orderSetting === 'default'
                ? [] : array_flip(explode(':', $orderSetting));
            $sortFunction = function ($a, $b) use ($locationOrder) {
                $aLoc = $a['locationID'];
                $bLoc = $b['locationID'];
                if (isset($locationOrder[$aLoc])) {
                    if (isset($locationOrder[$bLoc])) {
                        return $locationOrder[$aLoc] - $locationOrder[$bLoc];
                    }
                    return -1;
                }
                if (isset($locationOrder[$bLoc])) {
                    return 1;
                }
                return $this->getSorter()->compare(
                    $a['locationDisplay'],
                    $b['locationDisplay']
                );
            };
            usort($pickResponse, $sortFunction);
        }

        return $pickResponse;
    }

    /**
     * Get Default Pick Up Location
     *
     * Returns the default pick up location set in VoyagerRestful.ini
     *
     * @param array $patron      Patron information returned by the patronLogin
     * method.
     * @param array $holdDetails Optional array, only passed in when getting a list
     * in the context of placing a hold; contains most of the same values passed to
     * placeHold, minus the patron data. May be used to limit the pickup options
     * or may be ignored.
     *
     * @return false|string      The default pickup location for the patron or false
     * if the user has to choose.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getDefaultPickUpLocation($patron = false, $holdDetails = null)
    {
        return $this->defaultPickUpLocation;
    }

    /**
     * Get Default Request Group
     *
     * Returns the default request group set in VoyagerRestful.ini
     *
     * @param array $patron      Patron information returned by the patronLogin
     * method.
     * @param array $holdDetails Optional array, only passed in when getting a list
     * in the context of placing a hold; contains most of the same values passed to
     * placeHold, minus the patron data. May be used to limit the request group
     * options or may be ignored.
     *
     * @return false|string      The default request group for the patron or false if
     * the user has to choose.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getDefaultRequestGroup($patron = false, $holdDetails = null)
    {
        return $this->defaultRequestGroup;
    }

    /**
     * Sort function for sorting request groups
     *
     * @param array $a Request group
     * @param array $b Request group
     *
     * @return number
     */
    protected function requestGroupSortFunction($a, $b)
    {
        $requestGroupOrder = isset($this->config['Holds']['requestGroupOrder'])
            ? explode(':', $this->config['Holds']['requestGroupOrder'])
            : [];
        $requestGroupOrder = array_flip($requestGroupOrder);
        if (isset($requestGroupOrder[$a['id']])) {
            if (isset($requestGroupOrder[$b['id']])) {
                return $requestGroupOrder[$a['id']] - $requestGroupOrder[$b['id']];
            }
            return -1;
        }
        if (isset($requestGroupOrder[$b['id']])) {
            return 1;
        }
        return $this->getSorter()->compare($a['name'], $b['name']);
    }

    /**
     * Get request groups
     *
     * @param int   $bibId       BIB ID
     * @param array $patron      Patron information returned by the patronLogin
     * method.
     * @param array $holdDetails Optional array, only passed in when getting a list
     * in the context of placing a hold; contains most of the same values passed to
     * placeHold, minus the patron data. May be used to limit the request group
     * options or may be ignored.
     *
     * @return array False if request groups not in use or an array of
     * associative arrays with id and name keys
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getRequestGroups($bibId, $patron, $holdDetails = null)
    {
        if (!$this->requestGroupsEnabled) {
            return false;
        }

        $sqlExpressions = [
            'rg.GROUP_ID',
            'rg.GROUP_NAME',
        ];
        $sqlFrom = [
            "$this->dbName.REQUEST_GROUP rg",

        ];
        $sqlWhere = [];
        $sqlBind = [];

        if ($this->pickupLocationsInRequestGroup) {
            // Limit to request groups that have valid pickup locations
            $sqlWhere[] = <<<EOT
                rg.GROUP_ID IN (
                  SELECT rgl.GROUP_ID
                  FROM $this->dbName.REQUEST_GROUP_LOCATION rgl
                  WHERE rgl.LOCATION_ID IN (
                    SELECT cpl.LOCATION_ID
                    FROM $this->dbName.CIRC_POLICY_LOCS cpl
                    WHERE cpl.PICKUP_LOCATION='Y'
                  )
                )
                EOT;
        }

        if ($this->checkItemsExist) {
            $sqlWhere[] = <<<EOT
                rg.GROUP_ID IN (
                  SELECT rgl.GROUP_ID
                  FROM $this->dbName.REQUEST_GROUP_LOCATION rgl
                  WHERE rgl.LOCATION_ID IN (
                    SELECT mm.LOCATION_ID FROM $this->dbName.MFHD_MASTER mm
                    WHERE mm.SUPPRESS_IN_OPAC='N'
                    AND mm.MFHD_ID IN (
                      SELECT mi.MFHD_ID
                      FROM $this->dbName.MFHD_ITEM mi, $this->dbName.BIB_ITEM bi
                      WHERE mi.ITEM_ID = bi.ITEM_ID AND bi.BIB_ID=:bibId
                    )
                  )
                )
                EOT;
            $sqlBind['bibId'] = $bibId;
        }

        if ($this->checkItemsNotAvailable) {
            // Build first the inner query that return item statuses for all request
            // groups
            $subExpressions = [
                'sub_rgl.GROUP_ID',
                'sub_i.ITEM_ID',
                'max(sub_ist.ITEM_STATUS) as STATUS',
            ];

            $subFrom = [
                "$this->dbName.ITEM_STATUS sub_ist",
                "$this->dbName.BIB_ITEM sub_bi",
                "$this->dbName.ITEM sub_i",
                "$this->dbName.REQUEST_GROUP_LOCATION sub_rgl",
                "$this->dbName.MFHD_ITEM sub_mi",
                "$this->dbName.MFHD_MASTER sub_mm",
            ];

            $subWhere = [
                'sub_bi.BIB_ID=:subBibId',
                'sub_i.ITEM_ID=sub_bi.ITEM_ID',
                'sub_ist.ITEM_ID=sub_i.ITEM_ID',
                'sub_mi.ITEM_ID=sub_i.ITEM_ID',
                'sub_mm.MFHD_ID=sub_mi.MFHD_ID',
                'sub_rgl.LOCATION_ID=sub_mm.LOCATION_ID',
                "sub_mm.SUPPRESS_IN_OPAC='N'",
            ];

            $subGroup = [
                'sub_rgl.GROUP_ID',
                'sub_i.ITEM_ID',
            ];

            $sqlBind['subBibId'] = $bibId;

            $subArray = [
                'expressions' => $subExpressions,
                'from' => $subFrom,
                'where' => $subWhere,
                'group' => $subGroup,
                'bind' => [],
            ];

            $subSql = $this->buildSqlFromArray($subArray);

            $itemWhere = <<<EOT
                rg.GROUP_ID NOT IN (
                  SELECT status.GROUP_ID
                  FROM ({$subSql['string']}) status
                  WHERE status.status=1
                )
                EOT;

            $key = 'disableAvailabilityCheckForRequestGroups';
            if (isset($this->config['Holds'][$key])) {
                $disabledGroups = array_map(
                    function ($s) {
                        return preg_replace('/[^\d]*/', '', $s);
                    },
                    explode(':', $this->config['Holds'][$key])
                );
                if ($disabledGroups) {
                    $itemWhere = "($itemWhere OR rg.GROUP_ID IN ("
                        . implode(',', $disabledGroups) . '))';
                }
            }
            $sqlWhere[] = $itemWhere;
        }

        $sqlArray = [
            'expressions' => $sqlExpressions,
            'from' => $sqlFrom,
            'where' => $sqlWhere,
            'bind' => $sqlBind,
        ];

        $sql = $this->buildSqlFromArray($sqlArray);

        try {
            $sqlStmt = $this->executeSQL($sql);
        } catch (PDOException $e) {
            $this->throwAsIlsException($e);
        }

        $results = [];
        while ($row = $sqlStmt->fetch(PDO::FETCH_ASSOC)) {
            $results[] = [
                'id' => $row['GROUP_ID'],
                'name' => $this->utf8Encode($row['GROUP_NAME']),
            ];
        }

        // Sort request groups
        usort($results, [$this, 'requestGroupSortFunction']);

        return $results;
    }

    /**
     * Make Request
     *
     * Makes a request to the Voyager Restful API
     *
     * @param array  $hierarchy Array of key-value pairs to embed in the URL path of
     * the request (set value to false to inject a non-paired value).
     * @param array  $params    A keyed array of query data
     * @param string $mode      The http request method to use (Default of GET)
     * @param string $xml       An optional XML string to send to the API
     *
     * @throws ILSException
     * @return obj  A Simple XML Object loaded with the xml data returned by the API
     */
    protected function makeRequest(
        $hierarchy,
        $params = false,
        $mode = 'GET',
        $xml = false
    ) {
        $hierarchyString = [];
        // Build Url Base
        $urlParams = "http://{$this->ws_host}:{$this->ws_port}/{$this->ws_app}";

        // Add Hierarchy
        foreach ($hierarchy as $key => $value) {
            $hierarchyString[] = ($value !== false)
                ? urlencode($key) . '/' . urlencode($value) : urlencode($key);
        }

        // Add Params
        $queryString = [];
        foreach ($params as $key => $param) {
            $queryString[] = urlencode($key) . '=' . urlencode($param);
        }

        // Build Hierarchy
        $urlParams .= '/' . implode('/', $hierarchyString);

        // Build Params
        $urlParams .= '?' . implode('&', $queryString);

        // Create Proxy Request
        $client = $this->httpService->createClient($urlParams);

        // Add any cookies
        if ($this->cookies) {
            $client->addCookie($this->cookies);
        }

        // Set timeout value
        $timeout = $this->config['Catalog']['http_timeout'] ?? 30;
        $client->setOptions(['timeout' => $timeout]);

        // Attach XML if necessary
        if ($xml !== false) {
            $client->setEncType('text/xml');
            $client->setRawBody($xml);
        }

        // Send Request and Retrieve Response
        $startTime = microtime(true);
        try {
            $result = $client->setMethod($mode)->send();
        } catch (\Exception $e) {
            $this->error(
                "$mode request for '$urlParams' with contents '$xml' failed: "
                . $e->getMessage()
            );
            throw new ILSException('Problem with RESTful API.');
        }
        if (!$result->isSuccess()) {
            $this->error(
                "$mode request for '$urlParams' with contents '$xml' failed: "
                . $result->getStatusCode() . ': ' . $result->getReasonPhrase()
            );
            throw new ILSException('Problem with RESTful API.');
        }

        // Store cookies
        $cookie = $result->getCookie();
        if ($cookie) {
            $this->cookies = $cookie;
        }

        // Process response
        $xmlResponse = $result->getBody();
        $this->debug(
            '[' . round(microtime(true) - $startTime, 4) . 's]'
            . " $mode request $urlParams, contents:" . PHP_EOL . $xml
            . PHP_EOL . 'response: ' . PHP_EOL
            . $xmlResponse
        );
        $oldLibXML = libxml_use_internal_errors();
        libxml_use_internal_errors(true);
        $simpleXML = simplexml_load_string($xmlResponse);
        libxml_use_internal_errors($oldLibXML);

        if ($simpleXML === false) {
            return false;
        }
        return $simpleXML;
    }

    /**
     * Encode a string for XML
     *
     * @param string $string String to be encoded
     *
     * @return string Encoded string
     */
    protected function encodeXML($string)
    {
        return htmlspecialchars($string, ENT_COMPAT, 'UTF-8');
    }

    /**
     * Build Basic XML
     *
     * Builds a simple xml string to send to the API
     *
     * @param array $xml A keyed array of xml node names and data
     *
     * @return string    An XML string
     */
    protected function buildBasicXML($xml)
    {
        $xmlString = '';

        foreach ($xml as $root => $nodes) {
            $xmlString .= '<' . $root . '>';

            foreach ($nodes as $nodeName => $nodeValue) {
                $xmlString .= '<' . $nodeName . '>';
                $xmlString .= $this->encodeXML($nodeValue);
                // Split out any attributes
                $nodeName = strtok($nodeName, ' ');
                $xmlString .= '</' . $nodeName . '>';
            }

            // Split out any attributes
            $root = strtok($root, ' ');
            $xmlString .= '</' . $root . '>';
        }

        $xmlComplete = '<?xml version="1.0" encoding="UTF-8"?>' . $xmlString;

        return $xmlComplete;
    }

    /**
     * Given the appropriate portion of the blocks API response, extract a list
     * of block reasons that VuFind is not configured to ignore.
     *
     * @param \SimpleXMLElement $borrowBlocks borrowingBlock section of XML response
     *
     * @return array
     */
    protected function extractBlockReasons($borrowBlocks)
    {
        $ignoredConfig = $this->config['Patron']['ignoredBlockCodes'] ?? '';
        $ignored = array_map('trim', explode(',', $ignoredConfig));
        $blockReason = [];
        foreach ($borrowBlocks as $borrowBlock) {
            if (!in_array((string)$borrowBlock->blockCode, $ignored)) {
                $blockReason[] = (string)$borrowBlock->blockReason;
            }
        }
        return $blockReason;
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
        return $this->checkAccountBlocks($patron['id']);
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
        return $this->checkAccountBlocks($patron['id']);
    }

    /**
     * Check Account Blocks
     *
     * Checks if a user has any blocks against their account which may prevent them
     * performing certain operations
     *
     * @param string $patronId A Patron ID
     *
     * @return mixed           A boolean false if no blocks are in place and an array
     * of block reasons if blocks are in place
     */
    protected function checkAccountBlocks($patronId)
    {
        $cacheId = "blocks|$patronId";
        $blockReason = $this->getCachedData($cacheId);
        if (null === $blockReason) {
            // Build Hierarchy
            $hierarchy = [
                'patron' =>  $patronId,
                'patronStatus' => 'blocks',
            ];

            // Add Required Params
            $params = [
                'patron_homedb' => $this->ws_patronHomeUbId,
                'view' => 'full',
            ];

            $blocks = $this->makeRequest($hierarchy, $params);
            if (
                $blocks
                && (string)$blocks->{'reply-text'} == 'ok'
                && isset($blocks->blocks->institution->borrowingBlock)
            ) {
                $blockReason = $this->extractBlockReasons(
                    $blocks->blocks->institution->borrowingBlock
                );
            } else {
                $blockReason = [];
            }
            $this->putCachedData($cacheId, $blockReason);
        }
        return empty($blockReason) ? false : $blockReason;
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
        $patron = $renewDetails['patron'];
        $finalResult = ['details' => []];

        // Get Account Blocks
        $finalResult['blocks'] = $this->checkAccountBlocks($patron['id']);

        if (!$finalResult['blocks']) {
            // Add Items and Attempt Renewal
            $itemIdentifiers = '';

            foreach ($renewDetails['details'] as $renewID) {
                [$dbKey, $loanId] = explode('|', $renewID);
                if (!$dbKey) {
                    $dbKey = $this->ws_dbKey;
                }

                $loanId = $this->encodeXML($loanId);
                $dbKey = $this->encodeXML($dbKey);

                $itemIdentifiers .= <<<EOT
                          <myac:itemIdentifier>
                           <myac:itemId>$loanId</myac:itemId>
                           <myac:ubId>$dbKey</myac:ubId>
                          </myac:itemIdentifier>
                    EOT;
            }

            $patronId = $this->encodeXML($patron['id']);
            $lastname = $this->encodeXML($patron['lastname']);
            $barcode = $this->encodeXML($patron['cat_username']);
            $localUbId = $this->encodeXML($this->ws_patronHomeUbId);

            // The RenewService has a weird prerequisite that
            // AuthenticatePatronService must be called first and JSESSIONID header
            // be preserved. There's no explanation why this is required, and a
            // quick check implies that RenewService works without it at least in
            // Voyager 8.1, but who knows if it fails with UB or something, so let's
            // try to play along with the rules.
            $xml = <<<EOT
                <?xml version="1.0" encoding="UTF-8"?>
                <ser:serviceParameters
                xmlns:ser="http://www.endinfosys.com/Voyager/serviceParameters">
                  <ser:patronIdentifier lastName="$lastname" patronHomeUbId="$localUbId">
                    <ser:authFactor type="B">$barcode</ser:authFactor>
                  </ser:patronIdentifier>
                </ser:serviceParameters>
                EOT;

            $response = $this->makeRequest(
                ['AuthenticatePatronService' => false],
                [],
                'POST',
                $xml
            );
            if ($response === false) {
                throw new ILSException('renew_error');
            }

            $xml = <<<EOT
                <?xml version="1.0" encoding="UTF-8"?>
                <ser:serviceParameters
                xmlns:ser="http://www.endinfosys.com/Voyager/serviceParameters">
                   <ser:parameters/>
                   <ser:definedParameters xsi:type="myac:myAccountServiceParametersType"
                   xmlns:myac="http://www.endinfosys.com/Voyager/myAccount"
                   xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
                $itemIdentifiers
                   </ser:definedParameters>
                  <ser:patronIdentifier lastName="$lastname" patronHomeUbId="$localUbId"
                  patronId="$patronId">
                    <ser:authFactor type="B">$barcode</ser:authFactor>
                  </ser:patronIdentifier>
                </ser:serviceParameters>
                EOT;

            $response = $this->makeRequest(
                ['RenewService' => false],
                [],
                'POST',
                $xml
            );
            if ($response === false) {
                throw new ILSException('renew_error');
            }

            // Process
            $myac_ns = 'http://www.endinfosys.com/Voyager/myAccount';
            $response->registerXPathNamespace(
                'ser',
                'http://www.endinfosys.com/Voyager/serviceParameters'
            );
            $response->registerXPathNamespace('myac', $myac_ns);
            // The service doesn't actually return messages (in Voyager 8.1),
            // but maybe in the future...
            foreach ($response->xpath('//ser:message') as $message) {
                if (
                    $message->attributes()->type == 'system'
                    || $message->attributes()->type == 'error'
                ) {
                    return false;
                }
            }
            foreach ($response->xpath('//myac:clusterChargedItems') as $cluster) {
                $cluster = $cluster->children($myac_ns);
                $dbKey = (string)$cluster->cluster->ubSiteId;
                foreach ($cluster->chargedItem as $chargedItem) {
                    $chargedItem = $chargedItem->children($myac_ns);
                    $renewStatus = $chargedItem->renewStatus;
                    if (!$renewStatus) {
                        continue;
                    }
                    $renewed = false;
                    foreach ($renewStatus->status as $status) {
                        if ((string)$status == 'Renewed') {
                            $renewed = true;
                        }
                    }

                    $result = [];
                    $result['item_id'] = (string)$chargedItem->itemId;
                    $result['sysMessage'] = (string)$renewStatus->status;

                    $dueDate = (string)$chargedItem->dueDate;
                    try {
                        $newDate = $this->dateFormat->convertToDisplayDate(
                            'Y-m-d H:i',
                            $dueDate
                        );
                        $response['new_date'] = $newDate;
                    } catch (DateException $e) {
                        // If we can't parse out the date, use the raw string:
                        $response['new_date'] = $dueDate;
                    }
                    try {
                        $newTime = $this->dateFormat->convertToDisplayTime(
                            'Y-m-d H:i',
                            $dueDate
                        );
                        $response['new_time'] = $newTime;
                    } catch (DateException $e) {
                        // If we can't parse out the time, just ignore it:
                        $response['new_time'] = false;
                    }
                    $result['success'] = $renewed;

                    $finalResult['details'][$result['item_id']] = $result;
                }
            }
        }
        return $finalResult;
    }

    /**
     * Check Item Requests
     *
     * Determines if a user can place a hold or recall on a specific item
     *
     * @param string $patronId The user's Patron ID
     * @param string $request  The request type (hold or recall)
     * @param string $bibId    An item's Bib ID
     * @param string $itemId   An item's Item ID (optional)
     *
     * @return bool true if the request can be made, false if it cannot
     */
    protected function checkItemRequests(
        $patronId,
        $request,
        $bibId,
        $itemId = false
    ) {
        if (!empty($bibId) && !empty($patronId) && !empty($request)) {
            $hierarchy = [];

            // Build Hierarchy
            $hierarchy['record'] = $bibId;

            if ($itemId) {
                $hierarchy['items'] = $itemId;
            }

            $hierarchy[$request] = false;

            // Add Required Params
            $params = [
                'patron' => $patronId,
                'patron_homedb' => $this->ws_patronHomeUbId,
                'view' => 'full',
            ];

            $check = $this->makeRequest($hierarchy, $params, 'GET', false);

            if ($check) {
                // Process
                $check = $check->children();
                $node = 'reply-text';
                $reply = (string)$check->$node;

                // Valid Response
                if ($reply == 'ok') {
                    if ($check->$request) {
                        $requestAttributes = $check->$request->attributes();
                        if ($requestAttributes['allowed'] == 'Y') {
                            return true;
                        }
                    }
                }
            }
        }
        return false;
    }

    /**
     * Make Item Requests
     *
     * Places a Hold or Recall for a particular title or item
     *
     * @param string $patron      Patron information from patronLogin
     * @param string $type        The request type (hold or recall)
     * @param array  $requestData An array of parameters to submit with the request
     *
     * @return array             An array of data from the attempted request
     * including success, status and a System Message (if available)
     */
    protected function makeItemRequests(
        $patron,
        $type,
        $requestData
    ) {
        if (
            empty($patron) || empty($requestData) || empty($requestData['bibId'])
            || empty($type)
        ) {
            return ['success' => false, 'status' => 'hold_error_fail'];
        }

        // Build request
        $patronId = htmlspecialchars($patron['id'], ENT_COMPAT, 'UTF-8');
        $lastname = htmlspecialchars($patron['lastname'], ENT_COMPAT, 'UTF-8');
        $barcode = htmlspecialchars($patron['cat_username'], ENT_COMPAT, 'UTF-8');
        $localUbId = htmlspecialchars($this->ws_patronHomeUbId, ENT_COMPAT, 'UTF-8');
        $type = strtoupper($type);
        $cval = 'anyCopy';
        if (isset($requestData['itemId'])) {
            $cval = 'thisCopy';
        } elseif (isset($requestData['requestGroupId'])) {
            $cval = 'anyCopyAt';
        }

        // Build request
        $xml = <<<EOT
            <?xml version="1.0" encoding="UTF-8"?>
            <ser:serviceParameters
              xmlns:ser="http://www.endinfosys.com/Voyager/serviceParameters">
              <ser:parameters>
                <ser:parameter key="bibDbCode">
                  <ser:value>LOCAL</ser:value>
                </ser:parameter>
                <ser:parameter key="requestCode">
                  <ser:value>$type</ser:value>
                </ser:parameter>
                <ser:parameter key="requestSiteId">
                  <ser:value>$localUbId</ser:value>
                </ser:parameter>
                <ser:parameter key="CVAL">
                  <ser:value>$cval</ser:value>
                </ser:parameter>

            EOT;
        foreach ($requestData as $key => $value) {
            $value = htmlspecialchars($value, ENT_COMPAT, 'UTF-8');
            $xml .= <<<EOT
                    <ser:parameter key="$key">
                      <ser:value>$value</ser:value>
                    </ser:parameter>

                EOT;
        }
        $xml .= <<<EOT
              </ser:parameters>
              <ser:patronIdentifier lastName="$lastname" patronHomeUbId="$localUbId"
                patronId="$patronId">
                <ser:authFactor type="B">$barcode</ser:authFactor>
              </ser:patronIdentifier>
            </ser:serviceParameters>
            EOT;

        $response = $this->makeRequest(
            ['SendPatronRequestService' => false],
            [],
            'POST',
            $xml
        );

        if ($response === false) {
            return $this->holdError('hold_error_system');
        }
        // Process
        $response->registerXPathNamespace(
            'ser',
            'http://www.endinfosys.com/Voyager/serviceParameters'
        );
        $response->registerXPathNamespace(
            'req',
            'http://www.endinfosys.com/Voyager/requests'
        );
        foreach ($response->xpath('//ser:message') as $message) {
            if ($message->attributes()->type == 'success') {
                return [
                    'success' => true,
                    'status' => 'hold_request_success',
                ];
            }
            if ($message->attributes()->type == 'system') {
                return $this->holdError('hold_error_system');
            }
        }

        return $this->holdError('hold_error_blocked');
    }

    /**
     * Determine Hold Type
     *
     * Determines if a user can place a hold or recall on a particular item
     *
     * @param string $patronId The user's Patron ID
     * @param string $bibId    An item's Bib ID
     * @param string $itemId   An item's Item ID (optional)
     *
     * @return string          The name of the request method to use or false on
     * failure
     */
    protected function determineHoldType($patronId, $bibId, $itemId = false)
    {
        if ($itemId && !$this->itemHoldsEnabled) {
            return false;
        }

        // Check for account Blocks
        if ($this->checkAccountBlocks($patronId)) {
            return false;
        }

        // Check Recalls First
        if ($this->recallsEnabled) {
            $recall = $this->checkItemRequests($patronId, 'recall', $bibId, $itemId);
            if ($recall) {
                return 'recall';
            }
        }
        // Check Holds
        $hold = $this->checkItemRequests($patronId, 'hold', $bibId, $itemId);
        if ($hold) {
            return 'hold';
        }
        return false;
    }

    /**
     * Hold Error
     *
     * Returns a Hold Error Message
     *
     * @param string $msg An error message string
     *
     * @return array An array with a success (boolean) and sysMessage key
     */
    protected function holdError($msg)
    {
        return [
            'success' => false,
            'sysMessage' => $msg,
        ];
    }

    /**
     * Check whether the given patron has the given bib record or its item on loan.
     *
     * @param int $patronId Patron ID
     * @param int $bibId    Bib ID
     * @param int $itemId   Item ID (optional)
     *
     * @return bool
     */
    protected function isRecordOnLoan($patronId, $bibId, $itemId = null)
    {
        $sqlExpressions = [
            'count(cta.ITEM_ID) CNT',
        ];

        $sqlFrom = [
            "$this->dbName.BIB_ITEM bi",
            "$this->dbName.CIRC_TRANSACTIONS cta",
        ];

        $sqlWhere = [
            'cta.PATRON_ID=:patronId',
            'bi.BIB_ID=:bibId',
            'bi.ITEM_ID=cta.ITEM_ID',
        ];

        if ($this->requestGroupsEnabled) {
            $sqlFrom[] = "$this->dbName.REQUEST_GROUP_LOCATION rgl";
            $sqlFrom[] = "$this->dbName.MFHD_ITEM mi";
            $sqlFrom[] = "$this->dbName.MFHD_MASTER mm";

            $sqlWhere[] = 'mi.ITEM_ID=cta.ITEM_ID';
            $sqlWhere[] = 'mm.MFHD_ID=mi.MFHD_ID';
            $sqlWhere[] = 'rgl.LOCATION_ID=mm.LOCATION_ID';
            $sqlWhere[] = "mm.SUPPRESS_IN_OPAC='N'";
        }

        $sqlBind = ['patronId' => $patronId, 'bibId' => $bibId];

        if (null !== $itemId) {
            $sqlWhere[] = 'cta.ITEM_ID=:itemId';
            $sqlBind['itemId'] = $itemId;
        }

        $sqlArray = [
            'expressions' => $sqlExpressions,
            'from' => $sqlFrom,
            'where' => $sqlWhere,
            'bind' => $sqlBind,
        ];

        $sql = $this->buildSqlFromArray($sqlArray);

        try {
            $sqlStmt = $this->executeSQL($sql);
            $sqlRow = $sqlStmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $this->throwAsIlsException($e);
        }
        return $sqlRow['CNT'] > 0;
    }

    /**
     * Check whether items exist for the given BIB ID
     *
     * @param int  $bibId          BIB ID
     * @param ?int $requestGroupId Request group ID or null
     *
     * @return bool
     */
    protected function itemsExist($bibId, ?int $requestGroupId = null)
    {
        $sqlExpressions = [
            'count(i.ITEM_ID) CNT',
        ];

        $sqlFrom = [
            "$this->dbName.BIB_ITEM bi",
            "$this->dbName.ITEM i",
            "$this->dbName.MFHD_ITEM mi",
            "$this->dbName.MFHD_MASTER mm",
        ];

        $sqlWhere = [
            'bi.BIB_ID=:bibId',
            'i.ITEM_ID=bi.ITEM_ID',
            'mi.ITEM_ID=i.ITEM_ID',
            'mm.MFHD_ID=mi.MFHD_ID',
            "mm.SUPPRESS_IN_OPAC='N'",
        ];

        if ($this->excludedItemLocations) {
            $sqlWhere[] = 'mm.LOCATION_ID not in (' . $this->excludedItemLocations .
                ')';
        }

        $sqlBind = ['bibId' => $bibId];

        if ($this->requestGroupsEnabled && isset($requestGroupId)) {
            $sqlFrom[] = "$this->dbName.REQUEST_GROUP_LOCATION rgl";

            $sqlWhere[] = 'rgl.LOCATION_ID=mm.LOCATION_ID';
            $sqlWhere[] = 'rgl.GROUP_ID=:requestGroupId';

            $sqlBind['requestGroupId'] = $requestGroupId;
        }

        $sqlArray = [
            'expressions' => $sqlExpressions,
            'from' => $sqlFrom,
            'where' => $sqlWhere,
            'bind' => $sqlBind,
        ];

        $sql = $this->buildSqlFromArray($sqlArray);
        try {
            $sqlStmt = $this->executeSQL($sql);
            $sqlRow = $sqlStmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $this->throwAsIlsException($e);
        }
        return $sqlRow['CNT'] > 0;
    }

    /**
     * Check whether there are items available for loan for the given BIB ID
     *
     * @param int  $bibId          BIB ID
     * @param ?int $requestGroupId Request group ID or null
     *
     * @return bool
     */
    protected function itemsAvailable($bibId, ?int $requestGroupId = null)
    {
        // Build inner query first
        $sqlExpressions = [
            'i.ITEM_ID',
            'max(ist.ITEM_STATUS) as STATUS',
        ];

        $sqlFrom = [
            "$this->dbName.ITEM_STATUS ist",
            "$this->dbName.BIB_ITEM bi",
            "$this->dbName.ITEM i",
            "$this->dbName.MFHD_ITEM mi",
            "$this->dbName.MFHD_MASTER mm",
        ];

        $sqlWhere = [
            'bi.BIB_ID=:bibId',
            'i.ITEM_ID=bi.ITEM_ID',
            'ist.ITEM_ID=i.ITEM_ID',
            'mi.ITEM_ID=i.ITEM_ID',
            'mm.MFHD_ID=mi.MFHD_ID',
            "mm.SUPPRESS_IN_OPAC='N'",
        ];

        if ($this->excludedItemLocations) {
            $sqlWhere[] = 'mm.LOCATION_ID not in (' . $this->excludedItemLocations .
                ')';
        }

        $sqlGroup = [
            'i.ITEM_ID',
        ];

        $sqlBind = ['bibId' => $bibId];

        if ($this->requestGroupsEnabled && isset($requestGroupId)) {
            $sqlFrom[] = "$this->dbName.REQUEST_GROUP_LOCATION rgl";

            $sqlWhere[] = 'rgl.LOCATION_ID=mm.LOCATION_ID';
            $sqlWhere[] = 'rgl.GROUP_ID=:requestGroupId';

            $sqlBind['requestGroupId'] = $requestGroupId;
        }

        $sqlArray = [
            'expressions' => $sqlExpressions,
            'from' => $sqlFrom,
            'where' => $sqlWhere,
            'group' => $sqlGroup,
            'bind' => $sqlBind,
        ];

        $sql = $this->buildSqlFromArray($sqlArray);
        $outersql = "select count(avail.item_id) CNT from ({$sql['string']}) avail" .
            ' where avail.STATUS=1'; // 1 = not charged

        try {
            $sqlStmt = $this->executeSQL($outersql, $sql['bind']);
            $sqlRow = $sqlStmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $this->throwAsIlsException($e);
        }
        return $sqlRow['CNT'] > 0;
    }

    /**
     * Protected support method for getMyHolds.
     *
     * Fetch both local and remote holds. Remote hold data will be augmented using
     * the API.
     *
     * @param array $patron Patron data for use in an sql query
     *
     * @return array Keyed data for use in an sql query
     */
    protected function getMyHoldsSQL($patron)
    {
        // Most of our SQL settings will be identical to the parent class....
        $sqlArray = parent::getMyHoldsSQL($patron);

        // Add remote holds; MFHD_ITEM and BIB_TEXT entries will be bogus for these,
        // but we'll deal with them later in getMyHolds()
        $sqlArray['expressions'][]
            = "NVL(VOYAGER_DATABASES.DB_CODE, 'LOCAL') as DB_CODE";

        // We need to significantly change the where clauses to account for remote
        // holds
        $sqlArray['where'] = [
            'HOLD_RECALL.PATRON_ID = :id',
            'HOLD_RECALL.HOLD_RECALL_ID = HOLD_RECALL_ITEMS.HOLD_RECALL_ID(+)',
            'HOLD_RECALL_ITEMS.ITEM_ID = MFHD_ITEM.ITEM_ID(+)',
            '(HOLD_RECALL_ITEMS.HOLD_RECALL_STATUS IS NULL OR ' .
            'HOLD_RECALL_ITEMS.HOLD_RECALL_STATUS < 3)',
            'HOLD_RECALL.BIB_ID = BIB_TEXT.BIB_ID(+)',
            'HOLD_RECALL.REQUEST_GROUP_ID = REQUEST_GROUP.GROUP_ID(+)',
            'HOLD_RECALL.HOLDING_DB_ID = VOYAGER_DATABASES.DB_ID(+)',
        ];

        return $sqlArray;
    }

    /**
     * Protected support method for getMyHolds.
     *
     * @param array $sqlRow An array of keyed data
     *
     * @throws DateException
     * @return array Keyed data for display by template files
     */
    protected function processMyHoldsData($sqlRow)
    {
        $result = parent::processMyHoldsData($sqlRow);
        $result['db_code'] = $sqlRow['DB_CODE'];
        return $result;
    }

    /**
     * Get Patron Holds
     *
     * This is responsible for retrieving all holds by a specific patron.
     *
     * @param array $patron The patron array from patronLogin
     *
     * @throws DateException
     * @throws ILSException
     * @return array        Array of the patron's holds on success.
     */
    public function getMyHolds($patron)
    {
        $holds = parent::getMyHolds($patron);
        // Check if we have remote holds and augment if necessary
        $augment = false;
        foreach ($holds as $hold) {
            if ($hold['db_code'] != 'LOCAL') {
                $augment = true;
                break;
            }
        }
        if ($augment) {
            // Fetch hold information via the API so that we can include correct
            // title etc. for remote holds.
            $copyFields = [
                'id', 'item_id', 'volume', 'publication_year', 'title',
                'institution_id', 'institution_name',
                'institution_dbkey', 'in_transit',
            ];
            $apiHolds = $this->getHoldsFromApi($patron, true);
            foreach ($apiHolds as $apiHold) {
                // Find the hold and add information to it
                foreach ($holds as &$hold) {
                    if ($hold['reqnum'] == $apiHold['reqnum']) {
                        // Ignore local holds
                        if ($hold['db_code'] == 'LOCAL') {
                            continue 2;
                        }
                        foreach ($copyFields as $field) {
                            $hold[$field] = $apiHold[$field] ?? '';
                        }
                        break;
                    }
                }
            }
        }
        return $holds;
    }

    /**
     * Place Hold
     *
     * Attempts to place a hold or recall on a particular item and returns
     * an array with result details or throws an exception on failure of support
     * classes
     *
     * @param array $holdDetails An array of item and patron data
     *
     * @throws ILSException
     * @return mixed An array of data on the request including
     * whether or not it was successful and a system message (if available)
     */
    public function placeHold($holdDetails)
    {
        $patron = $holdDetails['patron'];
        $type = isset($holdDetails['holdtype']) && !empty($holdDetails['holdtype'])
            ? $holdDetails['holdtype'] : 'auto';
        $level = isset($holdDetails['level']) && !empty($holdDetails['level'])
            ? $holdDetails['level'] : 'copy';
        $pickUpLocation = !empty($holdDetails['pickUpLocation'])
            ? $holdDetails['pickUpLocation'] : $this->defaultPickUpLocation;
        $itemId = $holdDetails['item_id'] ?? false;
        $comment = $holdDetails['comment'] ?? '';
        $bibId = $holdDetails['id'];

        // Request was initiated before patron was logged in -
        // Let's determine Hold Type now
        if ($type == 'auto') {
            $type = $this->determineHoldType($patron['id'], $bibId, $itemId);
            if (!$type) {
                return $this->holdError('hold_error_blocked');
            }
        }

        // Convert last interest date from Display Format to Voyager required format
        try {
            $lastInterestDate = $this->dateFormat->convertFromDisplayDate(
                'Y-m-d',
                $holdDetails['requiredBy']
            );
        } catch (DateException $e) {
            // Hold Date is invalid
            return $this->holdError('hold_date_invalid');
        }

        try {
            $checkTime = $this->dateFormat->convertFromDisplayDate(
                'U',
                $holdDetails['requiredBy']
            );
            if (!is_numeric($checkTime)) {
                throw new DateException('Result should be numeric');
            }
        } catch (DateException $e) {
            $this->throwAsIlsException($e, 'Problem parsing required by date.');
        }

        if (time() > $checkTime) {
            // Hold Date is in the past
            return $this->holdError('hold_date_past');
        }

        // Make Sure Pick Up Library is Valid
        if (!$this->pickUpLocationIsValid($pickUpLocation, $patron, $holdDetails)) {
            return $this->holdError('hold_invalid_pickup');
        }

        if (
            $this->requestGroupsEnabled && !$itemId
            && empty($holdDetails['requestGroupId'])
        ) {
            return $this->holdError('hold_invalid_request_group');
        }

        // Optional check that the bib has items
        if ($this->checkItemsExist) {
            $exist = $this->itemsExist(
                $bibId,
                $holdDetails['requestGroupId'] ?? null
            );
            if (!$exist) {
                return $this->holdError('hold_no_items');
            }
        }

        // Optional check that the bib has no available items
        if ($this->checkItemsNotAvailable) {
            $disabledGroups = [];
            $key = 'disableAvailabilityCheckForRequestGroups';
            if (isset($this->config['Holds'][$key])) {
                $disabledGroups = explode(':', $this->config['Holds'][$key]);
            }
            if (
                !isset($holdDetails['requestGroupId'])
                || !in_array($holdDetails['requestGroupId'], $disabledGroups)
            ) {
                $available = $this->itemsAvailable(
                    $bibId,
                    $holdDetails['requestGroupId'] ?? null
                );
                if ($available) {
                    return $this->holdError('hold_items_available');
                }
            }
        }

        // Optional check that the patron doesn't already have the bib on loan
        if ($this->checkLoans) {
            $checkItemId = $this->checkLoans === 'same-item' && $level == 'copy'
                && $itemId ? $itemId : null;
            if ($this->isRecordOnLoan($patron['id'], $bibId, $checkItemId)) {
                return $this->holdError('hold_record_already_on_loan');
            }
        }

        // Build Request Data
        $requestData = [
            'bibId' => $bibId,
            'PICK' => $pickUpLocation,
            'REQNNA' => $lastInterestDate,
            'REQCOMMENTS' => $comment,
        ];
        if ($level == 'copy' && $itemId) {
            $requestData['itemId'] = $itemId;
        } elseif (isset($holdDetails['requestGroupId'])) {
            $requestData['requestGroupId'] = $holdDetails['requestGroupId'];
        }

        // Attempt Request
        $result = $this->makeItemRequests($patron, $type, $requestData);
        if ($result) {
            return $result;
        }

        return $this->holdError('hold_error_blocked');
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
        $details = $cancelDetails['details'];
        $patron = $cancelDetails['patron'];
        $count = 0;
        $response = [];

        foreach ($details as $cancelDetails) {
            [$itemId, $cancelCode] = explode('|', $cancelDetails);

            // Create Rest API Cancel Key
            $cancelID = $this->ws_dbKey . '|' . $cancelCode;

            // Build Hierarchy
            $hierarchy = [
                'patron' => $patron['id'],
                 'circulationActions' => 'requests',
                 'holds' => $cancelID,
            ];

            // Add Required Params
            $params = [
                'patron_homedb' => $this->ws_patronHomeUbId,
                'view' => 'full',
            ];

            // Get Data
            $cancel = $this->makeRequest($hierarchy, $params, 'DELETE');

            if ($cancel) {
                // Process Cancel
                $cancel = $cancel->children();
                $node = 'reply-text';
                $reply = (string)$cancel->$node;
                $count = ($reply == 'ok') ? $count + 1 : $count;

                $response[$itemId] = [
                    'success' => ($reply == 'ok') ? true : false,
                    'status' => ($reply == 'ok')
                        ? 'hold_cancel_success' : 'hold_cancel_fail',
                    'sysMessage' => ($reply == 'ok') ? false : $reply,
                ];
            } else {
                $response[$itemId] = [
                    'success' => false, 'status' => 'hold_cancel_fail',
                ];
            }
        }
        $result = ['count' => $count, 'items' => $response];
        return $result;
    }

    /**
     * Get Cancel Hold Details
     *
     * In order to cancel a hold, Voyager requires the patron details an item ID
     * and a recall ID. This function returns the item id and recall id as a string
     * separated by a pipe, which is then submitted as form data in Hold.php. This
     * value is then extracted by the CancelHolds function.
     *
     * @param array $holdDetails A single hold array from getMyHolds
     * @param array $patron      Patron information from patronLogin
     *
     * @return string Data for use in a form field
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getCancelHoldDetails($holdDetails, $patron = [])
    {
        if (!$this->allowCancelingAvailableRequests && $holdDetails['available']) {
            return '';
        }
        return $holdDetails['item_id'] . '|' . $holdDetails['reqnum'];
    }

    /**
     * Get Renew Details
     *
     * In order to renew an item, Voyager requires the patron details and an item
     * id. This function returns the item id as a string which is then used
     * as submitted form data in checkedOut.php. This value is then extracted by
     * the RenewMyItems function.
     *
     * @param array $checkOutDetails An array of item data
     *
     * @return string Data for use in a form field
     */
    public function getRenewDetails($checkOutDetails)
    {
        $renewDetails = ($checkOutDetails['institution_dbkey'] ?? '')
            . '|' . $checkOutDetails['item_id'];
        return $renewDetails;
    }

    /**
     * Get Patron Transactions
     *
     * This is responsible for retrieving all transactions (i.e. checked out items)
     * by a specific patron.
     *
     * @param array $patron The patron array from patronLogin
     *
     * @throws ILSException
     * @return mixed        Array of the patron's transactions on success.
     */
    public function getMyTransactions($patron)
    {
        // Get local loans from the database so that we can get more details
        // than available via the API.
        $transactions = parent::getMyTransactions($patron);

        // Get remote loans and renewability for local loans via the API

        // Build Hierarchy
        $hierarchy = [
            'patron' =>  $patron['id'],
            'circulationActions' => 'loans',
        ];

        // Add Required Params
        $params = [
            'patron_homedb' => $this->ws_patronHomeUbId,
            'view' => 'full',
        ];

        $results = $this->makeRequest($hierarchy, $params);

        if ($results === false) {
            throw new ILSException('System error fetching loans');
        }

        $replyCode = (string)$results->{'reply-code'};
        if ($replyCode != 0 && $replyCode != 8) {
            throw new ILSException('System error fetching loans');
        }
        if (isset($results->loans->institution)) {
            foreach ($results->loans->institution as $institution) {
                foreach ($institution->loan as $loan) {
                    if ($this->isLocalInst((string)$institution->attributes()->id)) {
                        // Take only renewability for local loans, other information
                        // we have already
                        $renewable = (string)$loan->attributes()->canRenew == 'Y';

                        foreach ($transactions as &$transaction) {
                            if (
                                !isset($transaction['institution_id'])
                                && $transaction['item_id'] == (string)$loan->itemId
                            ) {
                                $transaction['renewable'] = $renewable;
                                break;
                            }
                        }
                        continue;
                    }

                    $dueStatus = false;
                    $now = time();
                    $dueTimeStamp = strtotime((string)$loan->dueDate);
                    if ($dueTimeStamp !== false && is_numeric($dueTimeStamp)) {
                        if ($now > $dueTimeStamp) {
                            $dueStatus = 'overdue';
                        } elseif ($now > $dueTimeStamp - (1 * 24 * 60 * 60)) {
                            $dueStatus = 'due';
                        }
                    }

                    try {
                        $dueDate = $this->dateFormat->convertToDisplayDate(
                            'Y-m-d H:i',
                            (string)$loan->dueDate
                        );
                    } catch (DateException $e) {
                        // If we can't parse out the date, use the raw string:
                        $dueDate = (string)$loan->dueDate;
                    }

                    try {
                        $dueTime = $this->dateFormat->convertToDisplayTime(
                            'Y-m-d H:i',
                            (string)$loan->dueDate
                        );
                    } catch (DateException $e) {
                        // If we can't parse out the time, just ignore it:
                        $dueTime = false;
                    }

                    $transactions[] = [
                        // This is bogus, but we need something..
                        'id' => (string)$institution->attributes()->id . '_' .
                                (string)$loan->itemId,
                        'item_id' => (string)$loan->itemId,
                        'duedate' => $dueDate,
                        'dueTime' => $dueTime,
                        'dueStatus' => $dueStatus,
                        'title' => (string)$loan->title,
                        'renewable' => (string)$loan->attributes()->canRenew == 'Y',
                        'institution_id' => (string)$institution->attributes()->id,
                        'institution_name' => (string)$loan->dbName,
                        'institution_dbkey' => (string)$loan->dbKey,
                    ];
                }
            }
        }
        return $transactions;
    }

    /**
     * Get patron's local or remote holds from the API
     *
     * This is responsible for retrieving all local or remote holds by a specific
     * patron.
     *
     * @param array $patron The patron array from patronLogin
     * @param bool  $local  Whether to fetch local holds instead of remote holds
     *
     * @throws DateException
     * @throws ILSException
     * @return array        Array of the patron's holds on success.
     */
    protected function getHoldsFromApi($patron, $local)
    {
        // Build Hierarchy
        $hierarchy = [
            'patron' =>  $patron['id'],
            'circulationActions' => 'requests',
            'holds' => false,
        ];

        // Add Required Params
        $params = [
            'patron_homedb' => $this->ws_patronHomeUbId,
            'view' => 'full',
        ];

        $results = $this->makeRequest($hierarchy, $params);

        if ($results === false) {
            throw new ILSException('System error fetching remote holds');
        }

        $replyCode = (string)$results->{'reply-code'};
        if ($replyCode != 0 && $replyCode != 8) {
            throw new ILSException('System error fetching remote holds');
        }
        $holds = [];
        if (isset($results->holds->institution)) {
            foreach ($results->holds->institution as $institution) {
                // Filter by the $local parameter
                $isLocal = $this->isLocalInst(
                    (string)$institution->attributes()->id
                );
                if ($local != $isLocal) {
                    continue;
                }

                foreach ($institution->hold as $hold) {
                    $item = $hold->requestItem;

                    $holds[] = [
                        'id' => '',
                        'type' => (string)$item->holdType,
                        'location' => (string)$item->pickupLocation,
                        'expire' => (string)$item->expiredDate
                            ? $this->dateFormat->convertToDisplayDate(
                                'Y-m-d',
                                (string)$item->expiredDate
                            )
                            : '',
                        // Looks like expired date shows creation date for
                        // UB requests, but who knows
                        'create' => (string)$item->expiredDate
                            ? $this->dateFormat->convertToDisplayDate(
                                'Y-m-d',
                                (string)$item->expiredDate
                            )
                            : '',
                        'position' => (string)$item->queuePosition,
                        'available' => (string)$item->status == '2',
                        'reqnum' => (string)$item->holdRecallId,
                        'item_id' => (string)$item->itemId,
                        'volume' => '',
                        'publication_year' => '',
                        'title' => (string)$item->itemTitle,
                        'institution_id' => (string)$institution->attributes()->id,
                        'institution_name' => (string)$item->dbName,
                        'institution_dbkey' => (string)$item->dbKey,
                        'in_transit' => str_starts_with((string)$item->statusText, 'In transit to')
                            ? substr((string)$item->statusText, 14)
                            : '',
                    ];
                }
            }
        }
        return $holds;
    }

    /**
     * Get Patron Storage Retrieval Requests (Call Slips). Gets callslips via
     * the API. Returns only remote slips by default since more complete data
     * can be retrieved directly from the local database; however, the $local
     * parameter exists to support potential local customizations.
     *
     * @param array $patron The patron array from patronLogin
     * @param bool  $local  Whether to include local callslips
     *
     * @return mixed        Array of the patron's storage retrieval requests.
     */
    protected function getCallSlips($patron, $local = false)
    {
        // Build Hierarchy
        $hierarchy = [
            'patron' =>  $patron['id'],
            'circulationActions' => 'requests',
            'callslips' => false,
        ];

        // Add Required Params
        $params = [
            'patron_homedb' => $this->ws_patronHomeUbId,
            'view' => 'full',
        ];

        $results = $this->makeRequest($hierarchy, $params);

        $replyCode = (string)$results->{'reply-code'};
        if ($replyCode != 0 && $replyCode != 8) {
            throw new \Exception('System error fetching call slips');
        }
        $requests = [];
        if (isset($results->callslips->institution)) {
            foreach ($results->callslips->institution as $institution) {
                if (
                    !$local
                    && $this->isLocalInst((string)$institution->attributes()->id)
                ) {
                    // Unless $local is set, ignore local callslips; we have them
                    // already....
                    continue;
                }
                foreach ($institution->callslip as $callslip) {
                    $item = $callslip->requestItem;
                    $requests[] = [
                        'id' => '',
                        'type' => (string)$item->holdType,
                        'location' => (string)$item->pickupLocation,
                        'expire' => (string)$item->expiredDate
                            ? $this->dateFormat->convertToDisplayDate(
                                'Y-m-d',
                                (string)$item->expiredDate
                            )
                            : '',
                        // Looks like expired date shows creation date for
                        // call slip requests, but who knows
                        'create' => (string)$item->expiredDate
                            ? $this->dateFormat->convertToDisplayDate(
                                'Y-m-d',
                                (string)$item->expiredDate
                            )
                            : '',
                        'position' => (string)$item->queuePosition,
                        'available' => (string)$item->status == '4',
                        'reqnum' => (string)$item->holdRecallId,
                        'item_id' => (string)$item->itemId,
                        'volume' => '',
                        'publication_year' => '',
                        'title' => (string)$item->itemTitle,
                        'institution_id' => (string)$institution->attributes()->id,
                        'institution_name' => (string)$item->dbName,
                        'institution_dbkey' => (string)$item->dbKey,
                        'processed' => str_starts_with((string)$item->statusText, 'Filled')
                            ? $this->dateFormat->convertToDisplayDate(
                                'Y-m-d',
                                substr((string)$item->statusText, 7)
                            )
                            : '',
                        'canceled' => str_starts_with((string)$item->statusText, 'Canceled')
                            ? $this->dateFormat->convertToDisplayDate(
                                'Y-m-d',
                                substr((string)$item->statusText, 9)
                            )
                            : '',
                    ];
                }
            }
        }
        return $requests;
    }

    /**
     * Place Storage Retrieval Request (Call Slip)
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
        $patron = $details['patron'];
        $level = isset($details['level']) && !empty($details['level'])
            ? $details['level'] : 'copy';
        $itemId = $details['item_id'] ?? false;
        $mfhdId = $details['holdings_id'] ?? false;
        $comment = $details['comment'] ?? '';
        $bibId = $details['id'];

        // Make Sure Pick Up Location is Valid
        if (
            isset($details['pickUpLocation'])
            && !$this->pickUpLocationIsValid(
                $details['pickUpLocation'],
                $patron,
                $details
            )
        ) {
            return $this->holdError('hold_invalid_pickup');
        }

        // Attempt Request
        $hierarchy = [];

        // Build Hierarchy
        $hierarchy['record'] = $bibId;

        if ($itemId && $level != 'title') {
            $hierarchy['items'] = $itemId;
        }

        $hierarchy['callslip'] = false;

        // Add Required Params
        $params = [
            'patron' => $patron['id'],
            'patron_homedb' => $this->ws_patronHomeUbId,
            'view' => 'full',
        ];

        $xml = [];
        if ('title' == $level) {
            $xml['call-slip-title-parameters'] = [
                'comment' => $comment,
                'reqinput field="1"' => $details['volume'],
                'reqinput field="2"' => $details['issue'],
                'reqinput field="3"' => $details['year'],
                'dbkey' => $this->ws_dbKey,
                'mfhdId' => $mfhdId,
            ];
            if (isset($details['pickUpLocation'])) {
                $xml['call-slip-title-parameters']['pickup-location']
                    = $details['pickUpLocation'];
            }
        } else {
            $xml['call-slip-parameters'] = [
                'comment' => $comment,
                'dbkey' => $this->ws_dbKey,
            ];
            if (isset($details['pickUpLocation'])) {
                $xml['call-slip-parameters']['pickup-location']
                    = $details['pickUpLocation'];
            }
        }

        // Generate XML
        $requestXML = $this->buildBasicXML($xml);

        // Get Data
        $result = $this->makeRequest($hierarchy, $params, 'PUT', $requestXML);

        if ($result) {
            // Process
            $result = $result->children();
            $reply = (string)$result->{'reply-text'};

            $responseNode = 'title' == $level
                ? 'create-call-slip-title'
                : 'create-call-slip';
            $note = (isset($result->$responseNode))
                ? trim((string)$result->$responseNode->note) : false;

            // Valid Response
            $response = [];
            if ($reply == 'ok' && $note == 'Your request was successful.') {
                $response['success'] = true;
                $response['status'] = 'storage_retrieval_request_place_success';
            } else {
                // Failed
                $response['sysMessage'] = $note;
            }
            return $response;
        }

        return $this->holdError('storage_retrieval_request_error_blocked');
    }

    /**
     * Cancel Storage Retrieval Requests (Call Slips)
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
        $details = $cancelDetails['details'];
        $patron = $cancelDetails['patron'];
        $count = 0;
        $response = [];

        foreach ($details as $cancelDetails) {
            [$dbKey, $itemId, $cancelCode] = explode('|', $cancelDetails);

            // Create Rest API Cancel Key
            $cancelID = ($dbKey ? $dbKey : $this->ws_dbKey) . '|' . $cancelCode;

            // Build Hierarchy
            $hierarchy = [
                'patron' => $patron['id'],
                'circulationActions' => 'requests',
                'callslips' => $cancelID,
            ];

            // Add Required Params
            $params = [
                'patron_homedb' => $this->ws_patronHomeUbId,
                'view' => 'full',
            ];

            // Get Data
            $cancel = $this->makeRequest($hierarchy, $params, 'DELETE');

            if ($cancel) {
                // Process Cancel
                $cancel = $cancel->children();
                $reply = (string)$cancel->{'reply-text'};
                $count = ($reply == 'ok') ? $count + 1 : $count;

                $response[$itemId] = [
                    'success' => ($reply == 'ok') ? true : false,
                    'status' => ($reply == 'ok')
                        ? 'storage_retrieval_request_cancel_success'
                        : 'storage_retrieval_request_cancel_fail',
                    'sysMessage' => ($reply == 'ok') ? false : $reply,
                ];
            } else {
                $response[$itemId] = [
                    'success' => false,
                    'status' => 'storage_retrieval_request_cancel_fail',
                ];
            }
        }
        $result = ['count' => $count, 'items' => $response];
        return $result;
    }

    /**
     * Get Cancel Storage Retrieval Request (Call Slip) Details
     *
     * In order to cancel a call slip, Voyager requires the item ID and a
     * request ID. This function returns the item id and call slip id as a
     * string separated by a pipe, which is then submitted as form data. This
     * value is then extracted by the CancelStorageRetrievalRequests function.
     *
     * @param array $details An array of item data
     * @param array $patron  Patron information from patronLogin
     *
     * @return string Data for use in a form field
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getCancelStorageRetrievalRequestDetails($details, $patron)
    {
        $details
            = ($details['institution_dbkey'] ?? '')
            . '|' . $details['item_id']
            . '|' . $details['reqnum'];
        return $details;
    }

    /**
     * A helper function that retrieves UB request details for ILL and caches them
     * for a short while for faster access.
     *
     * @param string $id     BIB id
     * @param array  $patron Patron
     *
     * @return bool|array False if UB request is not available or an array
     * of details on success
     */
    protected function getUBRequestDetails($id, $patron)
    {
        $cacheId = "ub|$id|{$patron['id']}";
        $data = $this->getCachedData($cacheId);
        if (!empty($data)) {
            return $data;
        }

        if (!str_contains($patron['id'], '.')) {
            $this->debug(
                "getUBRequestDetails: no prefix in patron id '{$patron['id']}'"
            );
            $this->putCachedData($cacheId, false);
            return false;
        }
        [$source, $patronId] = explode('.', $patron['id'], 2);
        if (!isset($this->config['ILLRequestSources'][$source])) {
            $this->debug("getUBRequestDetails: source '$source' unknown");
            $this->putCachedData($cacheId, false);
            return false;
        }

        [, $catUsername] = explode('.', $patron['cat_username'], 2);
        $patronId = $this->encodeXML($patronId);
        $patronHomeUbId = $this->encodeXML(
            $this->config['ILLRequestSources'][$source]
        );
        $lastname = $this->encodeXML($patron['lastname']);
        $barcode = $this->encodeXML($catUsername);
        $bibId = $this->encodeXML($id);
        $bibDbName = $this->encodeXML($this->config['Catalog']['database']);
        $localUbId = $this->encodeXML($this->ws_patronHomeUbId);

        // Call PatronRequestsService first to check that UB is an available request
        // type. Additionally, this seems to be mandatory, as PatronRequestService
        // may fail otherwise.
        $xml = <<<EOT
            <?xml version="1.0" encoding="UTF-8"?>
            <ser:serviceParameters
            xmlns:ser="http://www.endinfosys.com/Voyager/serviceParameters">
              <ser:parameters>
                <ser:parameter key="bibId">
                  <ser:value>$bibId</ser:value>
                </ser:parameter>
                <ser:parameter key="bibDbCode">
                  <ser:value>LOCAL</ser:value>
                </ser:parameter>
              </ser:parameters>
              <ser:patronIdentifier lastName="$lastname" patronHomeUbId="$patronHomeUbId"
              patronId="$patronId">
                <ser:authFactor type="B">$barcode</ser:authFactor>
              </ser:patronIdentifier>
            </ser:serviceParameters>
            EOT;

        $response = $this->makeRequest(
            ['PatronRequestsService' => false],
            [],
            'POST',
            $xml
        );

        if ($response === false) {
            $this->putCachedData($cacheId, false);
            return false;
        }
        // Process
        $response->registerXPathNamespace(
            'ser',
            'http://www.endinfosys.com/Voyager/serviceParameters'
        );
        $response->registerXPathNamespace(
            'req',
            'http://www.endinfosys.com/Voyager/requests'
        );
        foreach ($response->xpath('//ser:message') as $message) {
            // Any message means a problem, right?
            $this->putCachedData($cacheId, false);
            return false;
        }
        $requestCount = count(
            $response->xpath("//req:requestIdentifier[@requestCode='UB']")
        );
        if ($requestCount == 0) {
            // UB request not available
            $this->putCachedData($cacheId, false);
            return false;
        }

        $xml = <<<EOT
            <?xml version="1.0" encoding="UTF-8"?>
            <ser:serviceParameters
            xmlns:ser="http://www.endinfosys.com/Voyager/serviceParameters">
              <ser:parameters>
                <ser:parameter key="bibId">
                  <ser:value>$bibId</ser:value>
                </ser:parameter>
                <ser:parameter key="bibDbCode">
                  <ser:value>LOCAL</ser:value>
                </ser:parameter>
                <ser:parameter key="bibDbName">
                  <ser:value>$bibDbName</ser:value>
                </ser:parameter>
                <ser:parameter key="requestCode">
                  <ser:value>UB</ser:value>
                </ser:parameter>
                <ser:parameter key="requestSiteId">
                  <ser:value>$localUbId</ser:value>
                </ser:parameter>
              </ser:parameters>
              <ser:patronIdentifier lastName="$lastname" patronHomeUbId="$patronHomeUbId"
              patronId="$patronId">
                <ser:authFactor type="B">$barcode</ser:authFactor>
              </ser:patronIdentifier>
            </ser:serviceParameters>
            EOT;

        $response = $this->makeRequest(
            ['PatronRequestService' => false],
            [],
            'POST',
            $xml
        );

        if ($response === false) {
            $this->putCachedData($cacheId, false);
            return false;
        }
        // Process
        $response->registerXPathNamespace(
            'ser',
            'http://www.endinfosys.com/Voyager/serviceParameters'
        );
        $response->registerXPathNamespace(
            'req',
            'http://www.endinfosys.com/Voyager/requests'
        );
        foreach ($response->xpath('//ser:message') as $message) {
            // Any message means a problem, right?
            $this->putCachedData($cacheId, false);
            return false;
        }
        $items = [];
        $libraries = [];
        $locations = [];
        $requiredByDate = '';
        foreach ($response->xpath('//req:field') as $field) {
            switch ($field->attributes()->labelKey) {
                case 'selectItem':
                    foreach ($field->xpath('./req:select/req:option') as $option) {
                        $items[] = [
                            'id' => (string)$option->attributes()->id,
                            'name' => (string)$option,
                        ];
                    }
                    break;
                case 'pickupLib':
                    foreach ($field->xpath('./req:select/req:option') as $option) {
                        $libraries[] = [
                            'id' => (string)$option->attributes()->id,
                            'name' => (string)$option,
                            'isDefault' => $option->attributes()->isDefault == 'Y',
                        ];
                    }
                    break;
                case 'pickUpAt':
                    foreach ($field->xpath('./req:select/req:option') as $option) {
                        $locations[] = [
                            'id' => (string)$option->attributes()->id,
                            'name' => (string)$option,
                            'isDefault' => $option->attributes()->isDefault == 'Y',
                        ];
                    }
                    break;
                case 'notNeededAfter':
                    $node = current($field->xpath('./req:text'));
                    $requiredByDate = $this->dateFormat->convertToDisplayDate(
                        'Y-m-d H:i',
                        (string)$node
                    );
                    break;
            }
        }
        $results = [
            'items' => $items,
            'libraries' => $libraries,
            'locations' => $locations,
            'requiredBy' => $requiredByDate,
        ];
        $this->putCachedData($cacheId, $results);
        return $results;
    }

    /**
     * Check if ILL Request is valid
     *
     * This is responsible for determining if an item is requestable
     *
     * @param string $id     The Bib ID
     * @param array  $data   An Array of item data
     * @param array  $patron An array of patron data
     *
     * @return bool True if request is valid, false if not
     */
    public function checkILLRequestIsValid($id, $data, $patron)
    {
        if (!isset($this->config['ILLRequests'])) {
            $this->debug('ILL Requests not configured');
            return false;
        }

        $level = $data['level'] ?? 'copy';
        $itemID = ($level != 'title' && isset($data['item_id']))
            ? $data['item_id']
            : false;

        if ($level == 'copy' && $itemID === false) {
            $this->debug('Item ID missing');
            return false;
        }

        $results = $this->getUBRequestDetails($id, $patron);
        if ($results === false) {
            $this->debug('getUBRequestDetails returned false');
            return false;
        }
        if ($level == 'copy') {
            $found = false;
            foreach ($results['items'] as $item) {
                if ($item['id'] == "$itemID.$id") {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $this->debug('Item not requestable');
                return false;
            }
        }

        return true;
    }

    /**
     * Get ILL (UB) Pickup Libraries
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
        if (!isset($this->config['ILLRequests'])) {
            return false;
        }

        $results = $this->getUBRequestDetails($id, $patron);
        if ($results === false) {
            $this->debug('getUBRequestDetails returned false');
            return false;
        }

        return $results['libraries'];
    }

    /**
     * Get ILL (UB) Pickup Locations
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
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getILLPickupLocations($id, $pickupLib, $patron)
    {
        if (!isset($this->config['ILLRequests'])) {
            return false;
        }

        [$source, $patronId] = explode('.', $patron['id'], 2);
        if (!isset($this->config['ILLRequestSources'][$source])) {
            return $this->holdError('ill_request_unknown_patron_source');
        }

        [, $catUsername] = explode('.', $patron['cat_username'], 2);
        $patronId = $this->encodeXML($patronId);
        $patronHomeUbId = $this->encodeXML(
            $this->config['ILLRequestSources'][$source]
        );
        $lastname = $this->encodeXML($patron['lastname']);
        $barcode = $this->encodeXML($catUsername);
        $pickupLib = $this->encodeXML($pickupLib);

        $xml = <<<EOT
            <?xml version="1.0" encoding="UTF-8"?>
            <ser:serviceParameters
            xmlns:ser="http://www.endinfosys.com/Voyager/serviceParameters">
              <ser:parameters>
                <ser:parameter key="pickupLibId">
                  <ser:value>$pickupLib</ser:value>
                </ser:parameter>
              </ser:parameters>
              <ser:patronIdentifier lastName="$lastname" patronHomeUbId="$patronHomeUbId"
              patronId="$patronId">
                <ser:authFactor type="B">$barcode</ser:authFactor>
              </ser:patronIdentifier>
            </ser:serviceParameters>
            EOT;

        $response = $this->makeRequest(
            ['UBPickupLibService' => false],
            [],
            'POST',
            $xml
        );

        if ($response === false) {
            throw new ILSException('ill_request_error_technical');
        }
        // Process
        $response->registerXPathNamespace(
            'ser',
            'http://www.endinfosys.com/Voyager/serviceParameters'
        );
        $response->registerXPathNamespace(
            'req',
            'http://www.endinfosys.com/Voyager/requests'
        );
        if ($response->xpath('//ser:message')) {
            // Any message means a problem, right?
            throw new ILSException('ill_request_error_technical');
        }
        $locations = [];
        foreach ($response->xpath('//req:location') as $location) {
            $locations[] = [
                'id' => (string)$location->attributes()->id,
                'name' => (string)$location,
                'isDefault' => $location->attributes()->isDefault == 'Y',
            ];
        }
        return $locations;
    }

    /**
     * Place ILL (UB) Request
     *
     * Attempts to place an UB request on a particular item and returns
     * an array with result details or a PEAR error on failure of support classes
     *
     * @param array $details An array of item and patron data
     *
     * @return mixed An array of data on the request including
     * whether or not it was successful and a system message (if available)
     */
    public function placeILLRequest($details)
    {
        $patron = $details['patron'];
        [$source, $patronId] = explode('.', $patron['id'], 2);
        if (!isset($this->config['ILLRequestSources'][$source])) {
            return $this->holdError('ill_request_error_unknown_patron_source');
        }

        [, $catUsername] = explode('.', $patron['cat_username'], 2);
        $patronId = htmlspecialchars($patronId, ENT_COMPAT, 'UTF-8');
        $patronHomeUbId = $this->encodeXML(
            $this->config['ILLRequestSources'][$source]
        );
        $lastname = $this->encodeXML($patron['lastname']);
        $ubId = $this->encodeXML($patronHomeUbId);
        $barcode = $this->encodeXML($catUsername);
        $pickupLocation = $this->encodeXML($details['pickUpLibraryLocation']);
        $pickupLibrary = $this->encodeXML($details['pickUpLibrary']);
        $itemId = $this->encodeXML($details['item_id'] . '.' . $details['id']);
        $comment = $this->encodeXML(
            $details['comment'] ?? ''
        );
        $bibId = $this->encodeXML($details['id']);
        $bibDbName = $this->encodeXML($this->config['Catalog']['database']);
        $localUbId = $this->encodeXML($this->ws_patronHomeUbId);

        // Convert last interest date from Display Format to Voyager required format
        try {
            $lastInterestDate = $this->dateFormat->convertFromDisplayDate(
                'Y-m-d',
                $details['requiredBy']
            );
        } catch (DateException $e) {
            // Date is invalid
            return $this->holdError('ill_request_date_invalid');
        }

        // Verify pickup library and location
        $pickupLocationValid = false;
        $pickupLocations = $this->getILLPickupLocations(
            $details['id'],
            $details['pickUpLibrary'],
            $patron
        );
        foreach ($pickupLocations as $location) {
            if ($location['id'] == $details['pickUpLibraryLocation']) {
                $pickupLocationValid = true;
                break;
            }
        }
        if (!$pickupLocationValid) {
            return [
                'success' => false,
                'sysMessage' => 'ill_request_place_fail_missing',
            ];
        }

        // Attempt Request
        $xml = <<<EOT
            <?xml version="1.0" encoding="UTF-8"?>
            <ser:serviceParameters
            xmlns:ser="http://www.endinfosys.com/Voyager/serviceParameters">
              <ser:parameters>
                <ser:parameter key="bibId">
                  <ser:value>$bibId</ser:value>
                </ser:parameter>
                <ser:parameter key="bibDbCode">
                  <ser:value>LOCAL</ser:value>
                </ser:parameter>
                <ser:parameter key="bibDbName">
                  <ser:value>$bibDbName</ser:value>
                </ser:parameter>
                <ser:parameter key="Select_Library">
                  <ser:value>$localUbId</ser:value>
                </ser:parameter>
                <ser:parameter key="requestCode">
                  <ser:value>UB</ser:value>
                </ser:parameter>
                <ser:parameter key="requestSiteId">
                  <ser:value>$localUbId</ser:value>
                </ser:parameter>
                <ser:parameter key="itemId">
                  <ser:value>$itemId</ser:value>
                </ser:parameter>
                <ser:parameter key="Select_Pickup_Lib">
                  <ser:value>$pickupLibrary</ser:value>
                </ser:parameter>
                <ser:parameter key="PICK">
                  <ser:value>$pickupLocation</ser:value>
                </ser:parameter>
                <ser:parameter key="REQNNA">
                  <ser:value>$lastInterestDate</ser:value>
                </ser:parameter>
                <ser:parameter key="REQCOMMENTS">
                  <ser:value>$comment</ser:value>
                </ser:parameter>
              </ser:parameters>
              <ser:patronIdentifier lastName="$lastname" patronHomeUbId="$ubId"
              patronId="$patronId">
                <ser:authFactor type="B">$barcode</ser:authFactor>
              </ser:patronIdentifier>
            </ser:serviceParameters>
            EOT;

        $response = $this->makeRequest(
            ['SendPatronRequestService' => false],
            [],
            'POST',
            $xml
        );

        if ($response === false) {
            return $this->holdError('ill_request_error_technical');
        }
        // Process
        $response->registerXPathNamespace(
            'ser',
            'http://www.endinfosys.com/Voyager/serviceParameters'
        );
        $response->registerXPathNamespace(
            'req',
            'http://www.endinfosys.com/Voyager/requests'
        );
        foreach ($response->xpath('//ser:message') as $message) {
            if ($message->attributes()->type == 'success') {
                return [
                    'success' => true,
                    'status' => 'ill_request_place_success',
                ];
            }
            if ($message->attributes()->type == 'system') {
                return $this->holdError('ill_request_error_technical');
            }
        }

        return $this->holdError('ill_request_error_blocked');
    }

    /**
     * Get Patron ILL Requests
     *
     * This is responsible for retrieving all UB requests by a specific patron.
     *
     * @param array $patron The patron array from patronLogin
     *
     * @throws ILSException
     * @return mixed        Array of the patron's holds on success.
     */
    public function getMyILLRequests($patron)
    {
        return array_merge(
            $this->getHoldsFromApi($patron, false),
            $this->getCallSlips($patron, false) // remote only
        );
    }

    /**
     * Cancel ILL (UB) Requests
     *
     * Attempts to Cancel an UB request on a particular item. The
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
        $details = $cancelDetails['details'];
        $patron = $cancelDetails['patron'];
        $count = 0;
        $response = [];

        foreach ($details as $cancelDetails) {
            [$dbKey, $itemId, $type, $cancelCode] = explode('|', $cancelDetails);

            // Create Rest API Cancel Key
            $cancelID = ($dbKey ? $dbKey : $this->ws_dbKey) . '|' . $cancelCode;

            // Build Hierarchy
            $hierarchy = [
                'patron' => $patron['id'],
                 'circulationActions' => 'requests',
            ];
            // An UB request is
            if ($type == 'C') {
                $hierarchy['callslips'] = $cancelID;
            } else {
                $hierarchy['holds'] = $cancelID;
            }

            // Add Required Params
            $params = [
                'patron_homedb' => $this->ws_patronHomeUbId,
                'view' => 'full',
            ];

            // Get Data
            $cancel = $this->makeRequest($hierarchy, $params, 'DELETE');

            if ($cancel) {
                // Process Cancel
                $cancel = $cancel->children();
                $node = 'reply-text';
                $reply = (string)$cancel->$node;
                $count = ($reply == 'ok') ? $count + 1 : $count;

                $response[$itemId] = [
                    'success' => ($reply == 'ok') ? true : false,
                    'status' => ($reply == 'ok')
                        ? 'ill_request_cancel_success' : 'ill_request_cancel_fail',
                    'sysMessage' => ($reply == 'ok') ? false : $reply,
                ];
            } else {
                $response[$itemId] = [
                    'success' => false,
                    'status' => 'ill_request_cancel_fail',
                ];
            }
        }
        $result = ['count' => $count, 'items' => $response];
        return $result;
    }

    /**
     * Get Cancel ILL (UB) Request Details
     *
     * In Voyager an UB request is either a call slip (pending delivery) or a hold
     * (pending checkout). In order to cancel an UB request, Voyager requires the
     * patron details, an item ID, request type and a recall ID. This function
     * returns the information as a string separated by pipes, which is then
     * submitted as form data and extracted by the CancelILLRequests function.
     *
     * @param array $details An array of item data
     * @param array $patron  Patron information from patronLogin
     *
     * @return string Data for use in a form field
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getCancelILLRequestDetails($details, $patron)
    {
        $details = ($details['institution_dbkey'] ?? '')
            . '|' . $details['item_id']
            . '|' . $details['type']
            . '|' . $details['reqnum'];
        return $details;
    }

    /**
     * Support method: is this institution code a local one?
     *
     * @param string $institution Institution code
     *
     * @return bool
     */
    protected function isLocalInst($institution)
    {
        // In some versions of Voyager, this will be 'LOCAL' while
        // in others, it may be something like '1@LOCAL' -- for now,
        // let's try checking the last 5 characters. If other options
        // exist in the wild, we can make this method more sophisticated.
        return str_ends_with($institution, 'LOCAL');
    }

    /**
     * Change Password
     *
     * Attempts to change patron password (PIN code)
     *
     * @param array $details An array of patron id and old and new password:
     *
     * 'patron'      The patron array from patronLogin
     * 'oldPassword' Old password
     * 'newPassword' New password
     *
     * @return array An array of data on the request including
     * whether or not it was successful and a system message (if available)
     */
    public function changePassword($details)
    {
        $patron = $details['patron'];
        $id = htmlspecialchars($patron['id'], ENT_COMPAT, 'UTF-8');
        $lastname = htmlspecialchars($patron['lastname'], ENT_COMPAT, 'UTF-8');
        $ubId = htmlspecialchars($this->ws_patronHomeUbId, ENT_COMPAT, 'UTF-8');
        $oldPIN = trim(
            htmlspecialchars(
                $this->sanitizePIN($details['oldPassword']),
                ENT_COMPAT,
                'UTF-8'
            )
        );

        if ($oldPIN === '') {
            // Voyager requires the PIN code to be set even if it was empty
            $oldPIN = '     ';

            // In this case we have to check that the user didn't previously have a
            // PIN code since Voyager doesn't validate the 'empty' old PIN
            $sql = "SELECT PATRON_PIN FROM {$this->dbName}.PATRON WHERE"
                . ' PATRON_ID=:id';
            $sqlStmt = $this->executeSQL($sql, ['id' => $patron['id']]);
            if (
                !($row = $sqlStmt->fetch(PDO::FETCH_ASSOC))
                || null !== $row['PATRON_PIN']
            ) {
                return [
                    'success' => false, 'status' => 'authentication_error_invalid',
                ];
            }
        }
        $newPIN = trim(
            htmlspecialchars(
                $this->sanitizePIN($details['newPassword']),
                ENT_COMPAT,
                'UTF-8'
            )
        );
        if ($newPIN === '') {
            return [
                'success' => false, 'status' => 'password_error_invalid',
            ];
        }
        $barcode = htmlspecialchars($patron['cat_username'], ENT_COMPAT, 'UTF-8');

        $xml = <<<EOT
            <?xml version="1.0" encoding="UTF-8"?>
            <ser:serviceParameters
            xmlns:ser="http://www.endinfosys.com/Voyager/serviceParameters">
               <ser:parameters>
                  <ser:parameter key="oldPatronPIN">
                     <ser:value>$oldPIN</ser:value>
                  </ser:parameter>
                  <ser:parameter key="newPatronPIN">
                     <ser:value>$newPIN</ser:value>
                  </ser:parameter>
               </ser:parameters>
               <ser:patronIdentifier lastName="$lastname" patronHomeUbId="$ubId" patronId="$id">
                  <ser:authFactor type="B">$barcode</ser:authFactor>
               </ser:patronIdentifier>
            </ser:serviceParameters>
            EOT;

        $result = $this->makeRequest(
            ['ChangePINService' => false],
            [],
            'POST',
            $xml
        );

        $result->registerXPathNamespace(
            'ser',
            'http://www.endinfosys.com/Voyager/serviceParameters'
        );
        $error = $result->xpath("//ser:message[@type='error']");
        if (!empty($error)) {
            $error = reset($error);
            $code = $error->attributes()->errorCode;
            $exceptionNamespace = 'com.endinfosys.voyager.patronpin.PatronPIN.';
            if ($code == $exceptionNamespace . 'ValidateException') {
                return [
                    'success' => false, 'status' => 'authentication_error_invalid',
                ];
            }
            if ($code == $exceptionNamespace . 'ValidateUniqueException') {
                return [
                    'success' => false, 'status' => 'password_error_not_unique',
                ];
            }
            if ($code == $exceptionNamespace . 'ValidateLengthException') {
                // This error may happen even with correct settings if the new PIN
                // contains invalid characters.
                return [
                    'success' => false, 'status' => 'password_error_invalid',
                ];
            }
            throw new ILSException((string)$error);
        }
        return ['success' => true, 'status' => 'change_password_ok'];
    }

    /**
     * Helper method to determine whether or not a certain method can be
     * called on this driver. Required method for any smart drivers.
     *
     * @param string $method The name of the called method.
     * @param array  $params Array of passed parameters
     *
     * @return bool True if the method can be called with the given parameters,
     * false otherwise.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function supportsMethod($method, $params)
    {
        // Special case: change password is only available if properly configured.
        if ($method == 'changePassword') {
            return isset($this->config['changePassword']);
        }
        return is_callable([$this, $method]);
    }
}
