<?php

/**
 * Alma ILS Driver
 *
 * PHP version 8
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

use Laminas\Http\Headers;
use SimpleXMLElement;
use VuFind\Exception\ILS as ILSException;
use VuFind\I18n\TranslatableString;
use VuFind\I18n\Translator\TranslatorAwareInterface;
use VuFind\I18n\Translator\TranslatorAwareTrait;
use VuFind\ILS\Logic\AvailabilityStatusInterface;
use VuFind\Marc\MarcReader;

use function count;
use function floatval;
use function in_array;
use function is_callable;

/**
 * Alma ILS Driver
 *
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
class Alma extends AbstractBase implements
    \VuFindHttp\HttpServiceAwareInterface,
    \Laminas\Log\LoggerAwareInterface,
    TranslatorAwareInterface
{
    use \VuFindHttp\HttpServiceAwareTrait;
    use \VuFind\Log\LoggerAwareTrait;
    use \VuFind\Cache\CacheTrait;
    use TranslatorAwareTrait;

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
     * Mappings from location type to item status. Overrides any other item status.
     *
     * @var array
     */
    protected $locationTypeToItemStatus = [];

    /**
     * Constructor
     *
     * @param \VuFind\Date\Converter $dateConverter Date converter object
     */
    public function __construct(\VuFind\Date\Converter $dateConverter)
    {
        $this->dateConverter = $dateConverter;
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

        if (!empty($this->config['Holdings']['locationTypeItemStatus'])) {
            $this->locationTypeToItemStatus
                = $this->config['Holdings']['locationTypeItemStatus'];
        }
    }

    /**
     * Make an HTTP request against Alma
     *
     * @param string        $path          Path to retrieve from API (excluding base
     *                                     URL/API key)
     * @param array         $paramsGet     Additional GET params
     * @param array         $paramsPost    Additional POST params
     * @param string        $method        GET or POST. Default is GET.
     * @param string        $rawBody       Request body.
     * @param Headers|array $headers       Add headers to the call.
     * @param array         $allowedErrors HTTP status codes that are not treated as
     *                                     API errors.
     * @param bool          $returnStatus  Whether to return HTTP status in addition
     *                                     to the response.
     *
     * @throws ILSException
     * @return null|SimpleXMLElement|array
     */
    protected function makeRequest(
        $path,
        $paramsGet = [],
        $paramsPost = [],
        $method = 'GET',
        $rawBody = null,
        $headers = null,
        $allowedErrors = [],
        $returnStatus = false
    ) {
        // Set some variables
        $url = null;
        $result = null;
        $statusCode = null;
        $returnValue = null;
        $startTime = microtime(true);

        try {
            // Set API key if it is not already available in the GET params
            if (!isset($paramsGet['apikey'])) {
                $paramsGet['apikey'] = $this->apiKey;
            }

            // Create the API URL
            $url = !str_contains($path, '://') ? $this->baseUrl . $path : $path;

            // Create client with API URL
            $client = $this->httpService->createClient($url);

            // Set method
            $client->setMethod($method);

            // Set timeout
            $timeout = $this->config['Catalog']['http_timeout'] ?? 30;
            $client->setOptions(['timeout' => $timeout]);

            // Set other GET parameters (apikey and other URL parameters are used
            // also with e.g. POST requests)
            $client->setParameterGet($paramsGet);
            // Set POST parameters
            if ($method == 'POST') {
                $client->setParameterPost($paramsPost);
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
            $this->logError("$method request '$url' failed: " . $e->getMessage());
            $this->throwAsIlsException($e);
        }

        // Get the HTTP status code and response
        $statusCode = $result->getStatusCode();
        $answer = $statusCode !== 204 ? $result->getBody() : '';
        $answer = str_replace('xmlns=', 'ns=', $answer);

        $duration = round(microtime(true) - $startTime, 4);
        $urlParams = $client->getRequest()->getQuery()->toString();
        $fullUrl = $url . (!str_contains($url, '?') ? '?' : '&') . $urlParams;
        $this->debug(
            "[$duration] $method request '$fullUrl' results ($statusCode):\n"
            . $answer
        );

        // Check for error
        if ($result->isServerError()) {
            $this->logError(
                "$method request '$url' failed, HTTP error code: $statusCode"
            );
            throw new ILSException('HTTP error code: ' . $statusCode, $statusCode);
        }

        try {
            $xml = simplexml_load_string($answer);
        } catch (\Exception $e) {
            $this->logError(
                "Could not parse response for $method request '$url': "
                . $e->getMessage() . ". Response was:\n"
                . $result->getHeaders()->toString()
                . "\n\n$answer"
            );
            $this->throwAsIlsException($e);
        }
        if ($result->isSuccess() || in_array($statusCode, $allowedErrors)) {
            if (!$xml && $result->isServerError()) {
                $error = 'XML is not valid or HTTP error, URL: ' . $url .
                    ', HTTP status code: ' . $statusCode;
                $this->logError($error);
                throw new ILSException($error, $statusCode);
            }
            $returnValue = $xml;
        } else {
            $almaErrorMsg = $xml->errorList->error[0]->errorMessage
                ?? '[could not parse error message]';
            $errorMsg = "Alma error for $method request '$url' (status code"
                . " $statusCode): $almaErrorMsg";
            $this->logError(
                $errorMsg . '. GET params: ' . $this->varDump($paramsGet)
                . '. POST params: ' . $this->varDump($paramsPost)
                . '. Result body: ' . $result->getBody()
            );
            throw new ILSException($errorMsg, $statusCode);
        }

        return $returnStatus ? [$returnValue, $statusCode] : $returnValue;
    }

    /**
     * Given an item, return its availability and status.
     *
     * @param \SimpleXMLElement $item Item data
     *
     * @return array Availability and status
     */
    protected function getItemAvailabilityAndStatus(\SimpleXMLElement $item): array
    {
        // Check location type to status mappings first since they override
        // everything else:
        $available = null;
        $status = null;
        if ($this->locationTypeToItemStatus) {
            [$available, $status] = $this->getItemStatusFromLocationTypeMap(
                $this->getItemLocationType($item)
            );
        }

        // Normal checks for status if no mapping found above:
        if (null === $status) {
            $status = (string)$item->item_data->base_status[0]->attributes()['desc'];
            $duedate = $item->item_data->due_date
                ? $this->parseDate((string)$item->item_data->due_date) : null;
            if ($duedate && 'Item not in place' === $status) {
                $status = 'Checked Out';
            }

            $processType = (string)($item->item_data->process_type ?? '');
            if ($processType && 'LOAN' !== $processType) {
                $status = $this->getTranslatableStatusString(
                    $item->item_data->process_type
                );
            }
        }

        // Normal check for availability if no mapping found above:
        if (null === $available) {
            $available = (string)$item->item_data->base_status === '1'
                ? AvailabilityStatusInterface::STATUS_AVAILABLE
                : AvailabilityStatusInterface::STATUS_UNAVAILABLE;
        }

        return [$available, $status];
    }

    /**
     * Given an item, return its availability and status based on location type
     * mappings.
     *
     * @param string $locationType Location type
     *
     * @return array Availability and status
     */
    protected function getItemStatusFromLocationTypeMap(string $locationType): array
    {
        if (null === ($setting = $this->locationTypeToItemStatus[$locationType] ?? null)) {
            return [null, null];
        }
        $parts = explode(':', $setting);
        $available = null;
        $status = new TranslatableString($parts[0], $parts[0]);
        if (isset($parts[1])) {
            switch ($parts[1]) {
                case 'unavailable':
                    $available = AvailabilityStatusInterface::STATUS_UNAVAILABLE;
                    break;
                case 'uncertain':
                    $available = AvailabilityStatusInterface::STATUS_UNCERTAIN;
                    break;
                default:
                    $available = AvailabilityStatusInterface::STATUS_AVAILABLE;
                    break;
            }
        }
        return [$available, $status];
    }

    /**
     * Get Holding
     *
     * This is responsible for retrieving the holding information of a certain
     * record.
     *
     * @param string $id      The record id to retrieve the holdings for
     * @param array  $patron  Patron data
     * @param array  $options Additional options
     *
     * @return array On success an array with the key "total" containing the total
     * number of items for the given bib id, and the key "holdings" containing an
     * array of holding information each one with these keys: id, source,
     * availability, status, location, reserve, callnumber, duedate, returnDate,
     * number, barcode, item_notes, item_id, holdings_id, addLink, description
     */
    public function getHolding($id, $patron = null, array $options = [])
    {
        // Prepare result array with default values. If no API result can be received
        // these will be returned.
        $results = ['total' => 0, 'holdings' => []];

        // Correct copy count in case of paging
        $copyCount = $options['offset'] ?? 0;

        // Paging parameters for paginated API call. The "limit" tells the API how
        // many items the call should return at once (e. g. 10). The "offset" defines
        // the range (e. g. get items 30 to 40). With these parameters we are able to
        // use a paginator for paging through many items.
        $apiPagingParams = '';
        if ($options['itemLimit'] ?? null) {
            $apiPagingParams = '&limit=' . urlencode($options['itemLimit'])
                . '&offset=' . urlencode($options['offset'] ?? 0);
        }

        // The path for the API call. We call "ALL" available items, but not at once
        // as a pagination mechanism is used. If paging params are not set for some
        // reason, the first 10 items are called which is the default API behaviour.
        $itemsPath = '/bibs/' . rawurlencode($id) . '/holdings/ALL/items'
            . '?order_by=library,location,enum_a,enum_b&direction=desc'
            . '&expand=due_date'
            . $apiPagingParams;

        if ($items = $this->makeRequest($itemsPath)) {
            // Get the total number of items returned from the API call and set it to
            // a class variable. It is then used in VuFind\RecordTab\HoldingsILS for
            // the items paginator.
            $results['total'] = (int)$items->attributes()->total_record_count;

            foreach ($items->item as $item) {
                $number = ++$copyCount;
                $holdingId = (string)$item->holding_data->holding_id;
                $itemId = (string)$item->item_data->pid;
                $barcode = (string)$item->item_data->barcode;
                $itemNotes = !empty($item->item_data->public_note)
                    ? [(string)$item->item_data->public_note] : null;
                $duedate = $item->item_data->due_date
                    ? $this->parseDate((string)$item->item_data->due_date) : null;
                [$available, $status] = $this->getItemAvailabilityAndStatus($item);

                $description = null;
                if (!empty($item->item_data->description)) {
                    $number = (string)$item->item_data->description;
                    $description = (string)$item->item_data->description;
                }
                $callnumber = $item->holding_data->call_number;
                $results['holdings'][] = [
                    'id' => $id,
                    'source' => 'Solr',
                    'availability' => $available,
                    'status' => $status,
                    'location' => $this->getItemLocation($item),
                    'reserve' => 'N',   // TODO: support reserve status
                    'callnumber' => (string)($callnumber->desc ?? $callnumber),
                    'duedate' => $duedate,
                    'returnDate' => false, // TODO: support recent returns
                    'number' => $number,
                    'barcode' => empty($barcode) ? 'n/a' : $barcode,
                    'item_notes' => $itemNotes ?? null,
                    'item_id' => $itemId,
                    'holdings_id' => $holdingId,
                    'holding_id' => $holdingId, // deprecated, retained for backward compatibility
                    'holdtype' => 'auto',
                    'addLink' => $patron ? 'check' : false,
                    // For Alma title-level hold requests
                    'description' => $description ?? null,
                ];
            }
        }

        // Fetch also digital and/or electronic inventory if configured
        $types = $this->getInventoryTypes();
        if (in_array('d_avail', $types) || in_array('e_avail', $types)) {
            // No need for physical items
            $key = array_search('p_avail', $types);
            if (false !== $key) {
                unset($types[$key]);
            }
            $statuses = $this->getStatusesForInventoryTypes((array)$id, $types);
            $electronic = [];
            foreach ($statuses as $record) {
                foreach ($record as $status) {
                    $electronic[] = $status;
                }
            }
            $results['electronic_holdings'] = $electronic;
        }

        return $results;
    }

    /**
     * Check if request is valid
     *
     * This is responsible for determining if an item is requestable
     *
     * @param string $id     The record id
     * @param array  $data   An array of item data
     * @param array  $patron An array of patron data
     *
     * @return bool True if request is valid, false if not
     */
    public function checkRequestIsValid($id, $data, $patron)
    {
        $patronId = $patron['id'];
        $level = $data['level'] ?? 'copy';
        if ('copy' === $level) {
            // Call the request-options API for the logged-in user; note that holding_id
            // is deprecated but retained for backward compatibility.
            $requestOptionsPath = '/bibs/' . rawurlencode($id)
                . '/holdings/' . rawurlencode($data['holdings_id'] ?? $data['holding_id'])
                . '/items/' . rawurlencode($data['item_id']) . '/request-options?user_id='
                . urlencode($patronId);

            // Make the API request
            $requestOptions = $this->makeRequest($requestOptionsPath);
        } elseif ('title' === $level) {
            $hmac = explode(':', $this->config['Holds']['HMACKeys'] ?? '');
            if (!in_array('level', $hmac) || !in_array('description', $hmac)) {
                return false;
            }
            // Call the request-options API for the logged-in user
            $requestOptionsPath = '/bibs/' . rawurlencode($id)
                . '/request-options?user_id=' . urlencode($patronId);

            // Make the API request
            $requestOptions = $this->makeRequest($requestOptionsPath);
        } else {
            return false;
        }

        // Check possible request types from the API answer
        $requestTypes = $requestOptions->xpath(
            '/request_options/request_option//type'
        );
        foreach ($requestTypes as $requestType) {
            if ('HOLD' === (string)$requestType) {
                return true;
            }
        }

        return false;
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
        $patronId = $patron['id'];
        $cacheId = 'alma|user|' . $patronId . '|blocks';
        $cachedBlocks = $this->getCachedData($cacheId);
        if ($cachedBlocks !== null) {
            return $cachedBlocks;
        }

        $xml = $this->makeRequest('/users/' . rawurlencode($patronId));
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
        $newUserConfig = $this->config['NewUser'] ?? [];

        // Check if config params are all set
        $configParams = [
            'recordType', 'userGroup', 'preferredLanguage',
            'accountType', 'status', 'emailType', 'idType',
        ];
        foreach ($configParams as $configParam) {
            if (empty(trim($newUserConfig[$configParam] ?? ''))) {
                $errorMessage = 'Configuration "' . $configParam . '" is not set ' .
                                'in Alma ini in the [NewUser] section!';
                $this->logError($errorMessage);
                throw new \VuFind\Exception\Auth($errorMessage);
            }
        }

        // Calculate expiry date based on config in Alma.ini
        $expiryDate = new \DateTime('now');
        if (!empty(trim($newUserConfig['expiryDate'] ?? ''))) {
            try {
                $expiryDate->add(
                    new \DateInterval($newUserConfig['expiryDate'])
                );
            } catch (\Exception $exception) {
                $errorMessage = 'Configuration "expiryDate" in Alma ini (see ' .
                                '[NewUser] section) has the wrong format!';
                $this->logError($errorMessage);
                throw new \VuFind\Exception\Auth($errorMessage);
            }
        } else {
            $expiryDate->add(new \DateInterval('P1Y'));
        }

        // Calculate purge date based on config in Alma.ini
        $purgeDate = null;
        if (!empty(trim($newUserConfig['purgeDate'] ?? ''))) {
            try {
                $purgeDate = new \DateTime('now');
                $purgeDate->add(
                    new \DateInterval($newUserConfig['purgeDate'])
                );
            } catch (\Exception $exception) {
                $errorMessage = 'Configuration "purgeDate" in Alma ini (see ' .
                                '[NewUser] section) has the wrong format!';
                $this->logError($errorMessage);
                throw new \VuFind\Exception\Auth($errorMessage);
            }
        }

        // Create user XML for Alma API
        $xml = simplexml_load_string(
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . "\n\n<user/>"
        );
        $xml->addChild('record_type', $newUserConfig['recordType']);
        $xml->addChild('first_name', $formParams['firstname']);
        $xml->addChild('last_name', $formParams['lastname']);
        $xml->addChild('user_group', $newUserConfig['userGroup']);
        $xml->addChild(
            'preferred_language',
            $newUserConfig['preferredLanguage']
        );
        $xml->addChild('account_type', $newUserConfig['accountType']);
        $xml->addChild('status', $newUserConfig['status']);
        $xml->addChild('expiry_date', $expiryDate->format('Y-m-d') . 'Z');
        if (null !== $purgeDate) {
            $xml->addChild('purge_date', $purgeDate->format('Y-m-d') . 'Z');
        }

        $contactInfo = $xml->addChild('contact_info');
        $emails = $contactInfo->addChild('emails');
        $email = $emails->addChild('email');
        $email->addAttribute('preferred', 'true');
        $email->addChild('email_address', $formParams['email']);
        $emailTypes = $email->addChild('email_types');
        $emailTypes->addChild('email_type', $newUserConfig['emailType']);

        $userIdentifiers = $xml->addChild('user_identifiers');
        $userIdentifier = $userIdentifiers->addChild('user_identifier');
        $userIdentifier->addChild('id_type', $newUserConfig['idType']);
        $userIdentifier->addChild('value', $formParams['username']);

        $userXml = $xml->asXML();

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
     * @param string $username The patrons barcode or other username.
     * @param string $password The patrons password.
     *
     * @return string[]|NULL
     */
    public function patronLogin($username, $password)
    {
        $loginMethod = $this->config['Catalog']['loginMethod'] ?? 'vufind';

        $patron = [];
        $patronId = $username;
        if ('email' === $loginMethod) {
            // Try to find the user in Alma by an identifier
            [$response, $status] = $this->makeRequest(
                '/users/' . rawurlencode($username),
                [
                    'view' => 'full',
                ],
                [],
                'GET',
                null,
                null,
                [400],
                true
            );
            if (400 != $status) {
                $patron = [
                    'id' => (string)$response->primary_id,
                    'cat_username' => trim($username),
                    'email' => $this->getPreferredEmail($response),
                ];
            } else {
                // Try to find the user in Alma by unique email address
                $getParams = [
                    'q' => 'email~' . $username,
                ];

                $response = $this->makeRequest(
                    '/users/',
                    $getParams
                );

                foreach (($response->user ?? []) as $user) {
                    if ((string)$user->status !== 'ACTIVE') {
                        continue;
                    }
                    if ($patron) {
                        // More than one match, cannot log in by email
                        $this->debug(
                            "Email $username matches more than one user, cannot"
                            . ' login'
                        );
                        return null;
                    }
                    $patron = [
                        'id' => (string)$user->primary_id,
                        'cat_username' => trim($username),
                        'email' => trim($username),
                    ];
                }
            }
            if (!$patron) {
                return null;
            }
            // Use primary id in further queries
            $patronId = $patron['id'];
        } elseif ('password' === $loginMethod) {
            // Create parameters for API call
            $getParams = [
                'user_id_type' => 'all_unique',
                'op' => 'auth',
                'password' => $password,
            ];

            // Try to authenticate the user with Alma
            [$response, $status] = $this->makeRequest(
                '/users/' . rawurlencode($username),
                $getParams,
                [],
                'POST',
                null,
                null,
                [400],
                true
            );
            if (400 === $status) {
                return null;
            }
        } elseif ('vufind' !== $loginMethod) {
            $this->logError("Invalid login method configured: $loginMethod");
            throw new ILSException('Invalid login method configured');
        }

        // Create parameters for API call
        $getParams = [
            'user_id_type' => 'all_unique',
            'view' => 'full',
            'expand' => 'none',
        ];

        // Check for patron in Alma
        [$response, $status] = $this->makeRequest(
            '/users/' . rawurlencode($patronId),
            $getParams,
            [],
            'GET',
            null,
            null,
            [400],
            true
        );

        if ($status != 400 && $response !== null) {
            // We may already have some information, so just fill the gaps
            $patron['id'] = (string)$response->primary_id;
            $patron['cat_username'] = trim($username);
            $patron['cat_password'] = trim($password);
            $patron['firstname'] = (string)$response->first_name ?? '';
            $patron['lastname'] = (string)$response->last_name ?? '';
            $patron['email'] = $this->getPreferredEmail($response);
            return $patron;
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
        $patronId = $patron['id'];
        $xml = $this->makeRequest('/users/' . rawurlencode($patronId));
        if (empty($xml)) {
            return [];
        }
        $profile = [
            'firstname' => (isset($xml->first_name))
                ? (string)$xml->first_name
                : null,
            'lastname' => (isset($xml->last_name))
                ? (string)$xml->last_name
                : null,
            'group' => isset($xml->user_group)
                ? $this->getTranslatableString($xml->user_group)
                : null,
            'group_code' => (isset($xml->user_group))
                ? (string)$xml->user_group
                : null,
        ];
        $contact = $xml->contact_info;
        if ($contact) {
            if ($contact->addresses) {
                $address = $contact->addresses[0]->address;
                $profile['address1'] = (isset($address->line1))
                    ? (string)$address->line1
                    : null;
                $profile['address2'] = (isset($address->line2))
                    ? (string)$address->line2
                    : null;
                $profile['address3'] = (isset($address->line3))
                    ? (string)$address->line3
                    : null;
                $profile['zip'] = (isset($address->postal_code))
                    ? (string)$address->postal_code
                    : null;
                $profile['city'] = (isset($address->city))
                    ? (string)$address->city
                    : null;
                $profile['country'] = (isset($address->country))
                    ? (string)$address->country
                    : null;
            }
            if ($contact->phones) {
                $profile['phone'] = (isset($contact->phones[0]->phone->phone_number))
                    ? (string)$contact->phones[0]->phone->phone_number
                    : null;
            }
            $profile['email'] = $this->getPreferredEmail($xml);
        }
        if ($xml->birth_date) {
            // Drop any time zone designator from the date:
            $profile['birthdate'] = substr((string)$xml->birth_date, 0, 10);
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
            '/users/' . rawurlencode($patron['id']) . '/fees'
        );
        $fineList = [];
        foreach ($xml as $fee) {
            $created = (string)$fee->creation_time;
            $checkout = (string)$fee->status_time;
            $fineList[] = [
                'title'    => (string)($fee->title ?? ''),
                'amount'   => round(floatval($fee->original_amount) * 100),
                'balance'  => round(floatval($fee->balance) * 100),
                'createdate' => $this->parseDate($created, true),
                'checkout' => $this->parseDate($checkout, true),
                'fine'     => $this->getTranslatableString($fee->type),
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
        $holdList = [];
        $offset = 0;
        $totalCount = 1;
        $allowCancelingAvailableRequests
            = $this->config['Holds']['allowCancelingAvailableRequests'] ?? true;
        while ($offset < $totalCount) {
            $xml = $this->makeRequest(
                '/users/' . rawurlencode($patron['id']) . '/requests',
                ['request_type' => 'HOLD', 'offset' => $offset, 'limit' => 100]
            );
            $offset += 100;
            $totalCount = (int)$xml->attributes()->{'total_record_count'};
            foreach ($xml as $request) {
                $lastInterestDate = $request->last_interest_date
                    ? $this->dateConverter->convertToDisplayDate(
                        'Y-m-dT',
                        (string)$request->last_interest_date
                    ) : null;
                $available = (string)$request->request_status === 'On Hold Shelf';
                $lastPickupDate = null;
                if ($available) {
                    $lastPickupDate = $request->expiry_date
                        ? $this->dateConverter->convertToDisplayDate(
                            'Y-m-dT',
                            (string)$request->expiry_date
                        ) : null;
                    $lastInterestDate = null;
                }
                $requestStatus = (string)$request->request_status;
                $updateDetails = (!$available || $allowCancelingAvailableRequests)
                    ? (string)$request->request_id : '';

                $hold = [
                    'create' => $this->parseDate((string)$request->request_time),
                    'expire' => $lastInterestDate,
                    'id' => (string)($request->mms_id ?? ''),
                    'reqnum' => (string)$request->request_id,
                    'available' => $available,
                    'last_pickup_date' => $lastPickupDate,
                    'item_id' => (string)$request->request_id,
                    'location' => (string)$request->pickup_location,
                    'processed' => $request->item_policy === 'InterlibraryLoan'
                        && $requestStatus !== 'Not Started',
                    'title' => (string)$request->title,
                    'cancel_details' => $updateDetails,
                    'updateDetails' => $updateDetails,
                ];
                if (!$available) {
                    $hold['position'] = 'In Process' === $requestStatus
                        ? $this->translate('hold_in_process')
                        : (int)($request->place_in_queue ?? 1);
                }

                $holdList[] = $hold;
            }
        }
        return $holdList;
    }

    /**
     * Cancel hold requests.
     *
     * @param array $cancelDetails An associative array with two keys: patron
     *                             (array returned by the driver's
     *                             patronLogin method) and details (an array
     *                             of strings returned in holds' cancel_details
     *                             field.
     *
     * @return array                Associative array containing with keys 'count'
     *                                 (number of items successfully cancelled) and
     *                                 'items' (array of successful cancellations).
     */
    public function cancelHolds($cancelDetails)
    {
        $returnArray = [];
        $patronId = $cancelDetails['patron']['id'];
        $count = 0;

        foreach ($cancelDetails['details'] as $requestId) {
            $item = [];
            try {
                // Delete the request in Alma
                $apiResult = $this->makeRequest(
                    $this->baseUrl .
                    '/users/' . rawurlencode($patronId) .
                    '/requests/' . rawurlencode($requestId),
                    ['reason' => 'CancelledAtPatronRequest'],
                    [],
                    'DELETE'
                );

                // Adding to "count" variable and setting values to return array
                $count++;
                $item[$requestId] = [
                    'success' => true,
                    'status' => 'hold_cancel_success',
                ];
            } catch (ILSException $e) {
                if (isset($apiResult['xml'])) {
                    $almaErrorCode = $apiResult['xml']->errorList->error->errorCode;
                    $sysMessage = $apiResult['xml']->errorList->error->errorMessage;
                } else {
                    $almaErrorCode = 'No error code available';
                    $sysMessage = 'HTTP status code: ' .
                         ($e->getCode() ?? 'Code not available');
                }
                $item[$requestId] = [
                    'success' => false,
                    'status' => 'hold_cancel_fail',
                    'sysMessage' => $sysMessage . '. ' .
                         'Alma request ID: ' . $requestId . '. ' .
                         'Alma error code: ' . $almaErrorCode,
                ];
            }

            $returnArray['items'] = $item;
        }

        $returnArray['count'] = $count;

        return $returnArray;
    }

    /**
     * Update holds
     *
     * This is responsible for changing the status of hold requests
     *
     * @param array $holdsDetails The details identifying the holds
     * @param array $fields       An associative array of fields to be updated
     * @param array $patron       Patron array
     *
     * @return array Associative array of the results
     */
    public function updateHolds(
        array $holdsDetails,
        array $fields,
        array $patron
    ): array {
        $results = [];
        $patronId = $patron['id'];
        foreach ($holdsDetails as $requestId) {
            $requestUrl = $this->baseUrl . '/users/' . rawurlencode($patronId)
                . '/requests/' . rawurlencode($requestId);
            $requestDetails = $this->makeRequest($requestUrl);

            if (isset($fields['pickUpLocation'])) {
                $requestDetails->pickup_location_library = $fields['pickUpLocation'];
            }
            [$result, $status] = $this->makeRequest(
                $requestUrl,
                [],
                [],
                'PUT',
                $requestDetails->asXML(),
                ['Content-Type' => 'application/xml'],
                [400],
                true
            );
            if (200 != $status) {
                $error = $result->errorList->error[0]->errorMessage
                    ?? 'hold_error_fail';
                $results[$requestId] = [
                    'success' => false,
                    'status' => (string)$error,
                ];
            } else {
                $results[$requestId] = [
                    'success' => true,
                ];
            }
        }

        return $results;
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
            '/users/' . rawurlencode($patron['id']) . '/requests',
            ['request_type' => 'MOVE']
        );
        $holdList = [];
        for ($i = 0; $i < count($xml->user_requests); $i++) {
            $request = $xml->user_requests[$i];
            if (
                !isset($request->item_policy)
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
            '/users/' . rawurlencode($patron['id']) . '/requests',
            ['request_type' => 'MOVE']
        );
        $holdList = [];
        for ($i = 0; $i < count($xml->user_requests); $i++) {
            $request = $xml->user_requests[$i];
            if (
                !isset($request->item_policy)
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
     * @param array $params Parameters
     *
     * @return array Transaction information as array.
     *
     * @author Michael Birkner
     */
    public function getMyTransactions($patron, $params = [])
    {
        // Defining the return value
        $returnArray = [];

        // Get the patron id
        $patronId = $patron['id'];

        // Create a timestamp for calculating the due / overdue status
        $nowTS = time();

        $sort = explode(
            ' ',
            !empty($params['sort']) ? $params['sort'] : 'checkout desc',
            2
        );
        if ($sort[0] == 'checkout') {
            $sortKey = 'loan_date';
        } elseif ($sort[0] == 'title') {
            $sortKey = 'title';
        } else {
            $sortKey = 'due_date';
        }
        $direction = (isset($sort[1]) && 'desc' === $sort[1]) ? 'DESC' : 'ASC';

        $pageSize = $params['limit'] ?? 50;
        $params = [
            'limit' => $pageSize,
            'offset' => isset($params['page'])
                ? ($params['page'] - 1) * $pageSize : 0,
            'order_by' => $sortKey,
            'direction' => $direction,
            'expand' => 'renewable',
        ];

        // Get user loans from Alma API
        $apiResult = $this->makeRequest(
            '/users/' . rawurlencode($patronId) . '/loans',
            $params
        );

        // If there is an API result, process it
        $totalCount = 0;
        if ($apiResult) {
            $totalCount = $apiResult->attributes()->total_record_count;
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
                $loan['institution_name']
                    = $this->getTranslatableString($itemLoan->library);
                //$loan['isbn'] = ;
                //$loan['issn'] = ;
                //$loan['oclc'] = ;
                //$loan['upc'] = ;
                $loan['borrowingLocation']
                    = $this->getTranslatableString($itemLoan->circ_desk);

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

        return [
            'count' => $totalCount,
            'records' => $returnArray,
        ];
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
        $patronId = $renewDetails['patron']['id'];

        foreach ($renewDetails['details'] as $loanId) {
            // Create an empty array that holds the information for a renewal
            $renewal = [];

            try {
                // POST the renewals to Alma
                $apiResult = $this->makeRequest(
                    '/users/' . rawurlencode($patronId) . '/loans/'
                    . rawurlencode($loanId) . '/?op=renew',
                    [],
                    [],
                    'POST'
                );

                // Add information to the renewal array
                $renewal = [
                    'success' => true,
                    'new_date' => $this->parseDate(
                        (string)$apiResult->due_date,
                        true
                    ),
                    'item_id' => (string)$apiResult->loan_id,
                    'sysMessage' => 'renew_success',
                ];

                // Add the renewal to the return array
                $returnArray['details'][$loanId] = $renewal;
            } catch (ILSException $ilsEx) {
                // Add the empty renewal array to the return array
                $returnArray['details'][$loanId] = [
                    'success' => false,
                ];
            }
        }

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
        $idList = [$id];
        $status = $this->getStatuses($idList);
        return current($status);
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
        return $this->getStatusesForInventoryTypes($ids, $this->getInventoryTypes());
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
    public function getConfig($function, $params = [])
    {
        if ($function == 'patronLogin') {
            return [
                'loginMethod' => $this->config['Catalog']['loginMethod'] ?? 'vufind',
            ];
        }
        if (isset($this->config[$function])) {
            $functionConfig = $this->config[$function];

            // Set default value for "itemLimit" in Alma driver
            if ($function === 'Holdings') {
                // Use itemLimit in Holds as fallback for backward compatibility
                $functionConfig['itemLimit'] = ($functionConfig['itemLimit']
                    ?? $this->config['Holds']['itemLimit']
                    ?? 10) ?: 10;
            }
        } elseif ('getMyTransactions' === $function) {
            $functionConfig = [
                'max_results' => 100,
                'sort' => [
                    'checkout desc' => 'sort_checkout_date_desc',
                    'checkout asc' => 'sort_checkout_date_asc',
                    'due desc' => 'sort_due_date_desc',
                    'due asc' => 'sort_due_date_asc',
                    'title asc' => 'sort_title',
                ],
                'default_sort' => 'due asc',
            ];
        } else {
            $functionConfig = false;
        }

        return $functionConfig;
    }

    /**
     * Place a hold request via Alma API. This could be a title level request or
     * an item level request.
     *
     * @param array $holdDetails An associative array w/ at least patron and item_id
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
        // The holding_id value is deprecated but retained for back-compatibility
        $holId = $holdDetails['holdings_id'] ?? $holdDetails['holding_id'];
        $itmId = $holdDetails['item_id'];
        $patronId = $holdDetails['patron']['id'];
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
                $this->baseUrl . '/bibs/' . rawurlencode($mmsId)
                . '/requests?apikey=' . urlencode($this->apiKey)
                . '&user_id=' . urlencode($patronId)
                . '&format=json'
            );
        } else {
            // Create HTTP client with Alma API URL for item level requests
            $client = $this->httpService->createClient(
                $this->baseUrl . '/bibs/' . rawurlencode($mmsId)
                . '/holdings/' . rawurlencode($holId)
                . '/items/' . rawurlencode($itmId)
                . '/requests?apikey=' . urlencode($this->apiKey)
                . '&user_id=' . urlencode($patronId)
                . '&format=json'
            );
        }

        // Set headers
        $client->setHeaders(
            [
            'Content-type: application/json',
            'Accept: application/json',
            ]
        );

        // Set HTTP method
        $client->setMethod(\Laminas\Http\Request::METHOD_POST);

        // Set body
        $client->setRawBody(json_encode($body));

        // Send API call and get response
        $response = $client->send();

        // Check for success
        if ($response->isSuccess()) {
            return ['success' => true];
        } else {
            $url = $client->getRequest()->getUriString();
            $statusCode = $response->getStatusCode();
            $this->logError(
                "Alma error for hold POST request '$url' (status code $statusCode): "
                . $response->getBody()
            );
        }

        // Get error message
        $error = json_decode($response->getBody());
        if (!$error) {
            $error = simplexml_load_string($response->getBody());
        }

        return [
            'success' => false,
            'sysMessage' => $error->errorList->error[0]->errorMessage
                ?? 'hold_error_fail',
        ];
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
     * @return array An array of associative arrays with locationID and
     * locationDisplay keys
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getPickupLocations($patron, $holdDetails = null)
    {
        $xml = $this->makeRequest('/conf/libraries');
        $libraries = [];
        foreach ($xml as $library) {
            $libraries[] = [
                'locationID' => (string)$library->code,
                'locationDisplay' => (string)$library->name,
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
        $listsBase = '/courses/' . rawurlencode($courseID) . '/reading-lists';
        $xml = $this->makeRequest($listsBase);
        $reserves = [];
        foreach ($xml as $list) {
            $listId = $list->id;
            $listXML = $this->makeRequest(
                $listsBase . '/' . rawurlencode($listId) . '/citations'
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
        if (!str_contains($date, 'T') && str_ends_with($date, 'Z')) {
            $date = substr($date, 0, -1);
        }

        $compactDate = '/^[0-9]{8}$/'; // e. g. 20120725
        $euroName = "/^[0-9]+\/[A-Za-z]{3}\/[0-9]{4}$/"; // e. g. 13/jan/2012
        $euro = "/^[0-9]+\/[0-9]+\/[0-9]{4}$/"; // e. g. 13/7/2012
        $euroPad = "/^[0-9]{1,2}\/[0-9]{1,2}\/[0-9]{2,4}$/"; // e. g. 13/07/2012
        $datestamp = '/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/'; // e. g. 2012-07-13
        $timestamp = '/^[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}Z$/';
        $timestampMs
            = '/^[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}.[0-9]{3}Z$/';
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
        } elseif (preg_match($timestamp, $date) === 1) {
            if ($withTime) {
                return $this->dateConverter->convertToDisplayDateAndTime(
                    'Y-m-d\TH:i:sT',
                    $date
                );
            } else {
                return $this->dateConverter->convertToDisplayDate(
                    'Y-m-d',
                    substr($date, 0, 10)
                );
            }
        } elseif (preg_match($timestampMs, $date) === 1) {
            if ($withTime) {
                return $this->dateConverter->convertToDisplayDateAndTime(
                    'Y-m-d\TH:i:s#???T',
                    $date
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
        return is_callable([$this, $method]);
    }

    /**
     * Get the inventory types to be displayed. Possible values are:
     * p_avail,e_avail,d_avail
     *
     * @return array
     */
    protected function getInventoryTypes()
    {
        $types = explode(
            ':',
            $this->config['Holdings']['inventoryTypes']
                ?? 'physical:digital:electronic'
        );

        $result = [];
        $map = [
            'physical' => 'p_avail',
            'digital' => 'd_avail',
            'electronic' => 'e_avail',
        ];
        $types = array_flip($types);
        foreach ($map as $src => $dest) {
            if (isset($types[$src])) {
                $result[] = $dest;
            }
        }

        return $result;
    }

    /**
     * Get Statuses for inventory types
     *
     * This is responsible for retrieving the status information for a
     * collection of records with specified inventory types.
     *
     * @param array $ids   The array of record ids to retrieve the status for
     * @param array $types Inventory types
     *
     * @return array An array of getStatus() return values on success.
     */
    protected function getStatusesForInventoryTypes($ids, $types)
    {
        $results = [];
        $params = [
            'mms_id' => implode(',', $ids),
            'expand' => implode(',', $types),
        ];
        if ($bibs = $this->makeRequest('/bibs', $params)) {
            foreach ($bibs as $bib) {
                $marc = new MarcReader($bib->record->asXML());
                $status = [];
                $tmpl = [
                    'id' => (string)$bib->mms_id,
                    'source' => 'Solr',
                    'callnumber' => '',
                    'reserve' => 'N',
                ];
                // Physical
                $physicalItems = $marc->getFields('AVA');
                foreach ($physicalItems as $field) {
                    $available = null;
                    $statusText = '';
                    if ($this->locationTypeToItemStatus) {
                        $locationCode = $marc->getSubfield($field, 'j');
                        $library = $marc->getSubfield($field, 'b');
                        [$available, $statusText] = $this->getItemStatusFromLocationTypeMap(
                            $this->getLocationType($library, $locationCode)
                        );
                    }

                    if (null === $available) {
                        $availStr = strtolower($marc->getSubfield($field, 'e'));
                        $available = 'available' === $availStr;
                        // No status message available, so set it based on availability:
                        $statusText = $available ? 'Item in place' : 'Item not in place';
                    }

                    $item = $tmpl;
                    $item['availability'] = $available;
                    $item['status'] = $statusText;
                    $item['location'] = $marc->getSubfield($field, 'c');
                    $item['callnumber'] = $marc->getSubfield($field, 'd');
                    $status[] = $item;
                }
                // Electronic
                $electronicItems = $marc->getFields('AVE');
                foreach ($electronicItems as $field) {
                    $avail = $marc->getSubfield($field, 'e');
                    $item = $tmpl;
                    $item['availability'] = strtolower($avail) === 'available';
                    // Use the following subfields for location:
                    // m (Collection name)
                    // i (Available for library)
                    // d (Available for library)
                    // b (Available for library)
                    $location = [$marc->getSubfield($field, 'm') ?: 'Get full text'];
                    foreach (['i', 'd', 'b'] as $code) {
                        if ($content = $marc->getSubfield($field, $code)) {
                            $location[] = $content;
                        }
                    }
                    $item['location'] = implode(' - ', $location);
                    $item['callnumber'] = $marc->getSubfield($field, 't');
                    $url = $marc->getSubfield($field, 'u');
                    if (preg_match('/^https?:\/\//', $url)) {
                        $item['locationhref'] = $url;
                    }
                    $item['status'] = $marc->getSubfield($field, 's') ?: null;
                    if ($note = $marc->getSubfield($field, 'n')) {
                        $item['item_notes'] = [$note];
                    }
                    $status[] = $item;
                }
                // Digital
                $deliveryUrl
                    = $this->config['Holdings']['digitalDeliveryUrl'] ?? '';
                $digitalItems = $marc->getFields('AVD');
                if ($digitalItems && !$deliveryUrl) {
                    $this->logWarning(
                        'Digital items exist for ' . (string)$bib->mms_id
                        . ', but digitalDeliveryUrl not set -- unable to'
                        . ' generate links'
                    );
                }
                foreach ($digitalItems as $field) {
                    $item = $tmpl;
                    unset($item['callnumber']);
                    $item['availability'] = true;
                    $item['location'] = $marc->getSubfield($field, 'e');
                    // Using subfield 'd' ('Repository Name') as callnumber
                    $item['callnumber'] = $marc->getSubfield($field, 'd');
                    if ($deliveryUrl) {
                        $item['locationhref'] = str_replace(
                            '%%id%%',
                            $marc->getSubfield($field, 'b'),
                            $deliveryUrl
                        );
                    }
                    $status[] = $item;
                }
                $results[(string)$bib->mms_id] = $status;
            }
        }
        return $results;
    }

    /**
     * Get the preferred email address for the user (or first one if no preferred one
     * is found)
     *
     * @param SimpleXMLElement $user User data
     *
     * @return string|null
     */
    protected function getPreferredEmail($user)
    {
        if (!empty($user->contact_info->emails->email)) {
            foreach ($user->contact_info->emails->email as $email) {
                if ('true' === (string)$email['preferred']) {
                    return isset($email->email_address)
                        ? trim((string)$email->email_address) : null;
                }
            }
            $email = $user->contact_info->emails->email[0];
            return isset($email->email_address)
                ? (string)$email->email_address : null;
        }
        return null;
    }

    /**
     * Gets a translatable string from an element with content and a desc attribute.
     *
     * @param SimpleXMLElement $element XML element
     *
     * @return \VuFind\I18n\TranslatableString
     */
    protected function getTranslatableString($element)
    {
        if (null === $element) {
            return null;
        }
        $value = ($this->config['Catalog']['translationPrefix'] ?? '')
            . (string)$element;
        $desc = (string)($element->attributes()->desc ?? $element);
        return new TranslatableString($value, $desc);
    }

    /**
     * Gets a translatable string from an element with content and a desc attribute.
     *
     * @param SimpleXMLElement $element XML element
     *
     * @return TranslatableString
     */
    protected function getTranslatableStatusString($element)
    {
        if (null === $element) {
            return null;
        }
        $value = 'status_' . strtolower((string)$element);
        $desc = (string)($element->attributes()->desc ?? $element);
        return new TranslatableString($value, $desc);
    }

    /**
     * Get location for an item
     *
     * @param SimpleXMLElement $item Item
     *
     * @return TranslatableString|string
     */
    protected function getItemLocation($item)
    {
        return $this->getTranslatableString($item->item_data->location);
    }

    /**
     * Get location type for an item
     *
     * @param SimpleXMLElement $item Item
     *
     * @return string
     */
    protected function getItemLocationType($item)
    {
        // Yes, temporary location is in holding data while permanent location is in
        // item data.
        if ('true' === (string)$item->holding_data->in_temp_location) {
            $library = $item->holding_data->temp_library
                ?: $item->item_data->library;
            $location = $item->holding_data->temp_location
                ?: $item->item_data->location;
        } else {
            $library = $item->item_data->library;
            $location = $item->item_data->location;
        }
        return $this->getLocationType((string)$library, (string)$location);
    }

    /**
     * Get type of a location
     *
     * @param string $library  Library
     * @param string $location Location
     *
     * @return string
     */
    protected function getLocationType($library, $location)
    {
        $locations = $this->getLocations($library);
        return $locations[$location]['type'] ?? '';
    }

    /**
     * Get the locations for a library
     *
     * @param string $library Library
     *
     * @return array
     */
    protected function getLocations($library)
    {
        $cacheId = 'alma|locations|' . $library;
        $locations = $this->getCachedData($cacheId);

        if (null === $locations) {
            $xml = $this->makeRequest(
                '/conf/libraries/' . rawurlencode($library) . '/locations'
            );
            $locations = [];
            foreach ($xml as $entry) {
                $locations[(string)$entry->code] = [
                    'name' => (string)$entry->name,
                    'externalName' => (string)$entry->external_name,
                    'type' => (string)$entry->type,
                ];
            }
            $this->putCachedData($cacheId, $locations, 3600);
        }
        return $locations;
    }

    /**
     * Get list of funds
     *
     * @return array with key = course ID, value = course name
     */
    public function getFunds()
    {
        // TODO: implement me!
        // https://developers.exlibrisgroup.com/alma/apis/acq
        // GET /almaws/v1/acq/funds
        return [];
    }
}
