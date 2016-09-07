<?php
/**
 * KohaRest ILS Driver
 *
 * PHP version 5
 *
 * Copyright (C) Alex Sassmannshausen, PTFS Europe 2014.
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
 * @author   Alex Sassmannshausen, <alex.sassmannshausen@ptfs-europe.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_an_ils_driver Wiki
 */
namespace VuFind\ILS\Driver;
use VuFind\Exception\ILS as ILSException;
use VuFindHttp\HttpServiceInterface;
use Zend\Log\LoggerInterface;
use VuFind\Exception\Date as DateException;

/**
 * VuFind Driver for Koha, using web APIs (version: 0.1)
 *
 * last updated: 05/13/2014
 *
 * @category VuFind2
 * @package  ILS_Drivers
 * @author   Alex Sassmannshausen, <alex.sassmannshausen@ptfs-europe.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_an_ils_driver Wiki
 */
class KohaRest extends AbstractBase implements \VuFindHttp\HttpServiceAwareInterface,
    \Zend\Log\LoggerAwareInterface
{
    /**
     * Web services host
     *
     * @var string
     */
    protected $host;

    /**
     * Web services application path
     *
     * @var string
     */
    protected $api_path = "/cgi-bin/koha/ilsdi.pl?service=";

    /**
     * ILS base URL
     *
     * @var string
     */
    protected $ilsBaseUrl;

    /**
     * Location codes
     *
     * @var array
     */
    protected $locations;

    /**
     * Location codes
     *
     * @var string
     */
    protected $default_location;

    /**
     * Set the logger
     *
     * @param LoggerInterface $logger Logger to use.
     *
     * @return void
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    protected $logger = false;

    /**
     * Show a debug message.
     *
     * @param string $msg Debug message.
     *
     * @return void
     */
    protected function debug($msg)
    {
        if ($this->logger) {
            $this->logger->debug($msg);
        }
    }

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
    public function setHttpService(HttpServiceInterface $service)
    {
        $this->httpService = $service;
    }

    /**
     * Date converter object
     *
     * @var \VuFind\Date\Converter
     */
    protected $dateConverter;

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

        // Is debugging enabled?
        $this->debug_enabled = isset($this->config['Catalog']['debug'])
            ? $this->config['Catalog']['debug'] : false;

        // Base for API address
        $this->host = isset($this->config['Catalog']['host']) ?
            $this->config['Catalog']['host'] : "http://localhost";

        // Storing the base URL of ILS
        $this->ilsBaseUrl = $this->config['Catalog']['url'];

        // Location codes are defined in 'KohaRest.ini'
        $this->locations = isset($this->config['pickUpLocations'])
            ? $this->config['pickUpLocations'] : false;

        // Default location defined in 'KohaRest.ini'
        $this->default_location
            = isset($this->config['Holds']['defaultPickUpLocation'])
            ? $this->config['Holds']['defaultPickUpLocation'] : null;

        // Create a dateConverter
        $this->dateConverter = new \VuFind\Date\Converter;

        if ($this->debug_enabled) {
            $this->debug("Config Summary:");
            $this->debug("Debug: " . $this->debug_enabled);
            $this->debug("API Host: " . $this->host);
            $this->debug("ILS URL: " . $this->ilsBaseUrl);
            $this->debug("Locations: " . $this->locations);
            $this->debug("Default Location: " . $this->default_location);
        }
    }

    /**
     * getField 
     *
     * Check $contents is not "", return it; else return $default.
     *
     * @param string $contents string to be checked
     * @param string $default  value to return if $contents is ""
     *
     * @return $contents or $default
     */
    protected function getField($contents, $default="Unknown")
    {
        if ((string) $contents != "") {
            return (string) $contents;
        } else {
            return $default;
        }
    }

    /**
     * Make Request
     *
     * Makes a request to the Polaris Restful API
     *
     * @param string $api_query   Query string for request
     * @param string $http_method HTTP method (default = GET)
     *
     * @throws ILSException
     * @return obj
     */
    protected function makeRequest($api_query, $http_method="GET")
    {
        $url = $this->host . $this->api_path . $api_query;

        if ($this->debug_enabled) {
            $this->debug("URL: '$url'");
        }
        $http_headers = array(
            "Accept: text/xml",
            "Accept-encoding: plain",
        );

        try {
            $client = $this->httpService->createClient($url);

            $client->setMethod($http_method);
            $client->setHeaders($http_headers);
            $result = $client->send();
        } catch (\Exception $e) {
            $this->debug("Result is invalid.");
            throw new ILSException($e->getMessage());
        }

        if (!$result->isSuccess()) {
            $this->debug("Result is invalid.");
            throw new ILSException('HTTP error');
        }
        $answer = $result->getBody();
        //$answer = str_replace('xmlns=', 'ns=', $answer);
        $result = simplexml_load_string($answer);
        if (!$result) {
            if ($this->debug_enabled) {
                $this->debug("XML is not valid, URL: $url");
            }
            throw new ILSException(
                "XML is not valid, URL: $url method: $method answer: $answer."
            );
        }
        return $result;
    }

    /**
     * toKohaDate
     *
     * Turns a display date into a date format expected by Koha.
     *
     * @param string $display_date Date to be converted
     *
     * @throws ILSException
     * @return string $koha_date
     */
    protected function toKohaDate($display_date)
    {
        $koha_date = "";

        // Convert last interest date from format to Koha format
        $koha_date = $this->dateConverter->convertFromDisplayDate(
            "Y-m-d", $display_date
        );

        $checkTime =  $this->dateConverter->convertFromDisplayDate(
            "U", $display_date
        );
        if (!is_numeric($checkTime)) {
            throw new DateException('Result should be numeric');
        }

        if (time() > $checkTime) {
            // Hold Date is in the past
            throw new DateException('hold_date_past');
        }
        return $koha_date;
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
        $functionConfig = "";
        if (isset($this->config[$function])) {
            $functionConfig = $this->config[$function];
        } else {
            $functionConfig = false;
        }
        return $functionConfig;
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
        if ($this->locations) {
            // hardcoded pickup locations in the .ini file? or...
            foreach ($this->locations as $code => $library) {
                $locations[] = array(
                    'locationID'      => $code,
                    'locationDisplay' => $library,
                );
            }
        } else {
            if (!$this->default_location) {
                throw new ILSException(
                    "Neither locations nor default_location defined in KohaRest.ini."
                );
            }
            // we get them from the API
            // FIXME: Not yet possible: API incomplete.
            // TODO: When API: pull locations dynamically from API.
            /* $response = $this->makeRequest("organizations/branch"); */
            /* $locations_response_array = $response->OrganizationsGetRows; */
            /* foreach ($locations_response_array as $location_response) { */
            /*     $locations[] = array( */
            /*         'locationID'      => $location_response->OrganizationID, */
            /*         'locationDisplay' => $location_response->Name, */
            /*     ); */
            /* } */
        }
        return $locations;
    }

    /**
     * Get Default Pick Up Location
     *
     * Returns the default pick up location set in KohaRest.ini
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
        return $this->default_location;
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
        $rsvLst             = array();
        $patron             = $holdDetails['patron'];
        $patron_id          = $patron['id'];
        $request_location   = isset($patron['ip']) ? $patron['ip'] : "127.0.0.1";
        $bib_id             = $holdDetails['id'];
        $item_id            = $holdDetails['item_id'];
        $pickup_location    = !empty($holdDetails['pickUpLocation'])
            ? $holdDetails['pickUpLocation'] : $this->default_location;
        $level              = isset($holdDetails['level'])
            && !empty($holdDetails['level']) ? $holdDetails['level'] : "bib";

        try {
            $needed_before_date = $this->toKohaDate($holdDetails['requiredBy']);
        } catch (DateException $e) {
            return array(
                "success" => false,
                "sysMessage" => "It seems you entered an invalid expiration date."
            );
        }

        if ($this->debug_enabled) {
            $this->debug("patron: " . $patron);
            $this->debug("patron_id: " . $patron_id);
            $this->debug("request_location: " . $request_location);
            $this->debug("item_id: " . $item_id);
            $this->debug("bib_id: " . $bib_id);
            $this->debug("pickup loc: " . $pickup_location);
            $this->debug("Needed before date: " . $needed_before_date);
            $this->debug("Level: " . $level);
        }

        //Make Sure Pick Up Library is Valid
        /* if (!$this->pickUpLocationIsValid( */
        /* $pickUpLocation, $patron, $holdDetails)) { */
        /*     return $this->holdError("hold_invalid_pickup"); */
        /* } */

        if ( $level == "bib" ) {
            $rqString = "HoldTitle&patron_id=$patron_id&bib_id=$bib_id"
                . "&request_location=$request_location"
                . "&pickup_location=$pickup_location"
                . "&pickup_expiry_date=$needed_before_date";
        } else {
            $rqString = "HoldItem&patron_id=$patron_id&bib_id=$bib_id"
                . "&item_id=$item_id"
                . "&pickup_location=$pickup_location"
                . "&needed_before_date=$needed_before_date"
                . "&pickup_expiry_date=$needed_before_date";
                //. "&request_location=$request_location"
        }

        $rsp = $this->makeRequest($rqString);

        if ($this->debug_enabled) {
            $this->debug("Title: " . $rsp->{'title'});
            $this->debug("Pickup Location: " . $rsp->{'pickup_location'});
            $this->debug("Code: " . $rsp->{'code'});
        }

        if ($rsp->{'code'} != "") {
            return array(
                "success"    => false,
                "sysMessage" => $this->getField($rsp->{'code'}),
            );
        }
        return array(
            "success"    => true,
        );
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
     * @throws \VuFind\Exception\Date
     * @throws ILSException
     * @return array         On success, an associative array with the following
     * keys: id, availability (boolean), status, location, reserve, callnumber,
     * duedate, number, barcode.
     */
    public function getHolding($id, array $patron = null)
    {
        $holding = array();
        $available = true;
        $duedate = $status = '';
        $loc = $shelf = '';
        $reserves = "N";

        $rsp = $this->makeRequest("GetRecords&id=$id");

        if ($this->debug_enabled) {
            $this->debug("ISBN: " . $rsp->{'record'}->{'isbn'});
        }

        foreach ($rsp->{'record'}->{'items'}->{'item'} as $item) {
            if ($this->debug_enabled) {
                $this->debug("Biblio: " . $item->{'biblioitemnumber'});
                $this->debug("ItemNo: " . $item->{'itemnumber'});
            }
            switch ($item->{'notforloan'}) {
            case 0:
                if ($item->{'date_due'} != "") {
                    $available = false;
                    $status    = 'Checked out';
                    $duedate   = $this->getField($item->{'date_due'});
                } else {
                    $available = true;
                    $status    = 'Available';
                    $duedate   = '';
                }
                break;
            case 1: // The item is not available for loan
            default: $available = false;
                $status = 'Not for loan';
                $duedate = '';
                break;
            }
            
            foreach ($rsp->{'record'}->{'reserves'}->{'reserve'} as $reserve) {
                if ($reserve->{'suspend'} == '0') {
                    $reserves = "Y";
                    break;
                }
            }
            $holding[] = array(
                'id'           => (string) $id,
                'availability' => (string) $available,
                'item_id'      => $this->getField($item->{'itemnumber'}),
                'status'       => (string) $status,
                'location'     => $this->getField($item->{'holdingbranchname'}),
                'reserve'      => (string) $reserves,
                'callnumber'   => $this->getField($item->{'itemcallnumber'}),
                'duedate'      => (string) $duedate,
                'barcode'      => $this->getField($item->{'barcode'}),
                'number'       => $this->getField($item->{'copynumber'}),
            );
        }
        return $holding;
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
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getHoldLink($id, $details)
    {
        // Web link of the ILS for placing hold on the item
        return $this->ilsBaseUrl . "/cgi-bin/koha/opac-reserve.pl?biblionumber=$id";
    }

    /**
     * Get Patron Fines
     *
     * This is responsible for retrieving all fines by a specific patron.
     *
     * @param array $patron The patron array from patronLogin
     *
     * @throws \VuFind\Exception\Date
     * @throws ILSException
     * @return mixed        Array of the patron's fines on success.
     */
    public function getMyFines($patron)
    {
        $id = $patron['id'];        
        $fineLst = array();
        
        $rsp = $this->makeRequest(
            "GetPatronInfo&patron_id=$id" . "&show_contact=0&show_fines=1"
        );

        if ($this->debug_enabled) {
            $this->debug("ID: " . $rsp->{'borrowernumber'});
            $this->debug("Chrgs: " . $rsp->{'charges'});
        }

        foreach ($rsp->{'fines'}->{'fine'} as $fine) {
            $fineLst[] = array(
                'amount'     => 100 * $this->getField($fine->{'amount'}),
                // FIXME: require accountlines.itemnumber -> issues.issuedate data
                'checkout'   => "N/A",
                'fine'       => $this->getField($fine->{'description'}),
                'balance'    => 100 * $this->getField($fine->{'amountoutstanding'}),
                'createdate' => $this->getField($fine->{'date'}),
                // FIXME: require accountlines.itemnumber -> issues.date_due data.
                'duedate'    => "N/A",
                // FIXME: require accountlines.itemnumber -> items.biblionumber data 
                'id'         => "N/A",
            );
        }
        return $fineLst;
    }

    /**
     * Get Patron Holds
     *
     * This is responsible for retrieving all holds by a specific patron.
     *
     * @param array $patron The patron array from patronLogin
     *
     * @throws \VuFind\Exception\Date
     * @throws ILSException
     * @return array        Array of the patron's holds on success.
     */
    public function getMyHolds($patron)
    {
        $id = $patron['id'];        
        $holdLst = array();
        
        $rsp = $this->makeRequest(
            "GetPatronInfo&patron_id=$id" . "&show_contact=0&show_holds=1"
        );

        if ($this->debug_enabled) {
            $this->debug("ID: " . $rsp->{'borrowernumber'});
            //print_r($rsp); // Proof that no itemnumber is returned.
        }

        foreach ($rsp->{'holds'}->{'hold'} as $hold) {
            $holdLst[] = array(
                'id'       => $this->getField($hold->{'biblionumber'}),
                'location' => $this->getField($hold->{'branchname'}),
                // FIXME: require exposure of reserves.expirationdate
                'expire'   => "N/A",
                'create'   => $this->getField($hold->{'reservedate'}),
            );
        }
        return $holdLst;
    }

    /**
     * Get Cancel Hold Details
     *
     * In order to cancel a hold, Koha requires the patron details and
     * an item ID. This function returns the item id as a string. This
     * value is then used by the CancelHolds function.
     *
     * @param array $holdDetails An array of item data
     *
     * @return string Data for use in a form field
     */
    public function getCancelHoldDetails($holdDetails)
    {
        // Get the full details of this item
        $rsp = $this->getHolding($holdDetails['id']);
        // Fetch the item_id.
        return $rsp[0]['item_id'];
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
        // FIXME: Currently fails due to 1) limitation of API; 2) error in API.
        $retVal         = array('count' => 0, 'items' => array());
        $details        = $cancelDetails['details'];
        $patron_id      = $cancelDetails['patron']['id'];
        $request_prefix = "CancelHold&patron_id=" . $patron_id . "&item_id=";

        foreach ($details as $cancelItem) {
            $rsp = $this->makeRequest($request_prefix . $cancelItem);
            if ($rsp->{'message'} != "Canceled") {
                $retVal['items'][$cancelItem] = array(
                    'success'    => false,
                    'status'     => 'hold_cancel_fail',
                    'sysMessage' => $this->getField($rsp->{'code'}),
                );
            } else {
                $retVal['count']++;
                $retVal['items'][$current['item_id']] = array(
                    'success' => true,
                    'status' => 'hold_cancel_success',
                );
            }
        }
        return $retVal;
    }

    /**
     * Get Patron Profile
     *
     * This is responsible for retrieving the profile for a specific patron.
     *
     * @param array $patron The patron array
     *
     * @throws ILSException
     * @return array        Array of the patron's profile data on success.
     */
    public function getMyProfile($patron)
    {
        $id = $patron['id'];
        $profile = array();
        
        $rsp = $this->makeRequest(
            "GetPatronInfo&patron_id=$id" . "&show_contact=1"
        );

        if ($this->debug_enabled) {
            $this->debug("Code: " . $rsp->{'code'});
            $this->debug("Cardnumber: " . $rsp->{'cardnumber'});
        }

        if ($rsp->{'code'} != 'PatronNotFound') {
            $profile = array(
                'firstname' => $this->getField($rsp->{'firstname'}),
                'lastname'  => $this->getField($rsp->{'surname'}),
                'address1'  => $this->getField($rsp->{'address'}),
                'address2'  => $this->getField($rsp->{'address2'}),
                'zip'       => $this->getField($rsp->{'zipcode'}),
                'phone'     => $this->getField($rsp->{'phone'}),
                'group'     => $this->getField($rsp->{'categorycode'}),
            );
            return $profile;
        } else {
            return null;
        }
    }

    /**
     * Get Patron Transactions
     *
     * This is responsible for retrieving all transactions (i.e. checked out items)
     * by a specific patron.
     *
     * @param array $patron The patron array from patronLogin
     *
     * @throws \VuFind\Exception\Date
     * @throws ILSException
     * @return array        Array of the patron's transactions on success.
     */
    public function getMyTransactions($patron)
    {
        $id = $patron['id'];
        $transactionLst = array();
        
        $rsp = $this->makeRequest(
            "GetPatronInfo&patron_id=$id" . "&show_contact=0&show_loans=1"
        );

        if ($this->debug_enabled) {
            $this->debug("ID: " . $rsp->{'borrowernumber'});
        }

        foreach ($rsp->{'loans'}->{'loan'} as $loan) {
            $transactionLst[] = array(
                'duedate'   => $this->getField($loan->{'date_due'}),
                'id'        => $this->getField($loan->{'biblionumber'}),
                'item_id'   => $this->getField($loan->{'itemnumber'}),
                'barcode'   => $this->getField($loan->{'barcode'}),
                'renew'     => $this->getField($loan->{'renewals'}, '0'),
                'renewable' => true,
                // FIXME: could do with a proper 'renewable' key from
                // Koha API.
            );
        }
        return $transactionLst;
    }

    /**
     * Get Renew Details
     *
     * In order to renew an item, Koha requires the patron details and
     * an item id. This function returns the item id as a string which
     * is then used as submitted form data in checkedOut.php. This
     * value is then extracted by the RenewMyItems function.
     *
     * @param array $checkOutDetails An array of item data
     *
     * @return string Data for use in a form field
     */
    public function getRenewDetails($checkOutDetails)
    {
        return $checkOutDetails['item_id'];
    }

    /**
     * Renew My Items
     *
     * Function for attempting to renew a patron's items.  The data in
     * $renewDetails['details'] is determined by getRenewDetails().
     *
     * @param array $renewDetails An array of data required for
     * renewing items including the Patron ID and an array of renewal
     * IDS
     *
     * @return array An array of renewal information keyed by item ID
     */
    public function renewMyItems($renewDetails)
    {
        $retVal         = array('blocks' => false, 'details' => array());
        $details        = $renewDetails['details'];
        $patron_id      = $renewDetails['patron']['id'];
        $request_prefix = "RenewLoan&patron_id=" . $patron_id . "&item_id=";

        foreach ($details as $renewItem) {
            $rsp = $this->makeRequest($request_prefix . $renewItem);
            if ($rsp->{'success'} != '0') {
                list($date, $time)
                    = explode(" ", $this->getField($rsp->{'date_due'}));
                $retVal['details'][$renewItem] = array(
                    "success"  => true,
                    "new_date" => $date,
                    "new_time" => $time,
                    "item_id"  => $renewItem,
                );
            } else {
                $retVal['details'][$renewItem] = array(
                    "success"    => false,
                    "new_date"   => false,
                    "item_id"    => $renewItem,
                    //"sysMessage" => $this->getField($rsp->{'error'}),
                );
            }
        }
        return $retVal;
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
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getPurchaseHistory($id)
    {
        // TODO
        return array();
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
        return $this->getHolding($id);
    }

    /**
     * Get Statuses
     *
     * This is responsible for retrieving the status information for a
     * collection of records.
     *
     * @param array $idLst The array of record ids to retrieve the status for
     *
     * @throws ILSException
     * @return array       An array of getStatus() return values on success.
     */
    public function getStatuses($idLst)
    {
        $statusLst = array();
        foreach ($idLst as $id) {
            $statusLst[] = $this->getStatus($id);
        }
        return $statusLst;
    }

    /**
     * Get suppressed records.
     *
     * NOTE: This function needs to be modified only if Koha has
     *       suppressed records in OPAC view
     *
     * @throws ILSException
     * @return array ID numbers of suppressed records in the system.
     */
    public function getSuppressedRecords()
    {
        // FIXME: TODO: use hardcoded list in .ini if available.
        return array();
    }

    /**
     * Patron Login
     *
     * This is responsible for authenticating a patron against the catalog.
     *
     * @param string $username The patron username
     * @param string $password The patron's password
     *
     * @throws ILSException
     * @return mixed          Associative array of patron info on successful login,
     * null on unsuccessful login.
     */
    public function patronLogin($username, $password)
    {
        $patron = array();

        $idObj = $this->makeRequest(
            "AuthenticatePatron" . "&username=" . $username
            . "&password=" . $password
        );
        if ($this->debug_enabled) {
            $this->debug("Code: " . $idObj->{'code'});
            $this->debug("ID: " . $idObj->{'id'});
        }

        if ($idObj->{'code'} != "PatronNotFound") {
            $rsp = $this->makeRequest(
                "GetPatronInfo&patron_id=" . $idObj->{'id'} . "&show_contact=1"
            );

            if ($rsp->{'code'} != 'PatronNotFound') {
                $profile = array(
                    'id'           => $this->getField($idObj->{'id'}),
                    'firstname'    => $this->getField($rsp->{'firstname'}),
                    'lastname'     => $this->getField($rsp->{'lastname'}),
                    'cat_username' => $username,
                    'cat_password' => $password,
                    'email'        => $this->getField($rsp->{'email'}),
                    'major'        => null,
                    'college'      => null,
                );
                return $profile;
            } else {
                return null;
            }
        } else {
            return null;
        }
    }
}
