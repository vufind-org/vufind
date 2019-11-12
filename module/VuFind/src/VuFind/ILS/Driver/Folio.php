<?php
/**
 * FOLIO REST API driver
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2018.
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
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
namespace VuFind\ILS\Driver;

use VuFind\Exception\ILS as ILSException;
use VuFind\I18n\Translator\TranslatorAwareInterface;
use VuFindHttp\HttpServiceAwareInterface as HttpServiceAwareInterface;

/**
 * FOLIO REST API driver
 *
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
class Folio extends AbstractAPI implements
    HttpServiceAwareInterface, TranslatorAwareInterface
{
    use \VuFindHttp\HttpServiceAwareTrait;
    use \VuFind\I18n\Translator\TranslatorAwareTrait;
    use \VuFind\Log\LoggerAwareTrait {
        logWarning as warning;
        logError as error;
    }

    /**
     * Authentication tenant (X-Okapi-Tenant)
     *
     * @var string
     */
    protected $tenant = null;

    /**
     * Authentication token (X-Okapi-Token)
     *
     * @var string
     */
    protected $token = null;

    /**
     * Factory function for constructing the SessionContainer.
     *
     * @var Callable
     */
    protected $sessionFactory;

    /**
     * Session cache
     *
     * @var \Zend\Session\Container
     */
    protected $sessionCache;

    /**
     * Constructor
     *
     * @param \VuFind\Date\Converter $dateConverter  Date converter object
     * @param Callable               $sessionFactory Factory function returning
     * SessionContainer object
     */
    public function __construct(\VuFind\Date\Converter $dateConverter,
        $sessionFactory
    ) {
        $this->dateConverter = $dateConverter;
        $this->sessionFactory = $sessionFactory;
    }

    /**
     * Set the configuration for the driver.
     *
     * @param array $config Configuration array (usually loaded from a VuFind .ini
     * file whose name corresponds with the driver class name).
     *
     * @throws ILSException if base url excluded
     * @return void
     */
    public function setConfig($config)
    {
        parent::setConfig($config);
        $this->tenant = $this->config['API']['tenant'];
    }

    /**
     * Function that obscures and logs debug data
     *
     * @param string             $method      Request method GET/POST/PUT/DELETE/etc
     * @param string             $path        Request URL
     * @param array              $params      Request parameters
     * @param \Zend\Http\Headers $req_headers Headers object
     *
     * @return void
     */
    protected function debugRequest($method, $path, $params, $req_headers)
    {
        // Only log non-GET requests
        if ($method == 'GET') {
            return;
        }
        // remove passwords
        $logParams = $params;
        if (isset($logParams['password'])) {
            unset($logParams['password']);
        }
        // truncate headers for token obscuring
        $logHeaders = $req_headers->toArray();
        if (isset($logHeaders['X-Okapi-Token'])) {
            $logHeaders['X-Okapi-Token'] = substr($val, 0, 30) . '...';
        }

        $this->debug(
            $method . ' request.' .
            ' URL: ' . $path . '.' .
            ' Params: ' . print_r($logParams, true) . '.' .
            ' Headers: ' . print_r($logHeaders, true)
        );
    }

    /**
     * (From AbstractAPI) Allow default corrections to all requests
     *
     * Add X-Okapi headers and Content-Type to every request
     *
     * @param \Zend\Http\Headers $headers the request headers
     * @param object             $params  the parameters object
     *
     * @return array
     */
    public function preRequest(\Zend\Http\Headers $headers, $params)
    {
        $headers->addHeaderLine('Accept', 'application/json');
        if (!$headers->has('Content-Type')) {
            $headers->addHeaderLine('Content-Type', 'application/json');
        }
        $headers->addHeaderLine('X-Okapi-Tenant', $this->tenant);
        if ($this->token != null) {
            $headers->addHeaderLine('X-Okapi-Token', $this->token);
        }
        return [$headers, $params];
    }

    /**
     * Login and receive a new token
     *
     * @return void
     */
    protected function renewTenantToken()
    {
        $this->token = null;
        $auth = [
            'username' => $this->config['API']['username'],
            'password' => $this->config['API']['password'],
        ];
        $response = $this->makeRequest('POST', '/authn/login', json_encode($auth));
        if ($response->getStatusCode() >= 400) {
            throw new ILSException($response->getBody());
        }
        $this->token = $response->getHeaders()->get('X-Okapi-Token')
            ->getFieldValue();
        $this->sessionCache->folio_token = $this->token;
        $this->debug(
            'Token renewed. Tenant: ' . $auth['username'] .
            ' Token: ' . substr($this->token, 0, 30) . '...'
        );
    }

    /**
     * Check if our token is still valid
     *
     * Method taken from Stripes JS (loginServices.js:validateUser)
     *
     * @return void
     */
    protected function checkTenantToken()
    {
        $response = $this->makeRequest('GET', '/users');
        if ($response->getStatusCode() >= 400) {
            $this->token = null;
            $this->renewTenantToken();
        }
    }

    /**
     * Initialize the driver.
     *
     * Check or renew our auth token
     *
     * @return void
     */
    public function init()
    {
        $factory = $this->sessionFactory;
        $this->sessionCache = $factory($this->tenant);
        if ($this->sessionCache->folio_token ?? false) {
            $this->token = $this->sessionCache->folio_token;
            $this->debug(
                'Token taken from cache: ' . substr($this->token, 0, 30) . '...'
            );
        }
        if ($this->token == null) {
            $this->renewTenantToken();
        } else {
            $this->checkTenantToken();
        }
    }

    /**
     * Get local bib id from inventory by following parents up the tree
     *
     * @param string $instanceId Instance-level id (lowest level)
     * @param string $holdingId  Holding-level id (looked up from instance if null)
     * @param string $itemId     Item-level id (looked up from holding if null)
     *
     * @return string Local bib id retrieved from Folio identifiers
     */
    protected function getBibId($instanceId, $holdingId = null, $itemId = null)
    {
        if ($instanceId == null) {
            if ($holdingId == null) {
                $response = $this->makeRequest(
                    'GET',
                    '/item-storage/items/' . $itemId
                );
                $item = json_decode($response->getBody());
                $holdingId = $item->holdingsRecordId;
            }
            $response = $this->makeRequest(
                'GET', '/holdings-storage/holdings/' . $holdingId
            );
            $holding = json_decode($response->getBody());
            $instanceId = $holding->instanceId;
        }
        $response = $this->makeRequest(
            'GET', '/inventory/instances/' . $instanceId
        );
        $instance = json_decode($response->getBody());
        return $instance->identifiers[0]->value;
    }

    /**
     * Get raw object of item from inventory/items/
     *
     * @param string $bibId Bib-level id
     *
     * @throw
     * @return array
     */
    protected function getInstance($bibId)
    {
        $escaped = str_replace('"', '\"', str_replace('&', '%26', $bibId));
        $query = [
            'query' => '(id="' . $escaped . '" or identifiers="' . $escaped . '")'
        ];
        $response = $this->makeRequest('GET', '/instance-storage/instances', $query);
        $instances = json_decode($response->getBody());
        if (count($instances->instances) == 0) {
            throw new ILSException("Item Not Found");
        }
        return $instances->instances[0];
    }

    /**
     * Get raw object of item from inventory/items/
     *
     * @param string $itemId Item-level id
     *
     * @return array
     */
    public function getStatus($itemId)
    {
        return $this->getHolding($itemId);
    }

    /**
     * This method calls getStatus for an array of records or implement a bulk method
     *
     * @param array $idList Item-level ids
     *
     * @return array values from getStatus
     */
    public function getStatuses($idList)
    {
        $status = [];
        foreach ($idList as $id) {
            $status[] = $this->getStatus($id);
        }
        return $status;
    }

    /**
     * Retrieves renew, hold and cancel settings from the driver ini file.
     *
     * @param string $function The name of the feature to be checked
     * @param array  $params   Optional feature-specific parameters (array)
     *
     * @return array An array with key-value pairs.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getConfig($function, $params = null)
    {
        return $this->config[$function] ?? false;
    }

    /**
     * This method queries the ILS for holding information.
     *
     * @param string $bibId   Bib-level id
     * @param array  $patron  Patron login information from $this->patronLogin
     * @param array  $options Extra options (not currently used)
     *
     * @return array An array of associative holding arrays
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getHolding($bibId, array $patron = null, array $options = [])
    {
        $instance = $this->getInstance($bibId);
        $query = ['query' => '(instanceId="' . $instance->id . '")'];
        $holdingResponse = $this->makeRequest(
            'GET',
            '/holdings-storage/holdings',
            $query
        );
        $holdingBody = json_decode($holdingResponse->getBody());
        $items = [];
        for ($i = 0; $i < count($holdingBody->holdingsRecords); $i++) {
            $holding = $holdingBody->holdingsRecords[$i];
            $locationName = '';
            if (!empty($holding->permanentLocationId)) {
                $locationResponse = $this->makeRequest(
                    'GET',
                    '/locations/' . $holding->permanentLocationId
                );
                $location = json_decode($locationResponse->getBody());
                $locationName = $location->name;
            }

            $query = ['query' => '(holdingsRecordId="' . $holding->id . '")'];
            $itemResponse = $this->makeRequest('GET', '/item-storage/items', $query);
            $itemBody = json_decode($itemResponse->getBody());
            for ($j = 0; $j < count($itemBody->items); $j++) {
                $item = $itemBody->items[$j];
                $items[] = [
                    'id' => $bibId,
                    'item_id' => $instance->id,
                    'holding_id' => $holding->id,
                    'number' => count($items),
                    'barcode' => $item->barcode ?? '',
                    'status' => $item->status->name,
                    'availability' => $item->status->name == 'Available',
                    'notes' => $item->notes ?? [],
                    'callnumber' => $holding->callNumber ?? '',
                    'location' => $locationName,
                    'reserve' => 'TODO',
                    'addLink' => true
                ];
            }
        }
        return $items;
    }

    /**
     * Patron Login
     *
     * This is responsible for authenticating a patron against the catalog.
     *
     * @param string $username The patron username
     * @param string $password The patron password
     *
     * @return mixed Associative array of patron info on successful login,
     *               null on unsuccessful login.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function patronLogin($username, $password)
    {
        // Get user id
        $query = ['query' => 'username == ' . $username];
        $response = $this->makeRequest('GET', '/users', $query);
        $json = json_decode($response->getBody());
        if (count($json->users) == 0) {
            throw new ILSException("User not found");
        }
        $profile = $json->users[0];
        $credentials = [
            'userId' => $profile->id,
            'username' => $username,
            'password' => $password,
        ];
        // Get token
        try {
            $response = $this->makeRequest(
                'POST',
                '/authn/login',
                json_encode($credentials)
            );
            // Replace admin with user as tenant
            $this->token = $response->getHeaders()->get('X-Okapi-Token')
                ->getFieldValue();
            $this->debug(
                'User logged in. User: ' . $username . '.' .
                ' Token: ' . substr($this->token, 0, 30) . '...'
            );
            return [
                'id' => $profile->id,
                'username' => $username,
                'cat_username' => $username,
                'cat_password' => $password,
                'firstname' => $profile->personal->firstName ?? null,
                'lastname' => $profile->personal->lastName ?? null,
                'email' => $profile->personal->email ?? null,
            ];
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * This method queries the ILS for a patron's current profile information
     *
     * @param array $patron Patron login information from $this->patronLogin
     *
     * @return array Profile data in associative array
     */
    public function getMyProfile($patron)
    {
        $query = ['query' => 'username == "' . $patron['username'] . '"'];
        $response = $this->makeRequest('GET', '/users', $query);
        $users = json_decode($response->getBody());
        $profile = $users->users[0];
        return [
            'id' => $profile->id,
            'firstname' => $profile->personal->firstName ?? null,
            'lastname' => $profile->personal->lastName ?? null,
            'address1' => $profile->personal->addresses[0]->addressLine1 ?? null,
            'city' => $profile->personal->addresses[0]->city ?? null,
            'country' => $profile->personal->addresses[0]->countryId ?? null,
            'zip' => $profile->personal->addresses[0]->postalCode ?? null,
            'phone' => $profile->personal->phone ?? null,
            'mobile_phone' => $profile->personal->mobilePhone ?? null,
            'expiration_date' => $profile->expirationDate ?? null,
        ];
    }

    /**
     * This method queries the ILS for a patron's current checked out items
     *
     * Input: Patron array returned by patronLogin method
     * Output: Returns an array of associative arrays.
     *         Each associative array contains these keys:
     *         duedate - The item's due date (a string).
     *         dueTime - The item's due time (a string, optional).
     *         dueStatus - A special status – may be 'due' (for items due very soon)
     *                     or 'overdue' (for overdue items). (optional).
     *         id - The bibliographic ID of the checked out item.
     *         source - The search backend from which the record may be retrieved
     *                  (optional - defaults to Solr). Introduced in VuFind 2.4.
     *         barcode - The barcode of the item (optional).
     *         renew - The number of times the item has been renewed (optional).
     *         renewLimit - The maximum number of renewals allowed
     *                      (optional - introduced in VuFind 2.3).
     *         request - The number of pending requests for the item (optional).
     *         volume – The volume number of the item (optional).
     *         publication_year – The publication year of the item (optional).
     *         renewable – Whether or not an item is renewable
     *                     (required for renewals).
     *         message – A message regarding the item (optional).
     *         title - The title of the item (optional – only used if the record
     *                                        cannot be found in VuFind's index).
     *         item_id - this is used to match up renew responses and must match
     *                   the item_id in the renew response.
     *         institution_name - Display name of the institution that owns the item.
     *         isbn - An ISBN for use in cover image loading
     *                (optional – introduced in release 2.3)
     *         issn - An ISSN for use in cover image loading
     *                (optional – introduced in release 2.3)
     *         oclc - An OCLC number for use in cover image loading
     *                (optional – introduced in release 2.3)
     *         upc - A UPC for use in cover image loading
     *               (optional – introduced in release 2.3)
     *         borrowingLocation - A string describing the location where the item
     *                         was checked out (optional – introduced in release 2.4)
     *
     * @param array $patron Patron login information from $this->patronLogin
     *
     * @return array Transactions associative arrays
     */
    public function getMyTransactions($patron)
    {
        $query = ['query' => 'userId==' . $patron['id']];
        $response = $this->makeRequest("GET", '/circulation/loans', $query);
        $json = json_decode($response->getBody());
        if (count($json->loans) == 0) {
            return [];
        }
        $transactions = [];
        foreach ($json->loans as $trans) {
            $date = date_create($trans->dueDate);
            $transactions[] = [
                'duedate' => date_format($date, "j M Y"),
                'dueTime' => date_format($date, "g:i:s a"),
                // TODO: Due Status
                // 'dueStatus' => $trans['itemId'],
                'id' => $trans->item->instanceId,
                'item_id' => $trans->item->id,
                'barcode' => $trans->item->barcode,
                'title' => $trans->item->title,
            ];
        }
        return $transactions;
    }

    /**
     * Get Pick Up Locations
     *
     * This is responsible get a list of valid locations for holds / recall
     * retrieval
     *
     * @param array $patron Patron information returned by $this->patronLogin
     *
     * @return array An array of associative arrays with locationID and
     * locationDisplay keys
     */
    public function getPickupLocations($patron)
    {
        $response = $this->makeRequest('GET', '/locations');
        $json = json_decode($response->getBody());
        $locations = [];
        foreach ($json->locations as $location) {
            $locations[] = [
                'locationID' => $location->id,
                'locationDisplay' => $location->name
            ];
        }
        return $locations;
    }

    /**
     * This method queries the ILS for a patron's current holds
     *
     * Input: Patron array returned by patronLogin method
     * Output: Returns an array of associative arrays, one for each hold associated
     * with the specified account. Each associative array contains these keys:
     *     type - A string describing the type of hold – i.e. hold vs. recall
     * (optional).
     *     id - The bibliographic record ID associated with the hold (optional).
     *     source - The search backend from which the record may be retrieved
     * (optional - defaults to Solr). Introduced in VuFind 2.4.
     *     location - A string describing the pickup location for the held item
     * (optional). In VuFind 1.2, this should correspond with a locationID value from
     * getPickUpLocations. In VuFind 1.3 and later, it may be either
     * a locationID value or a raw ready-to-display string.
     *     reqnum - A control number for the request (optional).
     *     expire - The expiration date of the hold (a string).
     *     create - The creation date of the hold (a string).
     *     position – The position of the user in the holds queue (optional)
     *     available – Whether or not the hold is available (true/false) (optional)
     *     item_id – The item id the request item (optional).
     *     volume – The volume number of the item (optional)
     *     publication_year – The publication year of the item (optional)
     *     title - The title of the item
     * (optional – only used if the record cannot be found in VuFind's index).
     *     isbn - An ISBN for use in cover image loading (optional)
     *     issn - An ISSN for use in cover image loading (optional)
     *     oclc - An OCLC number for use in cover image loading (optional)
     *     upc - A UPC for use in cover image loading (optional)
     *     cancel_details - The cancel token, or a blank string if cancel is illegal
     * for this hold; if omitted, this will be dynamically generated using
     * getCancelHoldDetails(). You should only fill this in if it is more efficient
     * to calculate the value up front; if it is an expensive calculation, you should
     * omit the value entirely and let getCancelHoldDetails() do its job on demand.
     * This optional feature was introduced in release 3.1.
     *
     * @param array $patron Patron login information from $this->patronLogin
     *
     * @return array Associative array of holds information
     */
    public function getMyHolds($patron)
    {
        // Get user id
        $query = ['query' => 'username == "' . $patron['username'] . '"'];
        $response = $this->makeRequest('GET', '/users', $query);
        $users = json_decode($response->getBody());
        $query = [
            'query' => 'requesterId == "' . $users->users[0]->id . '"' .
                ' and requestType == "Hold"'
        ];
        // Request HOLDS
        $response = $this->makeRequest('GET', '/request-storage/requests', $query);
        $json = json_decode($response->getBody());
        $holds = [];
        foreach ($json->requests as $hold) {
            $holds[] = [
                'type' => 'Hold',
                'create' => $hold->requestDate,
                'expire' => $hold->requestExpirationDate,
                'id' => $this->getBibId(null, null, $hold->itemId),
            ];
        }
        return $holds;
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
        try {
            $requiredBy = date_create_from_format(
                'm-d-Y',
                $holdDetails['requiredBy']
            );
        } catch (Exception $e) {
            throw new ILSException('hold_date_invalid');
        }
        // Get status to separate Holds (on checked out items) and Pages on a
        $status = $this->getStatus($holdDetails['item_id']);
        $requestBody = [
            'requestType' => $item->status->name == 'Available' ? 'Page' : 'Hold',
            'requestDate' => date('c'),
            'requesterId' => $holdDetails['patron']['id'],
            'requester' => [
                'firstName' => $holdDetails['patron']['firstname'] ?? '',
                'lastName' => $holdDetails['patron']['lastname'] ?? ''
            ],
            'itemId' => $holdDetails['item_id'],
            'fulfilmentPreference' => 'Hold Shelf',
            'requestExpirationDate' => date_format($requiredBy, 'Y-m-d'),
        ];
        $response = $this->makeRequest(
            'POST',
            '/request-storage/requests',
            json_encode($requestBody)
        );
        if ($response->isSuccess()) {
            return [
                'success' => true,
                'status' => $response->getBody()
            ];
        } else {
            throw new ILSException($response->getBody());
        }
    }

    // @codingStandardsIgnoreStart
    /** NOT FINISHED BELOW THIS LINE **/

    /**
     * Check for request blocks.
     *
     * @param array $patron The patron array with username and password
     *
     * @return array|boolean    An array of block messages or false if there are no
     *                          blocks
     * @author Michael Birkner
     */
    public function getRequestBlocks($patron)
    {
        return false;
    }

    /**
     * This method returns information on recently received issues of a serial.
     *
     *     Input: Bibliographic record ID
     *     Output: Array of associative arrays, each with a single key:
     *         issue - String describing the issue
     *
     * Currently, most drivers do not implement this method, instead always returning
     * an empty array. It is only necessary to implement this in more detail if you
     * want to populate the “Most Recent Received Issues” section of the record
     * holdings tab.
     */
    public function getPurchaseHistory($bibID)
    {
        return [];
    }

    /**
     * This method returns items that are on reserve for the specified course,
     * instructor and/or department.
     *
     *     Input: CourseID, InstructorID, DepartmentID (these values come from the
     * corresponding getCourses, getInstructors and getDepartments methods; any of
     * these three filters may be set to a blank string to skip)
     *     Output: An array of associative arrays representing reserve items. Keys:
     *         BIB_ID - The record ID of the current reserve item.
     *         COURSE_ID - The course ID associated with the
     * current reserve item, if any (required when using Solr-based reserves).
     *         DEPARTMENT_ID - The department ID associated with the current
     * reserve item, if any (required when using Solr-based reserves).
     *         INSTRUCTOR_ID - The instructor ID associated with the current
     * reserve item, if any (required when using Solr-based reserves).
     *
     */
    public function findReserves($courseID, $instructorID, $departmentID)
    {
    }

    /**
     * This method queries the ILS for a patron's current fines
     *
     *     Input: Patron array returned by patronLogin method
     *     Output: Returns an array of associative arrays, one for each fine
     * associated with the specified account. Each associative array contains
     * these keys:
     *         amount - The total amount of the fine IN PENNIES. Be sure to adjust
     * decimal points appropriately (i.e. for a $1.00 fine, amount should be 100).
     *         checkout - A string representing the date when the item was
     * checked out.
     *         fine - A string describing the reason for the fine
     * (i.e. “Overdue”, “Long Overdue”).
     *         balance - The unpaid portion of the fine IN PENNIES.
     *         createdate – A string representing the date when the fine was accrued
     * (optional)
     *         duedate - A string representing the date when the item was due.
     *         id - The bibliographic ID of the record involved in the fine.
     *         source - The search backend from which the record may be retrieved
     * (optional - defaults to Solr). Introduced in VuFind 2.4.
     *
     */
    public function getMyFines($patron)
    {
        return [];
    }

    /**
     * Get a list of funds that can be used to limit the “new item” search. Note that
     * “fund” may be a misnomer – if funds are not an appropriate way to limit your
     * new item results, you can return a different set of values from this function.
     * For example, you might just make this a wrapper for getDepartments(). The
     * important thing is that whatever you return from this function, the IDs can be
     * used as a limiter to the getNewItems() function, and the names are appropriate
     * for display on the new item search screen. If you do not want or support such
     * limits, just return an empty array here and the limit control on the new item
     * search screen will disappear.
     *
     *     Output: An associative array with key = fund ID, value = fund name.
     *
     * IMPORTANT: The return value for this method changed in r2184. If you are using
     * VuFind 1.0RC2 or earlier, this function returns a flat array of options
     * (no ID-based keys), and empty return values may cause problems. It is
     * recommended that you update to newer code before implementing the new item
     * feature in your driver.
     */
    public function getFunds()
    {
        return [];
    }

    /**
     * This method retrieves a patron's historic transactions
     * (previously checked out items).
     *
     * :!: The getConfig method must return a non-false value for this feature to be
     * enabled. For privacy reasons, the entire feature should be disabled by default
     * unless explicitly turned on in the driver's .ini file.
     *
     * This feature was added in VuFind 5.0.
     *
     *     getConfig may return the following keys if the service supports paging on
     * the ILS side:
     *         max_results - Maximum number of results that can be requested at once.
     * Overrides the config.ini Catalog section setting historic_loan_page_size.
     *         page_size - An array of allowed page sizes
     * (number of records per page)
     *         default_page_size - Default number of records per page
     *     getConfig may return the following keys if the service supports sorting:
     *         sort - An associative array where each key is a sort key and its
     * value is a translation key
     *         default_sort - Default sort key
     *     Input: Patron array returned by patronLogin method and an array of
     * optional parameters (keys = 'limit', 'page', 'sort').
     *     Output: Returns an array of associative arrays containing some or all of
     * these keys:
     *         title - item title
     *         checkoutDate - date checked out
     *         dueDate - date due
     *         id - bibliographic ID
     *         barcode - item barcode
     *         returnDate - date returned
     *         publication_year - publication year
     *         volume - item volume
     *         institution_name - owning institution
     *         borrowingLocation - checkout location
     *         message - message about the transaction
     *
     */
    public function getMyTransactionHistory($patron)
    {
        return[];
    }

    /**
     * This method queries the ILS for new items
     *
     *     Input: getNewItems takes the following parameters:
     *         page - page number of results to retrieve (counting starts at 1)
     *         limit - the size of each page of results to retrieve
     *         daysOld - the maximum age of records to retrieve in days (maximum 30)
     *         fundID - optional fund ID to use for limiting results (use a value
     * returned by getFunds, or exclude for no limit); note that “fund” may be a
     * misnomer – if funds are not an appropriate way to limit your new item results,
     * you can return a different set of values from getFunds. The important thing is
     * that this parameter supports an ID returned by getFunds, whatever that may
     * mean.
     *     Output: An associative array with two keys: 'count' (the number of items
     * in the 'results' array) and 'results' (an array of associative arrays, each
     * with a single key: 'id', a record ID).
     *
     * IMPORTANT: The fundID parameter changed behavior in r2184. In VuFind 1.0RC2
     * and earlier versions, it receives one of the VALUES returned by getFunds();
     * in more recent code, it receives one of the KEYS from getFunds(). See getFunds
     * for additional notes.
     */
    public function getNewItems($page = 1, $limit, $daysOld = 30, $fundID = null)
    {
        return [];
    }

    // @codingStandardsIgnoreEnd
}
