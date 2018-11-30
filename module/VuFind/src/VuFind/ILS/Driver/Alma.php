<?php
/**
 * Alma ILS Driver
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2017.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
namespace VuFind\ILS\Driver;

use SimpleXMLElement;
use VuFind\Exception\ILS as ILSException;
use Zend\Http\Headers;

/**
 * Alma ILS Driver
 *
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
class Alma extends AbstractBase implements \VuFindHttp\HttpServiceAwareInterface
{
    use \VuFindHttp\HttpServiceAwareTrait;
    use CacheTrait;

    /**
     * Alma API base URL.
     *
     * @var string
     */
    protected $baseUrl;

    /**
     * Alma API key.
     *
     * @var string
     */
    protected $apiKey;

    /**
     * Date converter
     *
     * @var \VuFind\Date\Converter
     */
    protected $dateConverter;

    /**
     * Configuration loader
     *
     * @var \VuFind\Config\PluginManager
     */
    protected $configLoader;

    /**
     * Constructor
     *
     * @param \VuFind\Date\Converter       $dateConverter Date converter object
     * @param \VuFind\Config\PluginManager $configLoader  Plugin manager
     */
    public function __construct(
        \VuFind\Date\Converter $dateConverter,
        \VuFind\Config\PluginManager $configLoader
    ) {
        $this->dateConverter = $dateConverter;
        $this->configLoader = $configLoader;
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
        $this->baseUrl = $this->config['Catalog']['apiBaseUrl'];
        $this->apiKey = $this->config['Catalog']['apiKey'];
    }

    /**
     * Make an HTTP request against Alma
     *
     * @param string        $path       Path to retrieve from API (excluding base
     *                                  URL/API key)
     * @param array         $paramsGet  Additional GET params
     * @param array         $paramsPost Additional POST params
     * @param string        $method     GET or POST. Default is GET.
     * @param string        $rawBody    Request body.
     * @param Headers|array $headers    Add headers to the call.
     *
     * @throws ILSException
     * @return NULL|SimpleXMLElement
     */
    protected function makeRequest(
        $path,
        $paramsGet = [],
        $paramsPost = [],
        $method = 'GET',
        $rawBody = null,
        $headers = null
    ) {
        // Set some variables
        $result = null;
        $statusCode = null;
        $returnValue = null;

        try {
            // Set API key if it is not already available in the GET params
            if (!isset($paramsGet['apiKey'])) {
                $paramsGet['apiKey'] = $this->apiKey;
            }

            // Create the API URL
            $url = strpos($path, '://') === false ? $this->baseUrl . $path : $path;

            // Create client with API URL
            $client = $this->httpService->createClient($url);

            // Set method
            $client->setMethod($method);

            // Set other GET parameters
            if ($method == 'GET') {
                $client->setParameterGet($paramsGet);
            } else {
                // Always set API key as GET parameter
                $client->setParameterGet(['apiKey' => $paramsGet['apiKey']]);

                // Set POST parameters
                if ($method == 'POST') {
                    $client->setParameterPost($paramsPost);
                }
            }

            // Set body if applicable
            if (isset($rawBody)) {
                $client->setRawBody($rawBody);
            }

            // Set headers if applicable
            if (isset($headers)) {
                $client->setHeaders($headers);
            }

            // Execute HTTP call
            $result = $client->send();
        } catch (\Exception $e) {
            throw new ILSException($e->getMessage());
        }

        // Get the HTTP status code
        $statusCode = $result->getStatusCode();

        // Check for error
        if ($result->isServerError()) {
            throw new ILSException('HTTP error code: ' . $statusCode, $statusCode);
        }

        $answer = $result->getBody();
        $answer = str_replace('xmlns=', 'ns=', $answer);
        $xml = simplexml_load_string($answer);

        if ($result->isSuccess()) {
            if (!$xml && $result->isServerError()) {
                throw new ILSException(
                    'XML is not valid or HTTP error, URL: ' . $url .
                    ', HTTP status code: ' . $statusCode, $statusCode
                );
            }
            $returnValue = $xml;
        } else {
            $almaErrorMsg = $xml->errorList->error[0]->errorMessage;
            error_log(
                '[ALMA] ' . $almaErrorMsg . ' | Call to: ' . $client->getUri() .
                '. GET params: ' . var_export($paramsGet, true) . '. POST params: ' .
                var_export($paramsPost, true) . '. Result body: ' .
                $result->getBody() . '. HTTP status code: ' . $statusCode
            );
            throw new ILSException(
                'Alma error message: ' . $almaErrorMsg . ' | HTTP error code: ' .
                $statusCode, $statusCode
            );
        }

        return $returnValue;
    }

    /**
     * Given an item, return the availability status.
     *
     * @param \SimpleXMLElement $item Item data
     *
     * @return bool
     */
    protected function getAvailabilityFromItem($item)
    {
        return (string)$item->item_data->base_status === '1';
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
     * @return array         On success an associative array with the following keys:
     *                       id, source, availability (boolean), status, location,
     *                       reserve, callnumber, duedate, returnDate, number,
     *                       barcode, item_notes, item_id, holding_id, addLink.
     */
    public function getHolding($id, array $patron = null)
    {
        // Get config data:
        $fulfillementUnits = $this->config['FulfillmentUnits'] ?? null;
        $requestableConfig = $this->config['Requestable'] ?? null;

        $results = [];
        $copyCount = 0;
        $bibPath = '/bibs/' . urlencode($id) . '/holdings';
        if ($holdings = $this->makeRequest($bibPath)) {
            foreach ($holdings->holding as $holding) {
                $holdingId = (string)$holding->holding_id;
                $locationCode = (string)$holding->location;
                $addLink = false;
                if ($fulfillementUnits != null && $requestableConfig != null) {
                    $addLink = $this->requestsAllowed(
                        $fulfillementUnits,
                        $locationCode,
                        $requestableConfig,
                        $patron
                    );
                }

                $itemPath = $bibPath . '/' . urlencode($holdingId) . '/items';
                if ($currentItems = $this->makeRequest($itemPath)) {
                    foreach ($currentItems->item as $item) {
                        $itemId = (string)$item->item_data->pid;
                        $barcode = (string)$item->item_data->barcode;
                        $processType = (string)$item->item_data->process_type;
                        $itemNotes = null;
                        if ($item->item_data->public_note != null
                            && !empty($item->item_data->public_note)
                        ) {
                            $itemNotes = [(string)$item->item_data->public_note];
                        }
                        $requested = ((string)$item->item_data->requested == 'false')
                            ? false
                            : true;

                        $number = ++$copyCount;
                        $description = null;
                        if ($item->item_data->description != null
                            && !empty($item->item_data->description)
                        ) {
                            $number = (string)$item->item_data->description;
                            $description = (string)$item->item_data->description;
                        }

                        // For some data we need to do additional API calls
                        // due to the Alma API architecture
                        $duedate = ($requested) ? 'requested' : null;
                        if ($processType == 'LOAN' && !$requested) {
                            $loanDataPath = '/bibs/' . urlencode($id) . '/holdings/'
                                . urlencode($holdingId) . '/items/'
                                . urlencode($itemId) . '/loans';
                            $loanData = $this->makeRequest($loanDataPath);
                            $loan = $loanData->item_loan;
                            $duedate = $this->parseDate((string)$loan->due_date);
                        }

                        $results[] = [
                            'id' => $id,
                            'source' => 'Solr',
                            'availability' => $this->getAvailabilityFromItem($item),
                            'status' => (string)$item
                                ->item_data
                                ->base_status[0]
                                ->attributes()['desc'],
                            'location' => $locationCode,
                            'reserve' => 'N',   // TODO: support reserve status
                            'callnumber' => (string)$item->holding_data->call_number,
                            'duedate' => $duedate,
                            'returnDate' => false, // TODO: support recent returns
                            'number' => $number,//++$copyCount,
                            'barcode' => empty($barcode) ? 'n/a' : $barcode,
                            'item_notes' => $itemNotes,
                            'item_id' => $itemId,
                            'holding_id' => $holdingId,
                            'addLink' => $addLink,
                               // For Alma title-level hold requests
                            'description' => $description
                        ];
                    }
                }
            }
        }

        return $results;
    }

    /**
     * Check if the user is allowed to place requests for an Alma fulfillment
     * unit in general. We check for blocks on the patron account that could
     * block a request in getRequestBlocks().
     *
     * @param array  $fulfillementUnits An array of fulfillment units and associated
     *                                  locations from Alma.ini (see section
     *                                  [FulfillmentUnits])
     * @param string $locationCode      The location code of the holding to be
     *                                  checked
     * @param array  $requestableConfig An array of fulfillment units and associated
     *                                  patron groups and their request policy from
     *                                  Alma.ini (see section [Requestable])
     * @param array  $patron            An array with the patron details (username
     *                                  and password)
     *
     * @return boolean                  true if the the patron is allowed to place
     *                                  requests on holdings of this fulfillment
     *                                  unit, false otherwise.
     * @author Michael Birkner
     */
    protected function requestsAllowed(
        $fulfillementUnits,
        $locationCode,
        $requestableConfig,
        $patron
    ) {
        $requestsAllowed = false;

        // Get user group code
        $cacheId = 'alma|user|' . $patron['cat_username'] . '|group_code';
        $userGroupCode = $this->getCachedData($cacheId);
        if ($userGroupCode === null) {
            $profile = $this->getMyProfile($patron);
            $userGroupCode = (string)$profile['group_code'];
        }

        // Get the fulfillment unit of the location.
        $locationFulfillmentUnit = $this->getFulfillmentUnitByLocation(
            $locationCode,
            $fulfillementUnits
        );

        // Check if the group of the currently logged in user is allowed to place
        // requests on items belonging to current fulfillment unit
        if (($locationFulfillmentUnit != null && !empty($locationFulfillmentUnit))
            && ($userGroupCode != null && !empty($userGroupCode))
        ) {
            $requestsAllowed = false;
            if ($requestableConfig[$locationFulfillmentUnit][$userGroupCode] == 'Y'
            ) {
                $requestsAllowed = true;
            }
        }

        return $requestsAllowed;
    }

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
        return $this->getAccountBlocks($patron);
    }

    /**
     * Check for account blocks in Alma and cache them.
     *
     * @param array $patron The patron array with username and password
     *
     * @return array|boolean    An array of block messages or false if there are no
     *                          blocks
     * @author Michael Birkner
     */
    public function getAccountBlocks($patron)
    {
        $patronId = $patron['cat_username'];
        $cacheId = 'alma|user|' . $patronId . '|blocks';
        $cachedBlocks = $this->getCachedData($cacheId);
        if ($cachedBlocks !== null) {
            return $cachedBlocks;
        }

        $xml = $this->makeRequest('/users/' . $patron['cat_username']);
        if ($xml == null || empty($xml)) {
            return false;
        }

        $userBlocks = $xml->user_blocks->user_block;
        if ($userBlocks == null || empty($userBlocks)) {
            return false;
        }

        $blocks = [];
        foreach ($userBlocks as $block) {
            $blockStatus = (string)$block->block_status;
            if ($blockStatus === 'ACTIVE') {
                $blockNote = (isset($block->block_note))
                             ? (string)$block->block_note
                             : null;
                $blockDesc = (string)$block->block_description->attributes()->desc;
                $blockDesc = ($blockNote != null)
                             ? $blockDesc . '. ' . $blockNote
                             : $blockDesc;
                $blocks[] = $blockDesc;
            }
        }

        if (!empty($blocks)) {
            $this->putCachedData($cacheId, $blocks);
            return $blocks;
        } else {
            $this->putCachedData($cacheId, false);
            return false;
        }
    }

    /**
     * Get an Alma fulfillment unit by an Alma location.
     *
     * @param string $locationCode     A location code, e. g. "SCI"
     * @param array  $fulfillmentUnits An array of fulfillment units with all its
     *                                 locations.
     *
     * @return string|NULL              Null if the location was not found or a
     *                                  string specifying the fulfillment unit of
     *                                  the location that was found.
     * @author Michael Birkner
     */
    protected function getFulfillmentUnitByLocation($locationCode, $fulfillmentUnits)
    {
        foreach ($fulfillmentUnits as $key => $val) {
            if (array_search($locationCode, $val) !== false) {
                return $key;
            }
        }
        return null;
    }

    /**
     * Create a user in Alma via API call
     *
     * @param array $formParams The data from the "create new account" form
     *
     * @throws \VuFind\Exception\Auth
     *
     * @return NULL|SimpleXMLElement
     * @author Michael Birkner
     */
    public function createAlmaUser($formParams)
    {

        // Get config for creating new Alma users from Alma.ini
        $newUserConfig = $this->config['NewUser'];

        // Check if config params are all set
        $configParams = [
            'recordType', 'userGroup', 'preferredLanguage',
            'accountType', 'status', 'emailType', 'idType'
        ];
        foreach ($configParams as $configParam) {
            if (!isset($newUserConfig[$configParam])
                || empty(trim($newUserConfig[$configParam]))
            ) {
                $errorMessage = 'Configuration "' . $configParam . '" is not set ' .
                                'in Alma.ini in the [NewUser] section!';
                error_log('[ALMA]: ' . $errorMessage);
                throw new \VuFind\Exception\Auth($errorMessage);
            }
        }

        // Calculate expiry date based on config in Alma.ini
        $dateNow = new \DateTime('now');
        $expiryDate = null;
        if (isset($newUserConfig['expiryDate'])
            && !empty(trim($newUserConfig['expiryDate']))
        ) {
            try {
                $expiryDate = $dateNow->add(
                    new \DateInterval($newUserConfig['expiryDate'])
                );
            } catch (\Exception $exception) {
                $errorMessage = 'Configuration "expiryDate" in Alma.ini (see ' .
                                '[NewUser] section) has the wrong format!';
                error_log('[ALMA]: ' . $errorMessage);
                throw new \VuFind\Exception\Auth($errorMessage);
            }
        } else {
            $expiryDate = $dateNow->add(new \DateInterval('P1Y'));
        }
        $expiryDateXml = ($expiryDate != null)
                 ? '<expiry_date>' . $expiryDate->format('Y-m-d') . 'Z</expiry_date>'
                 : '';

        // Calculate purge date based on config in Alma.ini
        $purgeDate = null;
        if (isset($newUserConfig['purgeDate'])
            && !empty(trim($newUserConfig['purgeDate']))
        ) {
            try {
                $purgeDate = $dateNow->add(
                    new \DateInterval($newUserConfig['purgeDate'])
                );
            } catch (\Exception $exception) {
                $errorMessage = 'Configuration "purgeDate" in Alma.ini (see ' .
                                '[NewUser] section) has the wrong format!';
                error_log('[ALMA]: ' . $errorMessage);
                throw new \VuFind\Exception\Auth($errorMessage);
            }
        }
        $purgeDateXml = ($purgeDate != null)
                    ? '<purge_date>' . $purgeDate->format('Y-m-d') . 'Z</purge_date>'
                    : '';

        // Create user XML for Alma API
        $userXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<user>'
        . '<record_type>' . $this->config['NewUser']['recordType'] . '</record_type>'
        . '<first_name>' . $formParams['firstname'] . '</first_name>'
        . '<last_name>' . $formParams['lastname'] . '</last_name>'
        . '<user_group>' . $this->config['NewUser']['userGroup'] . '</user_group>'
        . '<preferred_language>' . $this->config['NewUser']['preferredLanguage'] .
          '</preferred_language>'
        . $expiryDateXml
        . $purgeDateXml
        . '<account_type>' . $this->config['NewUser']['accountType'] .
          '</account_type>'
        . '<status>' . $this->config['NewUser']['status'] . '</status>'
        . '<contact_info>'
        . '<emails>'
        . '<email preferred="true">'
        . '<email_address>' . $formParams['email'] . '</email_address>'
        . '<email_types>'
        . '<email_type>' . $this->config['NewUser']['emailType'] . '</email_type>'
        . '</email_types>'
        . '</email>'
        . '</emails>'
        . '</contact_info>'
        . '<user_identifiers>'
        . '<user_identifier>'
        . '<id_type>' . $this->config['NewUser']['idType'] . '</id_type>'
        . '<value>' . $formParams['username'] . '</value>'
        . '</user_identifier>'
        . '</user_identifiers>'
        . '</user>';

        // Remove whitespaces from XML
        $userXml = preg_replace("/\n/i", "", $userXml);
        $userXml = preg_replace("/>\s*</i", "><", $userXml);

        // Create user in Alma
        $almaAnswer = $this->makeRequest(
            '/users',
            [],
            [],
            'POST',
            $userXml,
            ['Content-Type' => 'application/xml']
        );

        // Return the XML from Alma on success. On error, an exception is thrown
        // in makeRequest
        return $almaAnswer;
    }

    /**
     * Patron Login
     *
     * This is responsible for authenticating a patron against the catalog.
     *
     * @param string $barcode  The patrons barcode.
     * @param string $password The patrons password.
     *
     * @return string[]|NULL
     */
    public function patronLogin($barcode, $password)
    {
        // Create array of get parameters for API call
        $getParams = [
            'user_id_type' => 'all_unique',
            'view' => 'brief',
            'expand' => 'none'
        ];

        // Check for patron in Alma
        $response = $this->makeRequest('/users/' . urlencode($barcode), $getParams);

        // Test once we have access
        if ($response != null) {
            return [
                'cat_username' => trim($barcode),
                'cat_password' => trim($password)
            ];
        }

        return null;
    }

    /**
     * Get Patron Profile
     *
     * This is responsible for retrieving the profile for a specific patron.
     *
     * @param array $patron The patron array
     *
     * @return array Array of the patron's profile data on success.
     */
    public function getMyProfile($patron)
    {
        $patronId = $patron['cat_username'];
        $xml = $this->makeRequest('/users/' . $patronId);
        if (empty($xml)) {
            return [];
        }
        $profile = [
            'firstname'  => (isset($xml->first_name))
                                ? (string)$xml->first_name
                                : null,
            'lastname'   => (isset($xml->last_name))
                                ? (string)$xml->last_name
                                : null,
            'group'      => (isset($xml->user_group['desc']))
                                ? (string)$xml->user_group['desc']
                                : null,
            'group_code' => (isset($xml->user_group))
                                ? (string)$xml->user_group
                                : null
        ];
        $contact = $xml->contact_info;
        if ($contact) {
            if ($contact->addresses) {
                $address = $contact->addresses[0]->address;
                $profile['address1'] =  (isset($address->line1))
                                            ? (string)$address->line1
                                            : null;
                $profile['address2'] =  (isset($address->line2))
                                            ? (string)$address->line2
                                            : null;
                $profile['address3'] =  (isset($address->line3))
                                            ? (string)$address->line3
                                            : null;
                $profile['zip']      =  (isset($address->postal_code))
                                            ? (string)$address->postal_code
                                            : null;
                $profile['city']     =  (isset($address->city))
                                            ? (string)$address->city
                                            : null;
                $profile['country']  =  (isset($address->country))
                                            ? (string)$address->country
                                            : null;
            }
            if ($contact->phones) {
                $profile['phone'] = (isset($contact->phones[0]->phone->phone_number))
                                   ? (string)$contact->phones[0]->phone->phone_number
                                   : null;
            }
        }

        // Cache the user group code
        $cacheId = 'alma|user|' . $patronId . '|group_code';
        $this->putCachedData($cacheId, $profile['group_code'] ?? null);

        return $profile;
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
        $xml = $this->makeRequest(
            '/users/' . $patron['cat_username'] . '/fees'
        );
        $fineList = [];
        foreach ($xml as $fee) {
            $checkout = (string)$fee->status_time;
            $fineList[] = [
                "title"   => (string)$fee->type,
                "amount"   => $fee->original_amount * 100,
                "balance"  => $fee->balance * 100,
                "checkout" => $this->dateConverter->convert(
                    'Y-m-d H:i',
                    'm-d-Y',
                    $checkout
                ),
                "fine"     => (string)$fee->type['desc']
            ];
        }
        return $fineList;
    }

    /**
     * Get Patron Holds
     *
     * This is responsible for retrieving all holds by a specific patron.
     *
     * @param array $patron The patron array from patronLogin
     *
     * @return mixed        Array of the patron's holds on success.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getMyHolds($patron)
    {
        $xml = $this->makeRequest(
            '/users/' . $patron['cat_username'] . '/requests',
            ['request_type' => 'HOLD']
        );
        $holdList = [];
        foreach ($xml as $request) {
            $holdList[] = [
                'create' => (string)$request->request_date,
                'expire' => (string)$request->last_interest_date,
                'id' => (string)$request->request_id,
                'in_transit' => (string)$request->request_status !== 'On Hold Shelf',
                'item_id' => (string)$request->mms_id,
                'location' => (string)$request->pickup_location,
                'processed' => $request->item_policy === 'InterlibraryLoan'
                    && (string)$request->request_status !== 'Not Started',
                'title' => (string)$request->title,
                /*
                // VuFind keys
                'available'         => $request->,
                'canceled'          => $request->,
                'institution_dbkey' => $request->,
                'institution_id'    => $request->,
                'institution_name'  => $request->,
                'position'          => $request->,
                'reqnum'            => $request->,
                'requestGroup'      => $request->,
                'source'            => $request->,
                // Alma keys
                "author": null,
                "comment": null,
                "desc": "Book"
                "description": null,
                "material_type": {
                "pickup_location": "Burns",
                "pickup_location_library": "BURNS",
                "pickup_location_type": "LIBRARY",
                "place_in_queue": 1,
                "request_date": "2013-11-12Z"
                "request_id": "83013520000121",
                "request_status": "NOT_STARTED",
                "request_type": "HOLD",
                "title": "Test title",
                "value": "BK",
                */
            ];
        }
        return $holdList;
    }

    /**
     * Cancel hold requests.
     *
     * @param array $cancelDetails An associative array with two keys: patron
     *                             (array returned by the driver's
     *                             patronLogin method) and details (an array
     *                             of strings eturned by the driver's
     *                             getCancelHoldDetails method)
     *
     * @return array                Associative array containing with keys 'count'
     *                                 (number of items successfully cancelled) and
     *                                 'items' (array of successful cancellations).
     */
    public function cancelHolds($cancelDetails)
    {
        $returnArray = [];
        $patronId = $cancelDetails['patron']['cat_username'];
        $count = 0;

        foreach ($cancelDetails['details'] as $requestId) {
            $item = [];
            try {
                // Get some details of the requested items as we need them below.
                // We only can get them from an API request.
                $requestDetails = $this->makeRequest(
                    $this->baseUrl .
                        '/users/' . urlencode($patronId) .
                        '/requests/' . urlencode($requestId)
                );

                $mmsId = (isset($requestDetails->mms_id))
                          ? (string)$requestDetails->mms_id
                          : (string)$requestDetails->mms_id;

                // Delete the request in Alma
                $apiResult = $this->makeRequest(
                    $this->baseUrl .
                    '/users/' . urlencode($patronId) .
                    '/requests/' . urlencode($requestId),
                    ['reason' => 'CancelledAtPatronRequest'],
                    [],
                    'DELETE'
                );

                // Adding to "count" variable and setting values to return array
                $count++;
                $item[$mmsId]['success'] = true;
                $item[$mmsId]['status'] = 'hold_cancel_success';
            } catch (ILSException $e) {
                if (isset($apiResult['xml'])) {
                    $almaErrorCode = $apiResult['xml']->errorList->error->errorCode;
                    $sysMessage = $apiResult['xml']->errorList->error->errorMessage;
                } else {
                    $almaErrorCode = 'No error code available';
                    $sysMessage = 'HTTP status code: ' .
                         ($e->getCode() ?? 'Code not available');
                }
                $item[$mmsId]['success'] = false;
                $item[$mmsId]['status'] = 'hold_cancel_fail';
                $item[$mmsId]['sysMessage'] = $sysMessage . '. ' .
                         'Alma MMS ID: ' . $mmsId . '. ' .
                         'Alma request ID: ' . $requestId . '. ' .
                         'Alma error code: ' . $almaErrorCode;
            }

            $returnArray['items'] = $item;
        }

        $returnArray['count'] = $count;

        return $returnArray;
    }

    /**
     * Get details of a single hold request.
     *
     * @param array $holdDetails One of the item arrays returned by the
     *                           getMyHolds method
     *
     * @return string            The Alma request ID
     */
    public function getCancelHoldDetails($holdDetails)
    {
        return $holdDetails['id'];
    }

    /**
     * Get Patron Storage Retrieval Requests
     *
     * This is responsible for retrieving all call slips by a specific patron.
     *
     * @param array $patron The patron array from patronLogin
     *
     * @return mixed        Array of the patron's holds
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getMyStorageRetrievalRequests($patron)
    {
        $xml = $this->makeRequest(
            '/users/' . $patron['cat_username'] . '/requests',
            ['request_type' => 'MOVE']
        );
        $holdList = [];
        for ($i = 0; $i < count($xml->user_requests); $i++) {
            $request = $xml->user_requests[$i];
            if (!isset($request->item_policy)
                || $request->item_policy !== 'Archive'
            ) {
                continue;
            }
            $holdList[] = [
                'create' => $request->request_date,
                'expire' => $request->last_interest_date,
                'id' => $request->request_id,
                'in_transit' => $request->request_status !== 'IN_PROCESS',
                'item_id' => $request->mms_id,
                'location' => $request->pickup_location,
                'processed' => $request->item_policy === 'InterlibraryLoan'
                    && $request->request_status !== 'NOT_STARTED',
                'title' => $request->title,
            ];
        }
        return $holdList;
    }

    /**
     * Get Patron ILL Requests
     *
     * This is responsible for retrieving all ILL requests by a specific patron.
     *
     * @param array $patron The patron array from patronLogin
     *
     * @return mixed        Array of the patron's ILL requests
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getMyILLRequests($patron)
    {
        $xml = $this->makeRequest(
            '/users/' . $patron['cat_username'] . '/requests',
            ['request_type' => 'MOVE']
        );
        $holdList = [];
        for ($i = 0; $i < count($xml->user_requests); $i++) {
            $request = $xml->user_requests[$i];
            if (!isset($request->item_policy)
                || $request->item_policy !== 'InterlibraryLoan'
            ) {
                continue;
            }
            $holdList[] = [
                'create' => $request->request_date,
                'expire' => $request->last_interest_date,
                'id' => $request->request_id,
                'in_transit' => $request->request_status !== 'IN_PROCESS',
                'item_id' => $request->mms_id,
                'location' => $request->pickup_location,
                'processed' => $request->item_policy === 'InterlibraryLoan'
                    && $request->request_status !== 'NOT_STARTED',
                'title' => $request->title,
            ];
        }
        return $holdList;
    }

    /**
     * Get transactions of the current patron.
     *
     * @param array $patron The patron array from patronLogin
     *
     * @return string[]    Transaction information as array or empty array if the
     *                  patron has no transactions.
     *
     * @author Michael Birkner
     */
    public function getMyTransactions($patron)
    {
        // Defining the return value
        $returnArray = [];

        // Get the patrons user name
        $patronUserName = $patron['cat_username'];

        // Create a timestamp for calculating the due / overdue status
        $nowTS = mktime();

        // Create parameters for the API call
        // INFO: "order_by" does not seem to work as expected!
        //       This is an Alma API problem.
        $params = [
            'limit' => '100',
            'order_by' => 'due_date',
            'direction' => 'DESC',
            'expand' => 'renewable'
        ];

        // Get user loans from Alma API
        $apiResult = $this->makeRequest(
            '/users/' . $patronUserName . '/loans/',
            $params
        );

        // If there is an API result, process it
        if ($apiResult) {
            // Iterate over all item loans
            foreach ($apiResult->item_loan as $itemLoan) {
                $loan['duedate'] = $this->parseDate(
                    (string)$itemLoan->due_date,
                    true
                );
                //$loan['dueTime'] = ;
                $loan['dueStatus'] = null; // Calculated below
                $loan['id'] = (string)$itemLoan->mms_id;
                //$loan['source'] = 'Solr';
                $loan['barcode'] = (string)$itemLoan->item_barcode;
                //$loan['renew'] = ;
                //$loan['renewLimit'] = ;
                //$loan['request'] = ;
                //$loan['volume'] = ;
                $loan['publication_year'] = (string)$itemLoan->publication_year;
                $loan['renewable']
                    = (strtolower((string)$itemLoan->renewable) == 'true')
                    ? true
                    : false;
                //$loan['message'] = ;
                $loan['title'] = (string)$itemLoan->title;
                $loan['item_id'] = (string)$itemLoan->loan_id;
                $loan['institution_name'] = (string)$itemLoan->library;
                //$loan['isbn'] = ;
                //$loan['issn'] = ;
                //$loan['oclc'] = ;
                //$loan['upc'] = ;
                $loan['borrowingLocation'] = (string)$itemLoan->circ_desk;

                // Calculate due status
                $dueDateTS = strtotime($loan['duedate']);
                if ($nowTS > $dueDateTS) {
                    // Loan is overdue
                    $loan['dueStatus'] = 'overdue';
                } elseif (($dueDateTS - $nowTS) < 86400) {
                    // Due date within one day
                    $loan['dueStatus'] = 'due';
                }

                $returnArray[] = $loan;
            }
        }

        return $returnArray;
    }

    /**
     * Get Alma loan IDs for use in renewMyItems.
     *
     * @param array $checkOutDetails An array from getMyTransactions
     *
     * @return string The Alma loan ID for this loan
     *
     * @author Michael Birkner
     */
    public function getRenewDetails($checkOutDetails)
    {
        $loanId = $checkOutDetails['item_id'];
        return $loanId;
    }

    /**
     * Renew loans via Alma API.
     *
     * @param array $renewDetails An array with the IDs of the loans returned by
     *                            getRenewDetails and the patron information
     *                            returned by patronLogin.
     *
     * @return array[] An array with the renewal details and a success or error
     *                 message.
     *
     * @author Michael Birkner
     */
    public function renewMyItems($renewDetails)
    {
        $returnArray = [];
        $patronUserName = $renewDetails['patron']['cat_username'];

        foreach ($renewDetails['details'] as $loanId) {
            // Create an empty array that holds the information for a renewal
            $renewal = [];

            try {
                // POST the renewals to Alma
                $apiResult = $this->makeRequest(
                    '/users/' . $patronUserName . '/loans/' . $loanId . '/?op=renew',
                    [],
                    [],
                    'POST'
                );

                // Add information to the renewal array
                $blocks = false;
                $renewal[$loanId]['success'] = true;
                $renewal[$loanId]['new_date'] = $this->parseDate(
                    (string)$apiResult->due_date,
                    true
                );
                //$renewal[$loanId]['new_time'] = ;
                $renewal[$loanId]['item_id'] = (string)$apiResult->loan_id;
                $renewal[$loanId]['sysMessage'] = 'renew_success';

                // Add the renewal to the return array
                $returnArray['details'] = $renewal;
            } catch (ILSException $ilsEx) {
                // Add the empty renewal array to the return array
                $returnArray['details'] = $renewal;

                // Add a message that can be translated
                $blocks[] = 'renew_fail';
            }
        }

        $returnArray['blocks'] = $blocks;

        return $returnArray;
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
        return $this->getHolding($id);
    }

    /**
     * Get Statuses
     *
     * This is responsible for retrieving the status information for a
     * collection of records.
     *
     * @param array $ids The array of record ids to retrieve the status for
     *
     * @return array An array of getStatus() return values on success.
     */
    public function getStatuses($ids)
    {
        $results = [];
        $params = [
            'mms_id' => implode(',', $ids),
            'expand' => 'p_avail,e_avail,d_avail'
        ];
        if ($bibs = $this->makeRequest('/bibs', $params)) {
            foreach ($bibs as $bib) {
                $marc = new \File_MARCXML(
                    $bib->record->asXML(),
                    \File_MARCXML::SOURCE_STRING
                );
                $status = [];
                $tmpl = [
                    'id' => (string)$bib->mms_id,
                    'source' => 'Solr',
                    'callnumber' => isset($bib->isbn)
                        ? (string)$bib->isbn
                        : ''
                ];
                if ($record = $marc->next()) {
                    // Physical
                    $physicalItems = $record->getFields('AVA');
                    foreach ($physicalItems as $field) {
                        $avail = $field->getSubfield('e')->getData();
                        $item = $tmpl;
                        $item['availability'] = strtolower($avail) === 'available';
                        $item['location'] = (string)$field->getSubfield('c')
                            ->getData();
                        $status[] = $item;
                    }
                    // Electronic
                    $electronicItems = $record->getFields('AVE');
                    foreach ($electronicItems as $field) {
                        $avail = $field->getSubfield('e')->getData();
                        $item = $tmpl;
                        $item['availability'] = strtolower($avail) === 'available';
                        $status[] = $item;
                    }
                    // Digital
                    $digitalItems = $record->getFields('AVD');
                    foreach ($digitalItems as $field) {
                        $avail = $field->getSubfield('e')->getData();
                        $item = $tmpl;
                        $item['availability'] = strtolower($avail) === 'available';
                        $status[] = $item;
                    }
                } else {
                    // TODO: Throw error
                    error_log('no record');
                }
                $results[] = $status;
            }
        }
        return $results;
    }

    /**
     * Get Purchase History
     *
     * This is responsible for retrieving the acquisitions history data for the
     * specific record (usually recently received issues of a serial).
     *
     * @param string $id The record id to retrieve the info for
     *
     * @return array     An array with the acquisitions data on success.
     */
    public function getPurchaseHistory($id)
    {
        // TODO: Alma getPurchaseHistory
        return [];
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
    public function getConfig($function, $params = null)
    {
        if (isset($this->config[$function])) {
            $functionConfig = $this->config[$function];
        } else {
            $functionConfig = false;
        }

        return $functionConfig;
    }

    /**
     * Place a hold request via Alma API. This could be a title level request or
     * an item level request.
     *
     * @param array $holdDetails An associative array w/ atleast patron and item_id
     *
     * @return array success: bool, sysMessage: string
     *
     * @link https://developers.exlibrisgroup.com/alma/apis/bibs
     */
    public function placeHold($holdDetails)
    {
        // Check for title or item level request
        $level = $holdDetails['level'] ?? 'item';

        // Get information that is valid for both, item level requests and title
        // level requests.
        $mmsId = $holdDetails['id'];
        $holId = $holdDetails['holding_id'];
        $itmId = $holdDetails['item_id'];
        $patronCatUsername = $holdDetails['patron']['cat_username'];
        $pickupLocation = $holdDetails['pickUpLocation'] ?? null;
        $comment = $holdDetails['comment'] ?? null;
        $requiredBy = (isset($holdDetails['requiredBy']))
        ? $this->dateConverter->convertFromDisplayDate(
            'Y-m-d',
            $holdDetails['requiredBy']
        ) . 'Z'
        : null;

        // Create body for API request
        $body = [];
        $body['request_type'] = 'HOLD';
        $body['pickup_location_type'] = 'LIBRARY';
        $body['pickup_location_library'] = $pickupLocation;
        $body['comment'] = $comment;
        $body['last_interest_date'] = $requiredBy;

        // Remove "null" values from body array
        $body = array_filter($body);

        // Check if we have a title level request or an item level request
        if ($level === 'title') {
            // Add description if we have one for title level requests as Alma
            // needs it under certain circumstances. See: https://developers.
            // exlibrisgroup.com/alma/apis/xsd/rest_user_request.xsd?tags=POST
            $description = isset($holdDetails['description']) ?? null;
            if ($description) {
                $body['description'] = $description;
            }

            // Create HTTP client with Alma API URL for title level requests
            $client = $this->httpService->createClient(
                $this->baseUrl . '/bibs/' . urlencode($mmsId)
                . '/requests?apiKey=' . urlencode($this->apiKey)
                . '&user_id=' . urlencode($patronCatUsername)
                . '&format=json'
            );
        } else {
            // Create HTTP client with Alma API URL for item level requests
            $client = $this->httpService->createClient(
                $this->baseUrl . '/bibs/' . urlencode($mmsId)
                . '/holdings/' . urlencode($holId)
                . '/items/' . urlencode($itmId)
                . '/requests?apiKey=' . urlencode($this->apiKey)
                . '&user_id=' . urlencode($patronCatUsername)
                . '&format=json'
            );
        }

        // Set headers
        $client->setHeaders(
            [
            'Content-type: application/json',
            'Accept: application/json'
            ]
        );

        // Set HTTP method
        $client->setMethod(\Zend\Http\Request::METHOD_POST);

        // Set body
        $client->setRawBody(json_encode($body));

        // Send API call and get response
        $response = $client->send();

        // Check for success
        if ($response->isSuccess()) {
            return ['success' => true];
        } else {
            // TODO: Throw an error
            error_log($response->getBody());
        }

        // Get error message
        $error = json_decode($response->getBody());
        if (!$error) {
            $error = simplexml_load_string($response->getBody());
        }

        return [
            'success' => false,
            'sysMessage' => $error->errorList->error[0]->errorMessage
        ];
    }

    /**
     * Get Pick Up Locations
     *
     * This is responsible get a list of valid library locations for holds / recall
     * retrieval
     *
     * @param array $patron Patron information returned by the patronLogin method.
     *
     * @return array An array of associative arrays with locationID and
     * locationDisplay keys
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getPickupLocations($patron)
    {
        $xml = $this->makeRequest('/conf/libraries');
        $libraries = [];
        foreach ($xml as $library) {
            $libraries[] = [
                'locationID' => $library->code,
                'locationDisplay' => $library->name
            ];
        }
        return $libraries;
    }

    /**
     * Request from /courses.
     *
     * @return array with key = course ID, value = course name
     */
    public function getCourses()
    {
        // https://developers.exlibrisgroup.com/alma/apis/courses
        // GET /almaws/v1/courses
        $xml = $this->makeRequest('/courses');
        $courses = [];
        foreach ($xml as $course) {
            $courses[$course->id] = $course->name;
        }
        return $courses;
    }

    /**
     * Get reserves by course
     *
     * @param string $courseID     Value from getCourses
     * @param string $instructorID Value from getInstructors (not used yet)
     * @param string $departmentID Value from getDepartments (not used yet)
     *
     * @return array With key BIB_ID - The record ID of the current reserve item.
     *               Not currently used:
     *               DISPLAY_CALL_NO, AUTHOR, TITLE, PUBLISHER, PUBLISHER_DATE
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function findReserves($courseID, $instructorID, $departmentID)
    {
        // https://developers.exlibrisgroup.com/alma/apis/courses
        // GET /almaws/v1/courses/{course_id}/reading-lists
        $xml = $this->makeRequest('/courses/' . $courseID . '/reading-lists');
        $reserves = [];
        foreach ($xml as $list) {
            $listXML = $this->makeRequest(
                "/courses/${$courseID}/reading-lists/${$list->id}/citations"
            );
            foreach ($listXML as $citation) {
                $reserves[$citation->id] = $citation->metadata;
            }
        }
        return $reserves;
    }

    /**
     * Parse a date.
     *
     * @param string  $date     Date to parse
     * @param boolean $withTime Add time to return if available?
     *
     * @return string
     */
    public function parseDate($date, $withTime = false)
    {
        // Remove trailing Z from end of date
        // e.g. from Alma we get dates like 2012-07-13Z without time, which is wrong)
        if (strpos($date, 'Z', (strlen($date) - 1))) {
            $date = preg_replace('/Z{1}$/', '', $date);
        }

        $compactDate = "/^[0-9]{8}$/"; // e. g. 20120725
        $euroName = "/^[0-9]+\/[A-Za-z]{3}\/[0-9]{4}$/"; // e. g. 13/jan/2012
        $euro = "/^[0-9]+\/[0-9]+\/[0-9]{4}$/"; // e. g. 13/7/2012
        $euroPad = "/^[0-9]{1,2}\/[0-9]{1,2}\/[0-9]{2,4}$/"; // e. g. 13/07/2012
        $datestamp = "/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/"; // e. g. 2012-07-13
        $timestamp = "/^[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}$/";
        // e. g. 2017-07-09T18:00:00

        if ($date == null || $date == '') {
            return '';
        } elseif (preg_match($compactDate, $date) === 1) {
            return $this->dateConverter->convertToDisplayDate('Ynd', $date);
        } elseif (preg_match($euroName, $date) === 1) {
            return $this->dateConverter->convertToDisplayDate('d/M/Y', $date);
        } elseif (preg_match($euro, $date) === 1) {
            return $this->dateConverter->convertToDisplayDate('d/m/Y', $date);
        } elseif (preg_match($euroPad, $date) === 1) {
            return $this->dateConverter->convertToDisplayDate('d/m/y', $date);
        } elseif (preg_match($datestamp, $date) === 1) {
            return $this->dateConverter->convertToDisplayDate('Y-m-d', $date);
        } elseif (preg_match($timestamp, substr($date, 0, 19)) === 1) {
            if ($withTime) {
                return $this->dateConverter->convertToDisplayDateAndTime(
                    'Y-m-d\TH:i:s',
                    substr($date, 0, 19)
                );
            } else {
                return $this->dateConverter->convertToDisplayDate(
                    'Y-m-d',
                    substr($date, 0, 10)
                );
            }
        } else {
            throw new \Exception("Invalid date: $date");
        }
    }

    // @codingStandardsIgnoreStart

    /**
     * @return array with key = course ID, value = course name
     * /
     * public function getFunds() {
     * // https://developers.exlibrisgroup.com/alma/apis/acq
     * // GET /almaws/v1/acq/funds
     * }
     */

    /* ================= METHODS INACCESSIBLE OUTSIDE OF GET ================== */

    /**
     * @param array $cancelDetails An associative array with two keys:
     *                  patron  (array returned by the driver's patronLogin method)
     *                  details (array returned by the driver's getCancelHoldDetails)
     *
     * @return array count – The number of items successfully cancelled
     *               items – Associative array where keyed by item_id (getMyHolds)
     *                    success – Boolean true or false
     *                    status – A status message from the language file (required)
     *                    sysMessage - A system supplied failure message (optional)
     * /
     * public function cancelHolds($cancelDetails) {
     * // https://developers.exlibrisgroup.com/alma/apis/users
     * // DELETE /almaws/v1/users/{user_id}/requests/{request_id}
     * }
     */
    // @codingStandardsIgnoreEnd
}
