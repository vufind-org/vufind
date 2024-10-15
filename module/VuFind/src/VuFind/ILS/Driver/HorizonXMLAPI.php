<?php

/**
 * Horizon ILS Driver (w/ XML API support)
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2007.
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
 * @author   Matt Mackey <vufind-tech@lists.sourceforge.net>
 * @author   Ray Cummins <vufind-tech@lists.sourceforge.net>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */

namespace VuFind\ILS\Driver;

use VuFind\Exception\ILS as ILSException;

use function in_array;
use function is_array;

/**
 * Horizon ILS Driver (w/ XML API support)
 *
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Matt Mackey <vufind-tech@lists.sourceforge.net>
 * @author   Ray Cummins <vufind-tech@lists.sourceforge.net>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
class HorizonXMLAPI extends Horizon implements \VuFindHttp\HttpServiceAwareInterface
{
    use \VuFindHttp\HttpServiceAwareTrait;

    /**
     * API profile
     *
     * @var string
     */
    protected $wsProfile;

    /**
     * API URL
     *
     * @var string
     */
    protected $wsURL;

    /**
     * Available pickup locations for holds
     *
     * @var array
     */
    protected $wsPickUpLocations;

    /**
     * Default pickup location for holds
     *
     * @var string
     */
    protected $wsDefaultPickUpLocation;

    /**
     * Date format used by API
     *
     * @var string
     */
    protected $wsDateFormat;

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

        // Process Config
        $this->wsProfile = $this->config['Webservices']['profile'];
        $this->wsURL = $this->config['Webservices']['HIPurl'];
        $this->wsPickUpLocations
            = $this->config['pickUpLocations'] ?? false;

        $this->wsDefaultPickUpLocation
            = $this->config['Holds']['defaultPickUpLocation'] ?? false;

        $this->wsDateFormat
            = $this->config['Webservices']['dateformat'] ?? 'd/m/Y';
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
     * Protected support method for getHolding.
     *
     * @param string $id     Bib Id
     * @param array  $row    SQL Row Data
     * @param array  $patron Patron Array
     *
     * @return array Keyed data
     */
    protected function processHoldingRow($id, $row, $patron)
    {
        $itemData = [
            'id' => $row['ITEM_ID'],
            'level' => 'item',
        ];

        $holding = parent::processHoldingRow($id, $row, $patron);
        $holding += [
            'addLink' => $this->checkRequestIsValid($id, $itemData, $patron),
         ];
        return $holding;
    }

    /**
     * Determine Renewability
     *
     * This is responsible for determining if an item is renewable
     *
     * @param string $requested The number of times an item has been requested
     *
     * @return array $renewData Array of the renewability status and associated
     * message
     */
    protected function determineRenewability($requested)
    {
        $renewData = [];

        $renewData['renewable'] = ($requested == 0) ? true : false;

        if (!$renewData['renewable']) {
            $renewData['message'] = 'renew_item_requested';
        } else {
            $renewData['message'] = false;
        }

        return $renewData;
    }

    /**
     * Protected support method for getMyTransactions.
     *
     * @param array $row An array of keyed data
     *
     * @return array Keyed data for display by template files
     */
    protected function processTransactionsRow($row)
    {
        $transactions = parent::processTransactionsRow($row);
        $renewData = $this->determineRenewability($row['REQUEST']);
        $transactions['renewable'] = $renewData['renewable'];
        $transactions['message'] = $renewData['message'];
        return $transactions;
    }

    /* Horizon XML API Functions */

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
    public function getPickUpLocations($patron, $holdDetails = null)
    {
        $pickresponse = [];
        if ($this->wsPickUpLocations == false) {
            // Select
            $sqlSelect = [
                    'l.location LOCATIONID',
                    'l.name LOCATIONDISPLAY',
            ];

            // From
            $sqlFrom = ['pickup_location_sort pls'];

            // Join
            $sqlJoin = [
                    'location l on l.location = pls.pickup_location',
                    'borrower b on b.location = pls.location',
                    'borrower_barcode bb on bb.borrower# = b.borrower#',
            ];

            // Where
            $sqlWhere = [
                    'pls.display = 1',
                    'bb.bbarcode="' . addslashes($patron['id']) . '"',
            ];

            // Order by
            $sqlOrder = ['l.name'];

            $sqlArray = [
                    'expressions' => $sqlSelect,
                    'from'        => $sqlFrom,
                    'join'        => $sqlJoin,
                    'where'       => $sqlWhere,
                    'order'       => $sqlOrder,
            ];

            $sql = $this->buildSqlFromArray($sqlArray);

            try {
                $sqlStmt = $this->db->query($sql);

                foreach ($sqlStmt as $row) {
                    $pickresponse[] = [
                        'locationID'      => $row['LOCATIONID'],
                        'locationDisplay' => $row['LOCATIONDISPLAY'],
                    ];
                }
            } catch (\Exception $e) {
                $this->throwAsIlsException($e);
            }
        } elseif (isset($this->wsPickUpLocations)) {
            foreach ($this->wsPickUpLocations as $code => $library) {
                $pickresponse[] = [
                    'locationID' => $code,
                    'locationDisplay' => $library,
                ];
            }
        }
        return $pickresponse;
    }

    /**
     * Get Default Pick Up Location
     *
     * This is responsible for retrieving the pickup location for a logged in patron.
     *
     * @param array $patron      Patron information returned by the patronLogin
     * method.
     * @param array $holdDetails Optional array, only passed in when getting a list
     * in the context of placing a hold; contains most of the same values passed to
     * placeHold, minus the patron data. May be used to limit the pickup options
     * or may be ignored.
     *
     * @return string       The default pickup location for the patron.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getDefaultPickUpLocation($patron = false, $holdDetails = null)
    {
        if ($this->wsDefaultPickUpLocation == false) {
            // Select
            $sqlSelect = ['b.location LOCATION'];

            // From
            $sqlFrom = ['borrower b'];

            // Join
            $sqlJoin = ['borrower_barcode bb on bb.borrower# = b.borrower#'];

            // Where
            $sqlWhere = ['bb.bbarcode="' . addslashes($patron['id']) . '"'];

            $sqlArray = [
                    'expressions' => $sqlSelect,
                    'from'        => $sqlFrom,
                    'join'        => $sqlJoin,
                    'where'       => $sqlWhere,
            ];

            $sql = $this->buildSqlFromArray($sqlArray);

            try {
                $sqlStmt = $this->db->query($sql);

                foreach ($sqlStmt as $row) {
                    $defaultPickUpLocation = $row['LOCATION'];
                    return $defaultPickUpLocation;
                }
            } catch (\Exception $e) {
                $this->throwAsIlsException($e);
            }
        } elseif (isset($this->wsDefaultPickUpLocation)) {
            return $this->wsDefaultPickUpLocation;
        }
        // If we didn't return above, there were no values.
        return null;
    }

    /**
     * Make Request
     *
     * Makes a request to the Horizon API
     *
     * @param array  $params A keyed array of query data
     * @param string $mode   The http request method to use (Default of GET)
     *
     * @return obj  A Simple XML Object loaded with the xml data returned by the API
     */
    protected function makeRequest($params = false, $mode = 'GET')
    {
        $queryString = [];
        // Build Url Base
        $urlParams = $this->wsURL;

        // Add Params
        foreach ($params as $key => $param) {
            if (is_array($param)) {
                foreach ($param as $sub) {
                    $queryString[] = $key . '=' . urlencode($sub);
                }
            } else {
                // This is necessary as Horizon expects spaces to be represented by
                // "+" rather than the url_encode "%20" for Pick Up Locations
                $queryString[] = $key . '=' .
                    str_replace('%20', '+', urlencode($param));
            }
        }

        // Build Params
        $urlParams .= '?' . implode('&', $queryString);

        // Create Proxy Request
        $client = $this->httpService->createClient($urlParams, $mode);

        // Send Request and Retrieve Response
        $result = $client->send();
        if (!$result->isSuccess()) {
            throw new ILSException('Problem with XML API.');
        }
        $xmlResponse = $result->getBody();

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
     *  Get Session
     *
     * Gets a Horizon session
     *
     * @return mixed A session string on success, boolean false on failure
     */
    protected function getSession()
    {
        $params = ['profile' => $this->wsProfile,
                        'menu' => 'account',
                        'GetXML' => 'true',
                        ];

        $response = $this->makeRequest($params);

        if ($response && $response->session) {
            $session = (string)$response->session;
            return $session;
        }

        return false;
    }

    /**
     *  Register User
     *
     * Associates a user with a session
     *
     * @param string $userBarcode  A valid Horizon user barcode
     * @param string $userPassword A valid Horizon user password (pin)
     *
     * @return bool true on success, false on failure
     */
    protected function registerUser($userBarcode, $userPassword)
    {
        // Get Session
        $session = $this->getSession();

        $params = ['session' => $session,
                        'profile' => $this->wsProfile,
                        'menu' => 'account',
                        'sec1' => $userBarcode,
                        'sec2' => $userPassword,
                        'GetXML' => 'true',
                        ];

        $response = $this->makeRequest($params);

        $auth = (string)$response->security->auth;

        if ($auth == 'true') {
            return $session;
        }

        return false;
    }

    /**
     * Check if Request is Valid
     *
     * Determines if a user can place a hold or recall on a specific item
     *
     * @param string $bibId    An item's Bib ID
     * @param string $itemData Array containing item id and hold level
     * @param array  $patron   Patron Array Data
     *
     * @return bool true if the request can be made, false if it cannot
     */
    public function checkRequestIsValid($bibId, $itemData, $patron)
    {
        // Register Account
        $session = $this->registerUser(
            $patron['cat_username'],
            $patron['cat_password']
        );
        if ($session) {
            $params = [
                'session' => $session,
                'profile' => $this->wsProfile,
                'bibkey'  => $bibId,
                'aspect'  => 'submenu13',
                'lang'    => 'eng',
                'menu'    => 'request',
                'submenu' => 'none',
                'source'  => '~!horizon',
                'uri'     => '',
                'GetXML'  => 'true',
            ];

            // set itemkey only if available and level is not title-level
            if ($itemData['item_id'] != '' && $itemData['level'] != 'title') {
                $params += ['itemkey' => $itemData['item_id']];
            }

            $initResponse = $this->makeRequest($params);

            if ($initResponse->request_confirm) {
                return true;
            }
        }
        return false;
    }

    /**
     *  Get Items
     *
     * Gets a list of items on loan
     *
     * @param string $session A valid Horizon session key
     *
     * @return obj A Simple XML Object
     */
    protected function getItems($session)
    {
        $params = ['session' => $session,
                        'profile' => $this->wsProfile,
                        'menu' => 'account',
                        'submenu' => 'itemsout',
                        'GetXML' => 'true',
                        ];

        $response = $this->makeRequest($params);

        if ($response->itemsoutdata) {
            return $response->itemsoutdata;
        }

        return false;
    }

    /**
     *  Renew Items
     *
     * Submits a renewal request to the Horizon API and returns the results
     *
     * @param string $session A valid Horizon session key
     * @param array  $items   A list of items to be renewed
     *
     * @return obj A Simple XML Object
     */
    protected function renewItems($session, $items)
    {
        $params = ['session' => $session,
                        'profile' => $this->wsProfile,
                        'menu' => 'account',
                        'submenu' => 'itemsout',
                        'renewitemkeys' => $items,
                        'renewitems' => 'Renew',
                        'GetXML' => 'true',
                        ];

        $response = $this->makeRequest($params);

        if ($response->itemsoutdata) {
            return $response->itemsoutdata;
        }

        return false;
    }

    /**
     * Place Request
     *
     * Submits a hold request to the Horizon XML API and processes the result
     *
     * @param string $session        A valid Horizon session key
     * @param array  $requestDetails An array of request details
     *
     * @return array  An array with keys indicating the success (boolean),
     * status (string) and sysMessage (string) if available
     */
    protected function placeRequest($session, $requestDetails)
    {
        $params = ['session' => $session,
                        'profile' => $this->wsProfile,
                        'bibkey' => $requestDetails['bibId'],
                        'aspect' => 'submenu13',
                        'lang' => 'eng',
                        'menu' => 'request',
                        'submenu' => 'none',
                        'source' => '~!horizon',
                        'uri' => '',
                        'GetXML' => 'true',
                        ];

        // set itemkey only if available
        if ($requestDetails['itemId'] != '') {
            $params += ['itemkey' => $requestDetails['itemId']];
        }

        $initResponse = $this->makeRequest($params);

        if ($initResponse->request_confirm) {
            $confirmParams = [
                'session' => $session,
                'profile' => $this->wsProfile,
                'bibkey' => $requestDetails['bibId'],
                'aspect' => 'advanced',
                'lang' => 'eng',
                'menu' => 'request',
                'submenu' => 'none',
                'source' => '~!horizon',
                'uri' => '',
                'link' => 'direct',
                'request_finish' => 'Request',
                'cl' => 'PlaceRequestjsp',
                'pickuplocation' => $requestDetails['pickuplocation'],
                'notifyby' => $requestDetails['notify'],
                'GetXML' => 'true',
            ];

            $request = $this->makeRequest($confirmParams);

            if ($request->request_success) {
                $response = [
                    'success' => true,
                    'status' => 'hold_success',
                ];
            } else {
                $response = [
                    'success' => false,
                    'status' => 'hold_error_fail',
                ];
            }
        } else {
            $sysMessage = false;
            if ($initResponse->alert->message) {
                $sysMessage = (string)$initResponse->alert->message;
            }
            $response = [
                'success' => false,
                'status' => 'hold_error_fail',
                'sysMessage' => $sysMessage,
            ];
        }
        return $response;
    }

    /**
     * Cancel Request
     *
     * Submits a cancel request to the Horizon API and processes the result
     *
     * @param string $session A valid Horizon session key
     * @param Array  $data    An array of item data
     *
     * @return array  An array of cancel information keyed by item ID plus
     * the number of successful cancels
     */
    protected function cancelRequest($session, $data)
    {
        $responseItems = [];

        $params = ['session'    => $session,
                        'profile'    => $this->wsProfile,
                        'lang'       => 'eng',
                        'menu'       => 'account',
                        'submenu'    => 'holds',
                        'cancelhold' => 'Cancel Request',
                        'GetXML'     => 'true',
                        ];

        $cancelData = [];
        foreach ($data as $values) {
            $cancelData[] = $values['bib_id'] . ':' . $values['item_id'];
        }

        $params += ['waitingholdselected' => $cancelData];

        $response = $this->makeRequest($params);

        $count = 0;
        // No Indication of Success or Failure
        if ($response !== false && !$response->error->message) {
            $keys = [];
            // Get a list of bib keys from waiting items
            $currentHolds = $response->holdsdata->waiting->waitingitem;
            foreach ($currentHolds as $hold) {
                foreach ($hold->key as $key) {
                    $keys[] = (string)$key;
                }
            }

            // Go through the submitted bib ids and look for a match
            foreach ($data as $values) {
                $itemID = $values['item_id'];
                // If the bib id is matched, the cancel must have failed
                if (in_array($values['bib_id'], $keys)) {
                    $responseItems[$itemID] = [
                        'success' => false, 'status' => 'hold_cancel_fail',
                    ];
                } else {
                    $responseItems[$itemID] = [
                        'success' => true, 'status' => 'hold_cancel_success',

                    ];
                    $count = $count + 1;
                }
            }
        } else {
            $message = false;
            if ($response->error->message) {
                $message = (string)$response->error->message;
            }
            foreach ($data as $values) {
                $itemID = $values['item_id'];
                $responseItems[$itemID] = [
                    'success' => false,
                    'status' => 'hold_cancel_fail',
                    'sysMessage' => $message,
                ];
            }
        }
        $result = ['count' => $count, 'items' => $responseItems];
        return $result;
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
        $userBarcode      = $holdDetails['patron']['id'];
        $userPassword     = $holdDetails['patron']['cat_password'];
        $bibId            = $holdDetails['id'];
        $itemId           = $holdDetails['item_id'];
        $level            = $holdDetails['level'];
        $pickUpLocationID = !empty($holdDetails['pickUpLocation'])
                          ? $holdDetails['pickUpLocation']
                          : $this->getDefaultPickUpLocation();
        $notify           = $this->config['Holds']['notify'];

        $requestDetails = [
            'bibId'          => $bibId,
            'pickuplocation' => strtoupper($pickUpLocationID),
            'notify'         => $notify,
        ];

        if ($level != 'title' && $itemId != '') {
            $requestDetails += ['itemId' => $itemId];
        }

        // Register Account
        $session = $this->registerUser($userBarcode, $userPassword);
        if ($session) {
            $response = $this->placeRequest($session, $requestDetails);
        } else {
            $response = [
                'success' => false, 'status' => 'authentication_error_admin',
            ];
        }

        return $response;
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
        $cancelIDs = [];
        $details = $cancelDetails['details'];
        $userBarcode = $cancelDetails['patron']['id'];
        $userPassword = $cancelDetails['patron']['cat_password'];

        foreach ($details as $cancelItem) {
            [$bibID, $itemID] = explode('|', $cancelItem);
            $cancelIDs[]  = ['bib_id' =>  $bibID, 'item_id' => $itemID];
        }

        // Register Account
        $session = $this->registerUser($userBarcode, $userPassword);
        if ($session) {
            $response = $this->cancelRequest($session, $cancelIDs);
        } else {
            $response = [
                'success' => false, 'sysMessage' => 'authentication_error_admin',
            ];
        }
        return $response;
    }

    /**
     * Process Renewals
     *
     * This is responsible for processing renewals and is necessary
     * as result of renew attempt is not returned
     *
     * @param array $renewIDs  A list of the items being renewed
     * @param array $origData  A Simple XML array of loan data before the
     * renewal attempt
     * @param array $renewData A Simple XML array of loan data after the
     * renewal attempt
     *
     * @return array        An Array specifying the results of each renewal attempt
     */
    protected function processRenewals($renewIDs, $origData, $renewData)
    {
        $response = ['ids' => $renewIDs];
        $i = 0;
        foreach ($origData->itemout as $item) {
            $ikey = (string)$item->ikey;
            if (in_array($ikey, $renewIDs)) {
                $response['details'][$ikey]['item_id'] = $ikey;
                $origRenewals = (string)$item->numrenewals;
                $currentRenewals = (string)$renewData->itemout[$i]->numrenewals;

                $dueDate = (string)$renewData->itemout[$i]->duedate;
                $renewerror = (string)$renewData->itemout[$i]->renewerror;

                // Convert Horizon Format to display format
                if (!empty($dueDate)) {
                    $dueDate = $this->dateFormat->convertToDisplayDate(
                        $this->wsDateFormat,
                        $dueDate
                    );
                }

                if ($currentRenewals > $origRenewals) {
                    $response['details'][$ikey] = [
                        'item_id' => $ikey,
                        'new_date' =>  $dueDate,
                        'success' => true,
                    ];
                } else {
                    $response['details'][$ikey] = [
                    'item_id' => $ikey,
                    'new_date' => '',
                        'success'    => false,
                        'sysMessage' => $renewerror,
                    ];
                }
            }
            $i++;
        }
        return $response;
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
        $renewItemKeys = [];
        $renewIDs = [];
        $renewals = $renewDetails['details'];
        $userBarcode = $renewDetails['patron']['id'];
        $userPassword = $renewDetails['patron']['cat_password'];

        $session = $this->registerUser($userBarcode, $userPassword);
        if ($session) {
            // Get Items
            $origData = $this->getItems($session);
            if ($origData) {
                // Build Params
                foreach ($renewals as $item) {
                    [$itemID, $barcode] = explode('|', $item);
                    $renewItemKeys[] = $barcode;
                    $renewIDs[] = $itemID;
                }
                // Renew Items
                $renewData = $this->renewItems($session, $renewItemKeys);
                if ($renewData) {
                    $response = $this->processRenewals(
                        $renewIDs,
                        $origData,
                        $renewData
                    );
                    return $response;
                }
            }
        }

        return ['blocks' => ['authentication_error_admin']];
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
        return $checkOutDetails['item_id'] . '|' . $checkOutDetails['barcode'];
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
        $cancelDetails = $holdDetails['id'] . '|' . $holdDetails['item_id'];
        return $cancelDetails;
    }
}
