<?php

/**
 * Polaris ILS Driver
 *
 * PHP version 8
 *
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
 * @author   BookSite <vufind-tech@lists.sourceforge.net>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */

namespace VuFind\ILS\Driver;

use VuFind\Exception\ILS as ILSException;

use function count;
use function intval;
use function strlen;

/**
 * VuFind Connector for Polaris
 *
 * Based on Polaris 1.4 API
 *
 * @category VuFind
 * @package  ILS_Drivers
 * @author   BookSite <vufind-tech@lists.sourceforge.net>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
class Polaris extends AbstractBase implements \VuFindHttp\HttpServiceAwareInterface
{
    use \VuFindHttp\HttpServiceAwareTrait;

    /**
     * Web services host
     *
     * @var string
     */
    protected $ws_host;

    /**
     * Web services application path
     *
     * @var string
     */
    protected $ws_app;

    /**
     * Web services ID
     *
     * @var string
     */
    protected $ws_api_id;

    /**
     * Web services key
     *
     * @var string
     */
    protected $ws_api_key;

    /**
     * Default pick up location
     *
     * @var string
     */
    protected $defaultPickUpLocation;

    /**
     * Web services requesting organization ID
     *
     * @var string
     */
    protected $ws_requestingorgid;

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
        if (empty($this->config) || !isset($this->config['PAPI'])) {
            throw new ILSException('Configuration needs to be set.');
        }

        // Define Polaris PAPI parameters
        $this->ws_host    = $this->config['PAPI']['ws_host'];
        $this->ws_app     = $this->config['PAPI']['ws_app'];
        $this->ws_api_id  = $this->config['PAPI']['ws_api_id'];
        $this->ws_api_key = $this->config['PAPI']['ws_api_key'];
        $this->ws_requestingorgid    = $this->config['PAPI']['ws_requestingorgid'];
        $this->defaultPickUpLocation
            = $this->config['Holds']['defaultPickUpLocation'] ?? null;
    }

    /**
     * Make Request
     *
     * Makes a request to the Polaris Restful API
     *
     * @param string $api_query      Query string for request
     * @param string $http_method    HTTP method (default = GET)
     * @param string $patronpassword Patron password (optional)
     * @param bool   $json           Optional JSON attachment
     *
     * @throws ILSException
     * @return obj
     */
    protected function makeRequest(
        $api_query,
        $http_method = 'GET',
        $patronpassword = '',
        $json = false
    ) {
        // auth has to be in GMT, otherwise use config-level TZ
        $site_config_TZ = date_default_timezone_get();
        date_default_timezone_set('GMT');
        $date = date('D, d M Y H:i:s T');
        date_default_timezone_set($site_config_TZ);

        $url = $this->ws_host . $this->ws_app . $api_query;

        $signature_text = $http_method . $url . $date . $patronpassword;
        $signature = base64_encode(
            hash_hmac('sha1', $signature_text, $this->ws_api_key, true)
        );

        $auth_token = "PWS {$this->ws_api_id}:$signature";
        $http_headers = [
            'Content-type: application/json',
            'Accept: application/json',
            "PolarisDate: $date",
            "Authorization: $auth_token",
        ];

        try {
            $client = $this->httpService->createClient($url);

            // Attach JSON if necessary
            $json_data = null;
            if ($json !== false) {
                $json_data = json_encode($json);
                $client->setRawBody($json_data);
                $client->setEncType('application/json');
            }

            // httpService doesn't explicitly support PUT, so add this:
            if ($http_method == 'PUT') {
                $http_headers[] = 'Content-Length: ' . strlen($json_data);
            }
            $client->setHeaders($http_headers);
            $client->setMethod($http_method);
            $result = $client->send();
        } catch (\Exception $e) {
            $this->throwAsIlsException($e);
        }

        if (!$result->isSuccess()) {
            throw new ILSException('HTTP error');
        }

        return json_decode($result->getBody());
    }

    /**
     * Return human-readable date from text like Date(1360051200000-0800)
     *
     * @param string $jsontime Input
     *
     * @return string
     */
    public function formatJSONTime($jsontime)
    {
        preg_match('/Date\((\d+)\-(\d){2}(\d){2}\)/', $jsontime, $matches);
        if (count($matches) > 0) {
            $matchestmp = intval($matches[1] / 1000);
            $date = date('n-j-Y', $matchestmp);
        } else {
            $date = 'n/a';
        }
        return $date;
    }

    /**
     * Encode from human-readable date to text like Date(1360051200000-0800)
     *
     * @param string $date Input
     *
     * @return string
     */
    public function encodeJSONTime($date)
    {
        // auth has to be in GMT, otherwise use config-level TZ
        //$site_config_TZ = date_default_timezone_get();
        //date_default_timezone_set('GMT');
        $unix_time = strtotime($date);
        //date_default_timezone_set($site_config_TZ);

        $json_time = '/Date(' . $unix_time . '000)/';
        return $json_time;
    }

    /**
     * Get Patron Holds
     *
     * This is responsible for retrieving all holds by a specific patron.
     *
     * @param array $patron The patron array from patronLogin
     *
     * @return mixed                Array of the patron's holds on success.
     */
    public function getMyHolds($patron)
    {
        $holds = [];
        $response = $this->makeRequest(
            "patron/{$patron['cat_username']}/holdrequests/all",
            'GET',
            $patron['cat_password']
        );
        $holds_response_array = $response->PatronHoldRequestsGetRows;
        foreach ($holds_response_array as $holds_response) {
            // only display item if it is NOT expired
            if ($holds_response->StatusID > 8) {
                continue;
            }

            $create = $this->formatJSONTime($holds_response->ActivationDate);
            $expire = $this->formatJSONTime($holds_response->ExpirationDate);

            $holds[] = [
                'type'     => $holds_response->StatusDescription,
                'id'       => $holds_response->BibID,
                'location' => $holds_response->PickupBranchName,
                'reqnum'   => $holds_response->HoldRequestID,
                'expire'   => $expire,
                'create'   => $create,
                'position' => $holds_response->QueuePosition,
                'title'    => $holds_response->Title,
            ];
        }
        return $holds;
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
     * id, availability (boolean), status, location, reserve, callnumber.
     */
    public function getStatus($id)
    {
        $holding = [];
        $response = $this->makeRequest("bib/$id/holdings");
        $holdings_response_array = $response->BibHoldingsGetRows;

        $copy_count = 0;
        foreach ($holdings_response_array as $holdings_response) {
            //$holdings_response = $holdings_response_array[0];
            $copy_count++;

            $availability = 0;
            if (
                ($holdings_response->CircStatus == 'In')
                || ($holdings_response->CircStatus == 'Just Returned')
                || ($holdings_response->CircStatus == 'On Shelf')
                || ($holdings_response->CircStatus == 'Available - Check shelves')
            ) {
                $availability = 1;
            }

            $duedate = '';
            if ($holdings_response->DueDate) {
                $duedate = date('n-j-Y', strtotime($holdings_response->DueDate));
            }

            $holding[] = [
                'availability' => $availability,
                'id'         => $id,
                'status'     => $holdings_response->CircStatus,
                'location'   => $holdings_response->LocationName,
                //'reserve'  => 'No',
                'callnumber' => $holdings_response->CallNumber,
                'duedate'    => $duedate,
                //'number'   => $holdings_response->ItemsIn,
                'number'     => $copy_count,
                'barcode'         => $holdings_response->Barcode,
                'shelf_location'  => $holdings_response->ShelfLocation,
                'collection_name' => $holdings_response->CollectionName,
                //'per_item_holdable' => $per_item_holdable,
                //'designation' => $designation,
                'holdable' => $holdings_response->Holdable,
            ];
        }
        return $holding;
    }

    /**
     * Get Statuses
     *
     * This is responsible for retrieving the status information for a
     * collection of records.
     *
     * @param array $ids The array of record ids to retrieve the status for
     *
     * @return mixed         An array of getStatus() return values on success.
     */
    public function getStatuses($ids)
    {
        $items = [];
        $count = 0;
        foreach ($ids as $id) {
            $items[$count] = $this->getStatus($id);
            $count++;
        }
        return $items;
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
     * Get Holding
     *
     * This is responsible for retrieving the holding information of a certain
     * record.
     *
     * @param string $id      The record id to retrieve the holdings for
     * @param array  $patron  Patron data
     * @param array  $options Extra options (not currently used)
     *
     * @return mixed         On success, an associative array with the following
     * keys: id, availability (boolean), status, location, reserve, callnumber,
     * duedate, number, barcode.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getHolding($id, array $patron = null, array $options = [])
    {
        return $this->getStatus($id);
    }

    /**
     * Place Hold
     *
     * Attempts to place a hold or recall on a particular item and returns
     * an array with result details.
     *
     * @param array $holdDetails An array of item and patron data
     *
     * @return mixed An array of data on the request including
     * whether or not it was successful and a system message (if available)
     */
    public function placeHold($holdDetails)
    {
        // what do workstation & userid really mean in this context?
        $workstationid = '1';
        $userid = '1';

        // all activations are for now(), for now.
        // microtime is msec or sec?? seems to have changed
        $activationdate = '/Date(' . intval(microtime(true) * 1000) . ')/';
        if (empty($holdDetails['barcode'])) {
            $holdDetails['barcode'] = '';
        }

        $jsonrequest = [
            'PatronID'     => $holdDetails['patron']['id'],
            'BibID'        => $holdDetails['id'],
            'ItemBarcode'  => $holdDetails['barcode'],
            'VolumeNumber' => '',
            'Designation'  => '',
            'PickupOrgID'     => $holdDetails['pickUpLocation'],
            'IsBorrowByMail'  => '0',
            'PatronNotes'     => $holdDetails['comment'],
            'ActivationDate'  => $activationdate,
            'WorkstationID'   => $workstationid,
            'UserID'          => $userid,
            'RequestingOrgID' => $this->ws_requestingorgid,
            'TargetGUID'      => '',
        ];

        $response = $this->makeRequest('holdrequest', 'POST', '', $jsonrequest);

        if ($response->StatusValue == 1) {
            return [ 'success' => true,  'sysMessage' => $response->Message ];
        } elseif ($response->StatusValue == 5) {
            // auto say "yes" to Conditional: Accept even with existing holds
            // response
            $reply_jsonrequest = [
                // apparent bug in API, TxnGroupQualifer missing final "i"
                'TxnGroupQualifier' => $response->TxnGroupQualifer,
                'TxnQualifier' => $response->TxnQualifier,
                'RequestingOrgID' => $this->ws_requestingorgid,
                'Answer' => 1,
                'State' => 5,
            ];

            $reply_response = $this->makeRequest(
                "holdrequest/{$response->RequestGUID}",
                'PUT',
                '',
                $reply_jsonrequest
            );

            if ($reply_response->StatusValue == 1) {
                // auto-reply success
                return [ 'success' => true,  'sysMessage' => $response->Message ];
            } else {
                return [ 'success' => false, 'sysMessage' => $response->Message ];
            }
        } else {
            return [ 'success' => false, 'sysMessage' => $response->Message ];
        }
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
     * @return array             An array of associative arrays with locationID
     * and locationDisplay keys
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getPickUpLocations($patron = false, $holdDetails = null)
    {
        $locations = [];
        if (isset($this->ws_pickUpLocations)) {
            // hardcoded pickup locations in the .ini file? or...
            foreach ($this->ws_pickUpLocations as $code => $library) {
                $locations[] = [
                    'locationID'      => $code,
                    'locationDisplay' => $library,
                ];
            }
        } else {
            // we get them from the API
            $response = $this->makeRequest('organizations/branch');
            $locations_response_array = $response->OrganizationsGetRows;
            foreach ($locations_response_array as $location_response) {
                $locations[] = [
                    'locationID'      => $location_response->OrganizationID,
                    'locationDisplay' => $location_response->Name,
                ];
            }
        }
        return $locations;
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
     * placeHold, minus the patron data.    May be used to limit the pickup options
     * or may be ignored.
     *
     * @return string           The default pickup location for the patron.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getDefaultPickUpLocation($patron = false, $holdDetails = null)
    {
        return $this->defaultPickUpLocation;
    }

    /**
     * Get Purchase History
     *
     * This is responsible for retrieving the acquisitions history data for the
     * specific record (usually recently received issues of a serial).
     *
     * @param string $id The record id to retrieve the info for
     *
     * @return mixed         An array with the acquisitions data on success.
     */
    public function getPurchaseHistory($id)
    {
        return [];
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
     * @return array             Associative array with 'count' and 'results' keys
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getNewItems($page, $limit, $daysOld, $fundId = null)
    {
        return ['count' => 0, 'results' => []];
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
     * @return mixed An array of associative arrays representing reserve items.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function findReserves($course, $inst, $dept)
    {
        return [];
    }

    /**
     * Patron Login
     *
     * This is responsible for authenticating a patron against the catalog.
     *
     * @param string $username The patron username
     * @param string $password The patron password
     *
     * @return mixed           Associative array of patron info on successful login,
     * null on unsuccessful login.
     */
    public function patronLogin($username, $password)
    {
        // username == barcode
        $response = $this->makeRequest("patron/$username", 'GET', "$password");

        if (!$response->ValidPatron) {
            return null;
        }

        $user = [];

        $user['id']           = $response->PatronID;
        $user['firstname']    = null;
        $user['lastname']     = null;
        $user['cat_username'] = $response->PatronBarcode;
        $user['cat_password'] = $password;
        $user['email']        = null;
        $user['major']        = null;
        $user['college']      = null;

        return $user;
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
        $fineList = [];

        $response = $this->makeRequest(
            "patron/{$patron['cat_username']}/account/outstanding",
            'GET',
            $patron['cat_password']
        );
        $fines_response_array = $response->PatronAccountGetRows;

        foreach ($fines_response_array as $fines_response) {
            $fineList[] = [
            // fees in vufind are in pennies
            'amount'   => $fines_response->TransactionAmount * 100,
            'checkout' => $this->formatJSONTime($fines_response->CheckOutDate),
            'fine'     => $fines_response->FeeDescription,
            'balance'  => $fines_response->OutstandingAmount * 100,
            'duedate'    => $this->formatJSONTime($fines_response->DueDate),
            'createdate' => $this->formatJSONTime($fines_response->TransactionDate),
            'id'    => $fines_response->BibID,
            'title' => $fines_response->Title,
            ];
        }

        return $fineList;
    }

    /**
     * Get Patron Profile
     *
     * This is responsible for retrieving the profile for a specific patron.
     *
     * @param array $patron The patron array
     *
     * @throws ILSException
     * @return array Array of the patron's profile data on success.
     */
    public function getMyProfile($patron)
    {
        // firstname, lastname, address1, address2, zip, phone, group
        $response = $this->makeRequest(
            "patron/{$patron['cat_username']}/basicdata",
            'GET',
            $patron['cat_password']
        );
        $profile_response = $response->PatronBasicData;
        $profile = [
          'firstname' => $profile_response->NameFirst,
          'lastname'  => $profile_response->NameLast,
          'phone'     => $profile_response->PhoneNumber,
        ];
        return $profile;
    }

    /**
     * Get Patron Transactions
     *
     * This is responsible for retrieving all transactions (i.e. checked out items)
     * by a specific patron.
     *
     * @param array $patron The patron array from patronLogin
     *
     * @return mixed Array of associative arrays of the patron's transactions on
     * success.
     */
    public function getMyTransactions($patron)
    {
        // duedate, id, barcode, renew (count), request (pending count),
        // volume (vol number), publication_year, renewable, message, title, item_id
        // polaris apis: PatronItemsOutGet
        $transactions = [];
        $response = $this->makeRequest(
            "patron/{$patron['cat_username']}/itemsout/all",
            'GET',
            $patron['cat_password']
        );

        foreach ($response->PatronItemsOutGetRows as $trResponse) {
            // any more renewals available?
            if (($trResponse->RenewalLimit - $trResponse->RenewalCount) > 0) {
                $renewable = true;
            } else {
                $renewable = false;
            }
            $transactions[] = [
                'duedate' => $this->formatJSONTime($trResponse->DueDate),
                'id'      => $trResponse->BibID,
                'barcode' => $trResponse->Barcode,
                'renew'   => $trResponse->RenewalCount,
                'renewLimit' => $trResponse->RenewalLimit,
                'renewable' => $renewable,
                'title'   => $trResponse->Title,
                'item_id' => $trResponse->ItemID,
            ];
        }
        return $transactions;
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
        $renew_ids = $renewDetails['details'];
        $patron = $renewDetails['patron'];
        $count = 0;
        $item_response = [];
        $item_blocks = [];

        foreach ($renew_ids as $renew_id) {
            $jsonrequest = [];
            $jsonrequest['Action'] = 'renew';
            $jsonrequest['LogonBranchID']      = '1';
            $jsonrequest['LogonUserID']        = '1';
            $jsonrequest['LogonWorkstationID'] = '1';
            $jsonrequest['RenewData']['IgnoreOverrideErrors'] = 'true';

            $response = $this->makeRequest(
                "patron/{$patron['cat_username']}/itemsout/$renew_id",
                'PUT',
                $patron['cat_password'],
                $jsonrequest
            );
            if ($response->PAPIErrorCode == 0) {
                $count++;
                $item_response[$renew_id] = [
                'success'  => true,
                'new_date' => $this->formatJSONTime(
                    $response->ItemRenewResult->DueDateRows[0]->DueDate
                ),
                'item_id'  =>
                    $response->ItemRenewResult->DueDateRows[0]->ItemRecordID,
                ];
            } elseif ($response->PAPIErrorCode == -2) {
                $item_blocks[$renew_id]
                    = $response->ItemRenewResult->BlockRows[0]->ErrorDesc;
                $item_response[$renew_id] = [
                'success'  => -1,
                'new_date' => false,
                'item_id' => $response->ItemRenewResult->BlockRows[0]->ItemRecordID,
                'sysMessage' => $response->ItemRenewResult->BlockRows[0]->ErrorDesc,
                ];
            }
        }
        $result = [
            'count' => $count, 'details' => $item_response,
            'blocks' => $item_blocks,
        ];

        return $result;
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
        $renewDetails = $checkOutDetails['item_id'];
        return $renewDetails;
    }

    /**
     * Cancel Holds
     *
     * Attempts to Cancel a hold or recall on a particular item. The
     * data in $cancelDetails['details'] is determined by getCancelHoldDetails().
     *
     * @param array $cancelDetails An array of item and patron data
     *
     * @return array An array of data on each request including whether or not it
     * was successful and a system message (if available)
     */
    public function cancelHolds($cancelDetails)
    {
        $hold_ids = $cancelDetails['details'];
        $patron = $cancelDetails['patron'];
        $count = 0;
        $item_response = [];

        foreach ($hold_ids as $hold_id) {
            $response = $this->makeRequest(
                "patron/{$patron['cat_username']}/holdrequests/$hold_id/cancelled"
                . '?wsid=1&userid=1',
                'PUT',
                $patron['cat_password']
            );

            if ($response->PAPIErrorCode == 0) {
                $count++;
                $item_response[$hold_id] = [
                'success' => true,
                'status'  => 'hold_cancel_success',
                ];
            } else {
                $item_response[$hold_id] = [
                'success' => false,
                'status'  => 'hold_cancel_fail',
                'sysMessage' => 'Failure calling ILS to cancel hold',
                ];
            }
        }

        $result = [ 'count' => $count, 'items' => $item_response ];
        return $result;
    }

    /**
     * Get Cancel Hold Details
     *
     * @param array $holdDetails A single hold array from getMyHolds
     * @param array $patron      Patron information from patronLogin
     *
     * @return string Data for use in a form field (just request id is all Polaris
     * needs)
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getCancelHoldDetails($holdDetails, $patron = [])
    {
        return $holdDetails['reqnum'];
    }

    /**
     * Get Checkout History
     *
     * Returns the patrons checkout / reading history
     *
     * @param array $patron The patron array from patronLogin
     *
     * @return mixed Array of the patron's checkouts on success.
     */
    public function getCheckoutHistory($patron)
    {
        // get number of pages, only get most recent max 200 items (last 2 pages)
        // TODO: use real pagination, not just recent items.
        $items_per_page = 100;

        $response = $this->makeRequest(
            "patron/{$patron['cat_username']}/readinghistory?rowsperpage=1&page=-1",
            'GET',
            $patron['cat_password']
        );

        // error code returns number of results
        $count = $response->PAPIErrorCode;

        if ($count == 0) {
            return;
        }

        $pages = ceil($count / $items_per_page);

        $penultimate_page = $pages - 1;

        if ($penultimate_page > 0) {
            $page_offset = $penultimate_page;
        } else {
            $page_offset = $pages;
        }

        $checkouts = [];
        while ($page_offset <= $pages) {
            $response = $this->makeRequest(
                "patron/{$patron['cat_username']}/readinghistory?rowsperpage="
                . "$items_per_page&page=$page_offset",
                'GET',
                $patron['cat_password']
            );

            $checkout_history_array = $response->PatronReadingHistoryGetRows;
            foreach ($checkout_history_array as $checkout_response) {
                $date = $this->formatJSONTime($checkout_response->CheckOutDate);
                $checkouts[] = [
                   'id' => $checkout_response->BibID,
                   'title' => $checkout_response->Title,
                   'format' => $checkout_response->FormatDescription,
                   'location' => $checkout_response->LoaningBranchName,
                   'date' => $date,
                   'author' => $checkout_response->Author,
                   ];
            }
            $page_offset++;
        }
        // show most recent checkouts first
        $checkouts = array_reverse($checkouts);

        return $checkouts;
    }

    /**
     * Get Hold Count
     *
     * Returns the count of a hold based on API call to bibid
     *
     * @param array $id bib id
     *
     * @return string count of holds
     */
    public function getHoldCount($id)
    {
        $response = $this->makeRequest("bib/$id");
        $holdings_response_array = $response->BibGetRows;
        $hold_count = 0;
        foreach ($holdings_response_array as $response) {
            if ($response->ElementID == '8') {
                // that's the current holds field, could also be pulled by label
                // instead?
                if ($response->Value > 0) {
                    $hold_count = $response->Value;
                }
                break;
            }
        }
        return $hold_count;
    }

    /**
     * Suspend Holds
     *
     * Attempts to Suspend a hold or recall on a particular item. The
     * data in $suspendDetails['details'] is determined by getSuspendHoldDetails().
     *
     * @param array $suspendDetails An array of item and patron data
     *
     * @return array An array of data on each request including whether or not it
     * was successful and a system message (if available)
     */
    public function suspendHolds($suspendDetails)
    {
        $hold_ids = $suspendDetails['details'];
        $patron = $suspendDetails['patron'];

        $jsondate = $this->encodeJSONTime($suspendDetails['date']);

        $count = 0;
        $item_response = [];

        foreach ($hold_ids as $hold_id) {
            $jsonrequest = [
                 'UserID' => '1',
                 'ActivationDate' => "$jsondate",
                ];

            $response = $this->makeRequest(
                "patron/{$patron['cat_username']}/holdrequests/$hold_id/inactive",
                'PUT',
                $patron['cat_password'],
                $jsonrequest
            );

            if ($response->PAPIErrorCode == 0) {
                $count++;
                $item_response[$hold_id] = [
                  'success' => true,
                  'status'  => 'hold_suspend_success',
                ];
            } else {
                $item_response[$hold_id] = [
                'success' => false,
                'status'  => 'hold_suspend_fail',
                'sysMessage' => 'Failure calling ILS to suspend hold',
                ];
            }
        }

        $result = [ 'count' => $count, 'items' => $item_response ];
        return $result;
    }

    /**
     * Get Suspend Hold Details
     *
     * @param array $holdDetails An array of item data
     *
     * @return string Data for use in a form field (just request id is all Polaris
     * needs)
     */
    public function getSuspendHoldDetails($holdDetails)
    {
        return $holdDetails['reqnum'];
    }

    /**
     * Reactivate Holds
     *
     * Attempts to Reactivate a hold or recall on a particular item. The
     * data in $reactivateDetails['details'] is determined by
     * getReactivateHoldDetails().
     *
     * @param array $reactivateDetails An array of item and patron data
     *
     * @return array An array of data on each request including whether or not it
     * was successful and a system message (if available)
     */
    public function reactivateHolds($reactivateDetails)
    {
        $hold_ids = $reactivateDetails['details'];
        $patron = $reactivateDetails['patron'];

        $date = date('d/M/Y');
        $jsondate = $this->encodeJSONTime($date);

        $count = 0;
        $item_response = [];

        foreach ($hold_ids as $hold_id) {
            $jsonrequest = [
                 'UserID' => '1',
                 'ActivationDate' => "$jsondate",
                 ];

            $response = $this->makeRequest(
                "patron/{$patron['cat_username']}/holdrequests/$hold_id/active",
                'PUT',
                $patron['cat_password'],
                $jsonrequest
            );

            if ($response->PAPIErrorCode == 0) {
                $count++;
                $item_response[$hold_id] = [
                  'success' => true,
                  'status'  => 'hold_reactivate_success',
                ];
            } else {
                $item_response[$hold_id] = [
                'success' => false,
                'status'  => 'hold_reactivate_fail',
                'sysMessage' => 'Failure calling ILS to reactivate hold',
                ];
            }
        }

        $result = [ 'count' => $count, 'items' => $item_response ];
        return $result;
    }
}
