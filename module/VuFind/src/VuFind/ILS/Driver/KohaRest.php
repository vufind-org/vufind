<?php
/**
 * KohaRest ILS Driver
 *
 * PHP version 5
 *
 * Copyright (C) Ayesha Abed Library, BRAC University 2010.
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
                'item_num'     => $this->getField($item->{'itemnumber'}),
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
                'checkout'   => "",
                'fine'       => $this->getField($fine->{'description'}),
                'balance'    => 100 * $this->getField($fine->{'amountoutstanding'}),
                'createdate' => $this->getField($fine->{'date'}),
                // FIXME: require accountlines.itemnumber -> issues.date_due data.
                'duedate'    => "",
                // FIXME: require accountlines.itemnumber -> items.biblionumber data 
                'id'         => "",
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
        }

        foreach ($rsp->{'holds'}->{'hold'} as $hold) {
            $holdLst[] = array(
                'id'       => $this->getField($hold->{'biblionumber'}),
                'location' => $this->getField($hold->{'branchname'}),
                // FIXME: require exposure of reserves.expirationdate
                'expire'   => "FIXME",
                'create'   => $this->getField($hold->{'reservedate'}),
            );
        }
        return $holdLst;
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
                'lastname'  => $this->getField($rsp->{'lastname'}),
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
                'duedate' => $this->getField($loan->{'date_due'}),
                'id'      => $this->getField($loan->{'biblionumber'}),
                'barcode' => $this->getField($loan->{'barcode'}),
                'renew'   => $this->getField($loan->{'renewals'}, '0'),
            );
        }
        return $transactionLst;
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
        // FIXME: TODO
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
        // FIXME: TODO
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
