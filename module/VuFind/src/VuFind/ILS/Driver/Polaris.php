<?php
/**
 * Polaris ILS Driver (POCA)
 *
 * PHP version 5
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  ILS_Drivers
 * @author   BookSite <vufind-tech@lists.sourceforge.net>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_an_ils_driver Wiki
 */
namespace VuFind\ILS\Driver;
use VuFind\Config\Reader as ConfigReader,
    VuFind\Exception\ILS as ILSException;

/**
 * VuFind Connector for Polaris
 *
 * Based on Polaris 1.4 API
 *
 * @category VuFind2
 * @package  ILS_Drivers
 * @author   BookSite <vufind-tech@lists.sourceforge.net>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_an_ils_driver Wiki
 */
class Polaris extends AbstractBase implements \VuFindHttp\HttpServiceAwareInterface
{
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
     * Web services requesting organization ID
     *
     * @var string
     */
    protected $ws_requestingorgid;

    /**
     * HTTP service
     *
     * @var \VuFindHttp\HttpServiceInterface
     */
    protected $httpService = null;

    /**
     * Set the HTTP service to be used for HTTP requests.
     *
     * @param HttpServiceInterface $service HTTP service
     *
     * @return void
     */
    public function setHttpService(\VuFindHttp\HttpServiceInterface $service)
    {
        $this->httpService = $service;
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
            = isset($this->config['Holds']['defaultPickUpLocation'])
            ? $this->config['Holds']['defaultPickUpLocation'] : null;
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
    protected function makeRequest($api_query, $http_method="GET",
        $patronpassword = "", $json = false
    ) {
        // TODO, just make this for this one call
        date_default_timezone_set('GMT');
        $date = date("D, d M Y H:i:s T");

        $url = $this->ws_host . $this->ws_app . $api_query;

        $signature_text = $http_method.$url.$date.$patronpassword;
        $signature = base64_encode(
            hash_hmac('sha1', $signature_text, $this->ws_api_key, true)
        );

        $auth_token = "PWS {$this->ws_api_id}:$signature";
        $http_headers = array(
            "Content-type: application/json",
            "Accept: application/json",
            "PolarisDate: $date",
            "Authorization: $auth_token"
        );

        try {
            $client = $this->httpService->createClient($url);

            // Attach JSON if necessary
            if ($json !== false) {
                $json_data = json_encode($json);
                $client->setRawBody($json_data);
                $client->setEncType('application/json');
            }

            $client->setHeaders($http_headers);
            $client->setMethod($http_method);
            $result = $client->send();
        } catch (\Exception $e) {
            throw new ILSException($e->getMessage());
        }

        if (!$result->isSuccess()) {
            throw new ILSException('HTTP error');
        }

        return json_decode($result->getBody());
    }

    /**
     * return human-readable date from text like Date(1360051200000-0800)
     *
     * @param string $jsontime Input
     *
     * @return string
     */
    public function formatJSONTime($jsontime)
    {
        preg_match('/Date\((\d+)\-(\d){2}(\d){2}\)/', $jsontime, $matches);
        $matchestmp = intval($matches[1]/1000);
        $date = gmdate("n-j-Y", $matchestmp);
        return $date;
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
        $holds = array();
        $response = $this->makeRequest(
            "patron/{$patron['cat_username']}/holdrequests/active", 'GET',
            $patron['cat_password']
        );
        $holds_response_array = $response->PatronHoldRequestsGetRows;
        foreach ($holds_response_array as $holds_response) {

            $create = $this->formatJSONTime($holds_response->ActivationDate);
            $expire = $this->formatJSONTime($holds_response->ExpirationDate);
            $holds[] = array(
                'id'             => $holds_response->BibID,
                'location' => $holds_response->PickupBranchName,
                'reqnum'     => $holds_response->HoldRequestID,
                'expire'     => $expire,
                'create'     => $create,
                'position' => $holds_response->QueuePosition,
                'title'      => $holds_response->Title,
            );

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
        $holding = array();
        $response = $this->makeRequest("bib/$id/holdings");
        $holdings_response_array = $response->BibHoldingsGetRows;

        $copy_count = 0;
        foreach ($holdings_response_array as $holdings_response) {
            //$holdings_response = $holdings_response_array[0];
            $copy_count++;

            $availability = 0;
            if (($holdings_response->CircStatus == 'In')
                || ($holdings_response->CircStatus == 'Just Returned')
                || ($holdings_response->CircStatus == 'On Shelf')
            ) {
                $availability = 1;
            }

            $duedate = '';
            if ($holdings_response->DueDate) {
                $duedate = date("n-j-Y", strtotime($holdings_response->DueDate));
            }

            $holding[] = array(
                'availability' => $availability,
                'id'                 => $id,
                'status'         => $holdings_response->CircStatus,
                'location'   => $holdings_response->LocationName,
                //'reserve'      => 'No',
                'callnumber' => $holdings_response->CallNumber,
                'duedate'    => $duedate,
                //'number'   => $holdings_response->ItemsIn,
                'number'         => $copy_count,
                'barcode'    => $holdings_response->Barcode,
            );

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
        $items = array();
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
     *
     * @return array An array with key-value pairs.
     */
    public function getConfig($function)
    {
        if (isset($this->config[$function]) ) {
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
     * @param string $id     The record id to retrieve the holdings for
     * @param array  $patron Patron data
     *
     * @return mixed         On success, an associative array with the following
     * keys: id, availability (boolean), status, location, reserve, callnumber,
     * duedate, number, barcode.
     */
    public function getHolding($id, $patron = false)
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
        $api_query = 'holdrequest';
        $http_method = 'POST';

        // what do workstation & userid really mean in this context?
        $workstationid = '1';
        $userid = '1';

        // all activations are for now(), for now.
        // microtime is msec or sec?? seems to have changed
        $activationdate = '/Date(' . intval(microtime(true) * 1000) .')/';

        $jsonrequest = array(
            'PatronID' => $holdDetails['patron']['id'],
            'BibID'      => $holdDetails['id'],
            'ItemBarcode'  => '',
            'VolumeNumber' => '',
            'Designation'  => '',
            'PickupOrgID'       => $holdDetails['pickUpLocation'],
            'IsBorrowByMail'    => '0',
            'PatronNotes'       => $holdDetails['comment'],
            'ActivationDate'    => $activationdate,
            'WorkstationID'     => $workstationid,
            'UserID'                    => $userid,
            'RequestingOrgID' => $this->ws_requestingorgid,
            'TargetGUID'            => '',
        );

        $response = $this->makeRequest('holdrequest', 'POST', '', $jsonrequest);

        if ($response->StatusValue == 1) {
            return array('success' => true,  'sysMessage' => $response->Message);
        } else {
            return array('success' => false, 'sysMessage' => $response->Message);
        }

    }

    /**
     * Get Pick Up Locations
     *
     * This is responsible for gettting a list of valid library locations for
     * holds / recall retrieval
     *
     * @param array $patron      Patron information returned by the patronLogin
     * method.
     * @param array $holdDetails Optional array, only passed in when getting a list
     * in the context of placing a hold; contains most of the same values passed to
     * placeHold, minus the patron data.    May be used to limit the pickup options
     * or may be ignored.  The driver must not add new options to the return array
     * based on this data or other areas of VuFind may behave incorrectly.
     *
     * @throws ILSException
     * @return array             An array of associative arrays with locationID
     * and locationDisplay keys
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getPickUpLocations($patron = false, $holdDetails = null)
    {
        if (isset($this->ws_pickUpLocations)) {
            // hardcoded pickup locations in the .ini file? or...
            foreach ($this->ws_pickUpLocations as $code => $library) {
                $locations[] = array(
                    'locationID'            => $code,
                    'locationDisplay' => $library
                );
            }
        } else {
            // we get them from the API
            $response = $this->makeRequest("organizations/branch");
            $locations_response_array = $response->OrganizationsGetRows;
            foreach ($locations_response_array as $location_response) {
                $locations[] = array(
                    'locationID'            => $location_response->OrganizationID,
                    'locationDisplay' => $location_response->Name,
                );
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
     * @return array             Associative array with 'count' and 'results' keys
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getNewItems($page, $limit, $daysOld, $fundId = null)
    {
        return array('count' => 0, 'results' => array());
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
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function findReserves($course, $inst, $dept)
    {
        return array();
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
        $response = $this->makeRequest("patron/$username", "GET", "$password");

        if (!$response->ValidPatron) {
            return null;
        }

        $user = array();

        $user['id']                     = $response->PatronID;
        $user['firstname']      = null;
        $user['lastname']       = null;
        $user['cat_username'] = $response->Barcode;
        $user['cat_password'] = $password;
        $user['email']              = null;
        $user['major']              = null;
        $user['college']            = null;

        return $user;
    }

    /**
     * Get Patron Profile
     *
     * This is responsible for retrieving the profile for a specific patron.
     *
     * @param array $userinfo The patron array
     *
     * @throws ILSException
     * @return array          Array of the patron's profile data on success.
     */
    public function getMyProfile($userinfo)
    {
        return $userinfo;
    }
}
