<?php
/**
 * KohaRESTful ILS Driver
 *
 * PHP version 5
 *
 * Copyright (C) Josef Moravec, 2016.
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
 * @package  ILS_Drivers
 * @author   Josef Moravec <josef.moravec@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
namespace VuFind\ILS\Driver;

use PDO, PDOException;
use VuFind\Exception\ILS as ILSException,
    VuFindHttp\HttpServiceAwareInterface as HttpServiceAwareInterface,
    Zend\Log\LoggerAwareInterface as LoggerAwareInterface,
    VuFind\Exception\Date as DateException;

/* TODO: will extend \VuFind\ILS\Driver\AbstractBase, this is just for testing and developing purposes */
class KohaRESTful extends \VuFind\ILS\Driver\KohaILSDI implements
    HttpServiceAwareInterface, LoggerAwareInterface
{
    use \VuFindHttp\HttpServiceAwareTrait;
    use \VuFind\Log\LoggerAwareTrait;

    /**
     * REST API base URL
     *
     * @var string
     */
    protected $apiUrl;

    /**
     * REST API user userid/login
     *
     * @var string
     */
    protected $apiUserid;

    /**
     * REST API user password
     *
     * @var string
     */
    protected $apiPassword;

    /**
     * Location codes
     *
     * @var array
     */
    protected $locations;

    /**
     * Codes of locations available for pickup
     *
     * @var array
     */
    protected $pickupEnableBranchcodes;

    /**
     * Codes of locations always should be available
     *   - For example reference material or material
     *     not for loan
     *
     * @var array
     */
    protected $availableLocationsDefault;

    /**
     * Default location code
     *
     * @var string
     */
    protected $defaultLocation;

    //TODO: make date format configurable
    protected $dateFormat = "d. m. Y";

    /**
     * Date converter object
     *
     * @var \VuFind\Date\Converter
     */
    //protected $dateConverter;

    /**
     * Id of CGI session for Koha RESTful API
     */
    protected $CGISESSID;

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
        // TODO: get rid of this and use standard vufind debugging system
        $this->debug_enabled = isset($this->config['Catalog']['debug'])
            ? $this->config['Catalog']['debug'] : false;

        // Storing the base RESTful API connection information
        $this->apiUrl = isset($this->config['Catalog']['apiurl'])
            ? $this->config['Catalog']['apiurl'] : "";
        $this->apiUserid = isset($this->config['Catalog']['apiuserid'])
            ? $this->config['Catalog']['apiuserid'] : null;
        $this->apiPassword = isset($this->config['Catalog']['apiuserpassword'])
            ? $this->config['Catalog']['apiuserpassword'] : null;
        // Authenticate to RESTful API
        $patron = $this->makeRESTfulRequest(
                '/auth/session',
                'POST',
                ['userid' => $this->apiUserid, 'password' => $this->apiPassword ]
        );
        if ($patron) {
            $this->CGISESSID = $patron->sessionid;
        } else {
            throw new ILSException(
                    'Can not authenticate to Koha through RESTful API'
            );
        }

        // MySQL database host
        $this->host = isset($this->config['Catalog']['host']) ?
            $this->config['Catalog']['host'] : "localhost";

        // Storing the base URL of ILS-DI
        $this->ilsBaseUrl = isset($this->config['Catalog']['url'])
            ? $this->config['Catalog']['url'] : "";

        // Default location defined in 'KohaRESTful.ini'
        $this->defaultLocation
            = isset($this->config['Holds']['defaultPickUpLocation'])
            ? $this->config['Holds']['defaultPickUpLocation'] : null;
        $this->pickupEnableBranchcodes
            = isset($this->config['Holds']['pickupLocations'])
            ? $this->config['Holds']['pickupLocations'] : [];

        // Locations that should default to available, defined in 'KohaRESTful.ini'
        $this->availableLocationsDefault
            = isset($this->config['Other']['availableLocations'])
            ? $this->config['Other']['availableLocations'] : [];

        // Create a dateConverter
        //$this->dateConverter = new \VuFind\Date\Converter;
    }

    /**
     * Make Request
     *
     * Makes a request to the Koha ILSDI API
     *
     * @param string $apiQuery   Query string for request (starts with "/")
     * @param string $httpMethod HTTP method (default = GET)
     * @param array  $data       Provide needed data (default = null)
     *
     * @throws ILSException
     * @return array
     */
    protected function makeRESTfulRequest(
            $apiQuery,
            $httpMethod = "GET",
            $data = null
    )
    {
        // TODO - get rid of this kind of authentication and use just session
        $kohaDate = date("r"); // RFC 1123/2822
        $signature = implode(" ", [
                (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') ? "HTTPS" : "HTTP",
                $this->apiUserid,
                $kohaDate
                ]
        );

        $hashedSignature = hash_hmac("sha256", $signature, $this->apiPassword);

        $httpHeaders = [
            "Accept" => "application/json",
            "X-Koha-Date" => $kohaDate,
            "Authorization" => "Koha " . $this->apiUserid . ":" . $hashedSignature ,
        ];

        $client = $this->httpService->createClient(
            $this->apiUrl . $apiQuery,
            $httpMethod
        );
        $client->setHeaders($httpHeaders);
        if (isset($this->CGISESSID)) {
            $client->addCookie('CGISESSID', $this->CGISESSID);
        }
        if ($data !== null) {
            $client->setRawBody(http_build_query($data));
        }

        try {
            $response = $client->send();
        } catch (\Exception $e) {
            throw new ILSException($e->getMessage());
        }

        if (!$response->isSuccess()) {
            var_dump($response->getBody());
            echo $this->apiUrl . $apiQuery;
            throw new ILSException(
                "Error in communication with Koha API:" . $response->getBody() .
                " HTTP status code: " . $response->getStatusCode()
            );
        }

        $result = json_decode($response->getBody());
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ILSException("Error parsing hajson response of Koha API");
        }
        return $result;
    }

    /**
     * Format dates
     */
    protected function formatDate($date)
    {
        if (!$date) {
            return null;
        }
        $dateObject = new \DateTime($date);
        return $dateObject->format($this->dateFormat);
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
     * https://vufind.org/wiki/development:plugins:ils_drivers#getpickuplocations
     */
    /* Will be available after Bug 16497 is pushed:
     * https://bugs.koha-community.org/bugzilla3/show_bug.cgi?id=16497
     */
    /*
    public function getPickupLocations($patron = false, $holdDetails = null)
    {
        // TODO: check if pickupEnableBranchcodes is set (maybe in init method),
        // if not, use default, if it's not set, get all locations from API
        if ( !isset($this->locations )) {
            $libraries = $this->makeRESTfulRequest("/libraries");
            $locations = [];
            foreach($libraries as $library)
            {
                if (in_array($library->branchcode, $this->pickupEnableBranchcodes)) {
                    $locations[] = [
                        "locationID" => $library->branchcode,
                        "locationDisplay" => $library->branchname,
                    ];
                }
            }
            $this->locatins = $locations;
        }
        return $this->locations;
    }*/

    /**
     * Get Default Pick Up Location
     *
     * Returns the default pick up location set in KohaILSDI.ini
     *
     * @param array $patron      Patron information returned by the patronLogin
     * method.
     * @param array $holdDetails Optional array, only passed in when getting a list
     * in the context of placing a hold; contains most of the same values passed to
     * placeHold, minus the patron data.    May be used to limit the pickup options
     * or may be ignored.
     *
     * @return string The default pickup location for the patron.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getDefaultPickUpLocation($patron = false, $holdDetails = null)
    {
        return $this->defaultLocation;
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
    /* Will be available after Bug 17004 is pushed:
     * https://bugs.koha-community.org/bugzilla3/show_bug.cgi?id=17004
     */
    /*
    public function patronLogin($username, $password)
    {
        $patron = $this->makeRESTfulRequest('/auth/session', 'POST',
                ['userid' => $username, 'password' => $password ]
        );
        if ($patron) {
            return [
                'id' => $patron->borrowernumber,
                'firstname' => $patron->firstname,
                'lastname' => $patron->surname,
                'cat_username' => $username,
                'cat_password' => $password,
                'email' => $patron->email,
                'major' => null,
                'college' => null,
            ];
        }
        return null;
    }
    */

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
        $patron = $this->makeRESTfulRequest('/patrons/' . $patron['id']);
        if ($patron) {
            return [
                'firstname' => $patron->firstname,
                'lastname'  => $patron->surname,
                'address1'  => $patron->address . ' ' . $patron->streetnumber,
                'address2'  => $patron->address2,
                'city'      => $patron->city,
                'country'   => $patron->country,
                'zip'       => $patron->zipcode,
                'phone'     => $patron->phone,
                'group'     => $patron->categorycode,
            ];
        }
        return null;
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
    /* Will be available after Bugs 13895, 17003 and 16825 are pushed:
     * https://bugs.koha-community.org/bugzilla3/show_bug.cgi?id=13895
     * https://bugs.koha-community.org/bugzilla3/show_bug.cgi?id=17003
     * https://bugs.koha-community.org/bugzilla3/show_bug.cgi?id=16825
     */
    /*
    public function getMyTransactions($patron)
    {
        $checkouts = $this->makeRESTfulRequest('/checkouts', 'GET',
                [ 'borrowernumber' => $patron['id'] ]
        );
        $checkoutsList = [];
        if($checkouts) {
            foreach ($checkouts as $checkout) {
                //TODO: it's not nice to make request for each checkout in the loop
                $item = $this->makeRESTfulRequest('/items/' . $checkout->itemnumber);
                //$renewable = $this->makeRESTfulRequest('/checkouts/' . $checkout->issue_id . '/renewability');
                $checkoutsList[] = [
                    'duedate'           => $this->formatDate($checkout->date_due),
                    'id'                => $item ? $item->biblionumber : 0,
                    'item_id'           => $checkout->itemnumber,
                    'barcode'           => $item ? $item->barcode : 0,
                    'renew'             => $checkout->renewals,
                    'borrowingLocation' => $checkout->branchcode,//TODO: branchname
                    // 'renewable' => is_object($renewable) ? 1 : 0,
                    // 'request => , //TODO: is item reserved?
                ];

            }
        }
        return $checkoutsList;
    }*/

    /**
     * Get Patron Holds
     *
     */
    public function getMyHolds($patron)
    {
        $holds = $this->makeRESTfulRequest('/holds', 'GET',
                [ 'borrowernumber' => $patron['id'] ]
        );
        $holdsList = [];
        if ($holds) {
            foreach ($holds as $hold) {
                $holdsList[] = [
                    'id'        => $hold->biblionumber,
                    'location'  => $hold->branchcode, //TODO: add branch name
                    'expire'    => $this->formatDate($hold->expirationdate),
                    'create'    => $this->formatDate($hold->reservedate),
                    'position'  => $hold->priority,
                    'available' => $hold->found,
                ];
            }
        }
        return $holdsList;
    }

    /**
     * Get Patron Fines
     *
     */
    /*
    public function getMyFines($patron)
    {
        $fines = $this->makeRESTfulRequest('/');
    }
    */

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

    /*
    public function placeHold($holdDetails)
    {

    }*/

    /**
     * Insert Suggestion
     */

    /**
     * Get Holdings
     */

    /* Will be available after Bugs 17371 and 16825 are pushed:
     * https://bugs.koha-community.org/bugzilla3/show_bug.cgi?id=17371,
     * https://bugs.koha-community.org/bugzilla3/show_bug.cgi?id=16825
     */

    /*
    public function getHolding($id, array $patron = null)
    {
        $biblio = $this->makeRESTfulRequest('/biblios/' . $id);
        $holdingsList = [];
        if ($biblio) {
            foreach ($biblio->items as $i) {
                $item = $this->makeRESTfulRequest('/items/' . $i->itemnumber);
                $holdingsList[] = [
                    'id'          => $id,
                    'availabiity' => $item->onloan ? false : true,
                    'status'      => $item->onloan ? 'Checked out' : 'Available', //TODO: more statuses
                    'location'    => $item->holdingbranch,
                    'callnumber'  => $item->callnumber,
                    'number'      => $item->stocknumber,
                    'barcode'     => $item->barcode,
                    'supplements' => $item->materials,
                    'item_notes'  => $item->itemnotes,
                    'item_id'     => $i->itemnumber,
                    // reqests_placed'         =>,
                    'duedate'         => null, //TODO
                    'returnDate'         => false, //TODO
                    // 'reserve'         =>, //Y or N
                ];
            }
        }
        return $holdingsList;
    }
    */
}
