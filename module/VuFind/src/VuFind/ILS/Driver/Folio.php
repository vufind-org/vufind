<?php
/**
 * FOLIO REST API driver
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2018-2023.
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
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
namespace VuFind\ILS\Driver;

use DateTime;
use DateTimeZone;
use Exception;
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

    use \VuFind\Cache\CacheTrait {
        getCacheKey as protected getBaseCacheKey;
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
     * @var callable
     */
    protected $sessionFactory;

    /**
     * Session cache
     *
     * @var \Laminas\Session\Container
     */
    protected $sessionCache;

    /**
     * Date converter
     *
     * @var \VuFind\Date\Converter
     */
    protected $dateConverter;

    /**
     * Default availability messages, in case they are not defined in Folio.ini
     *
     * @var string[]
     */
    protected $defaultAvailabilityStatuses = ['Open - Awaiting pickup'];

    /**
     * Default in_transit messages, in case they are not defined in Folio.ini
     *
     * @var string[]
     */
    protected $defaultInTransitStatuses = [
        'Open - In transit',
        'Open - Awaiting delivery'
    ];

    /**
     * Constructor
     *
     * @param \VuFind\Date\Converter $dateConverter  Date converter object
     * @param callable               $sessionFactory Factory function returning
     * SessionContainer object
     */
    public function __construct(
        \VuFind\Date\Converter $dateConverter,
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
     * Get the type of FOLIO ID used to match up with VuFind's bib IDs.
     *
     * @return string
     */
    protected function getBibIdType()
    {
        // Normalize string to tolerate minor variations in config file:
        return trim(strtolower($this->config['IDs']['type'] ?? 'instance'));
    }

    /**
     * Function that obscures and logs debug data
     *
     * @param string                $method      Request method
     * (GET/POST/PUT/DELETE/etc.)
     * @param string                $path        Request URL
     * @param array                 $params      Request parameters
     * @param \Laminas\Http\Headers $req_headers Headers object
     *
     * @return void
     */
    protected function debugRequest($method, $path, $params, $req_headers)
    {
        // Only log non-GET requests, unless configured otherwise
        if ($method == 'GET'
            && !($this->config['API']['debug_get_requests'] ?? false)
        ) {
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
            $logHeaders['X-Okapi-Token'] = substr(
                $logHeaders['X-Okapi-Token'],
                0,
                30
            ) . '...';
        }

        $this->debug(
            $method . ' request.' .
            ' URL: ' . $path . '.' .
            ' Params: ' . print_r($logParams, true) . '.' .
            ' Headers: ' . print_r($logHeaders, true)
        );
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
        // Override the base class formatting with FOLIO-specific details
        // to ensure proper caching in a MultiBackend environment.
        return 'FOLIO-'
            . md5("{$this->tenant}|$key");
    }

    /**
     * (From AbstractAPI) Allow default corrections to all requests
     *
     * Add X-Okapi headers and Content-Type to every request
     *
     * @param \Laminas\Http\Headers $headers the request headers
     * @param object                $params  the parameters object
     *
     * @return array
     */
    public function preRequest(\Laminas\Http\Headers $headers, $params)
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
        $response = $this->makeRequest('GET', '/users', [], [], [401, 403]);
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
     * Given some kind of identifier (instance, holding or item), retrieve the
     * associated instance object from FOLIO.
     *
     * @param string $instanceId Instance ID, if available.
     * @param string $holdingId  Holding ID, if available.
     * @param string $itemId     Item ID, if available.
     *
     * @return object
     */
    protected function getInstanceById(
        $instanceId = null,
        $holdingId = null,
        $itemId = null
    ) {
        if ($instanceId == null) {
            if ($holdingId == null) {
                if ($itemId == null) {
                    throw new \Exception('No IDs provided to getInstanceObject.');
                }
                $response = $this->makeRequest(
                    'GET',
                    '/item-storage/items/' . $itemId
                );
                $item = json_decode($response->getBody());
                $holdingId = $item->holdingsRecordId;
            }
            $response = $this->makeRequest(
                'GET',
                '/holdings-storage/holdings/' . $holdingId
            );
            $holding = json_decode($response->getBody());
            $instanceId = $holding->instanceId;
        }
        $response = $this->makeRequest(
            'GET',
            '/inventory/instances/' . $instanceId
        );
        return json_decode($response->getBody());
    }

    /**
     * Given an instance object or identifer, or a holding or item identifier,
     * determine an appropriate value to use as VuFind's bibliographic ID.
     *
     * @param string $instanceOrInstanceId Instance object or ID (will be looked up
     * using holding or item ID if not provided)
     * @param string $holdingId            Holding-level id (optional)
     * @param string $itemId               Item-level id (optional)
     *
     * @return string Appropriate bib id retrieved from FOLIO identifiers
     */
    protected function getBibId(
        $instanceOrInstanceId = null,
        $holdingId = null,
        $itemId = null
    ) {
        $idType = $this->getBibIdType();

        // Special case: if we're using instance IDs and we already have one,
        // short-circuit the lookup process:
        if ($idType === 'instance' && is_string($instanceOrInstanceId)) {
            return $instanceOrInstanceId;
        }

        $instance = is_object($instanceOrInstanceId)
            ? $instanceOrInstanceId
            : $this->getInstanceById($instanceOrInstanceId, $holdingId, $itemId);

        switch ($idType) {
        case 'hrid':
            return $instance->hrid;
        case 'instance':
            return $instance->id;
        }

        throw new \Exception('Unsupported ID type: ' . $idType);
    }

    /**
     * Escape a string for use in a CQL query.
     *
     * @param string $in Input string
     *
     * @return string
     */
    protected function escapeCql($in)
    {
        return str_replace('"', '\"', str_replace('&', '%26', $in));
    }

    /**
     * Retrieve FOLIO instance using VuFind's chosen bibliographic identifier.
     *
     * @param string $bibId Bib-level id
     *
     * @return array
     */
    protected function getInstanceByBibId($bibId)
    {
        // Figure out which ID type to use in the CQL query; if the user configured
        // instance IDs, use the 'id' field, otherwise pass the setting through
        // directly:
        $idType = $this->getBibIdType();
        $idField = $idType === 'instance' ? 'id' : $idType;

        $query = [
            'query' => '(' . $idField . '=="' . $this->escapeCql($bibId) . '")'
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
    public function getConfig($function, $params = [])
    {
        return $this->config[$function] ?? false;
    }

    /**
     * Check item location against list of configured locations
     * where holds should be offered
     *
     * @param string $locationName locationName from getHolding
     *
     * @return bool
     */
    protected function isHoldable($locationName)
    {
        $mode = $this->config['Holds']['excludeHoldLocationsCompareMode'] ?? 'exact';
        $excludeLocs = (array)($this->config['Holds']['excludeHoldLocations'] ?? []);

        // Exclude checking by regex match
        if (trim(strtolower($mode)) == "regex") {
            foreach ($excludeLocs as $pattern) {
                $match = @preg_match($pattern, $locationName);
                // Invalid regex, skip this pattern
                if ($match === false) {
                    $this->logWarning(
                        'Invalid regex found in excludeHoldLocations: ' .
                        $pattern
                    );
                    continue;
                }
                if ($match === 1) {
                    return false;
                }
            }
            return true;
        }
        // Otherwise exclude checking by exact match
        return !in_array($locationName, $excludeLocs);
    }

    /**
     * Gets locations from the /locations endpoint and sets
     * an array of location IDs to display names.
     * Display names are set from discoveryDisplayName, or name
     * if discoveryDisplayName is not available.
     *
     * @return array
     */
    protected function getLocations()
    {
        $cacheKey = 'locationMap';
        $locationMap = $this->getCachedData($cacheKey);
        if (null === $locationMap) {
            $locationMap = [];
            foreach ($this->getPagedResults(
                'locations',
                '/locations'
            ) as $location) {
                $name = $location->discoveryDisplayName ?? $location->name;
                $code = $location->code;
                $locationMap[$location->id] = compact('name', 'code');
            }
        }
        $this->putCachedData($cacheKey, $locationMap);
        return $locationMap;
    }

    /**
     * Get Inventory Location Name
     *
     * @param string $locationId UUID of item location
     *
     * @return array with the display name and code of location
     */
    protected function getLocationData($locationId)
    {
        $locationMap = $this->getLocations();
        $name = '';
        $code = '';
        if (array_key_exists($locationId, $locationMap)) {
            return $locationMap[$locationId];
        } else {
            // if key is not found in cache, the location could have
            // been added before the cache expired so check again
            $locationResponse = $this->makeRequest(
                'GET',
                '/locations/' . $locationId
            );
            if ($locationResponse->isSuccess()) {
                $location = json_decode($locationResponse->getBody());
                $name = $location->discoveryDisplayName ?? $location->name;
                $code = $location->code;
            }
        }

        return compact('name', 'code');
    }

    /**
     * Choose a call number and callnumber prefix.
     *
     * @param string $hCallNumP Holding-level call number prefix
     * @param string $hCallNum  Holding-level call number
     * @param string $iCallNumP Item-level call number prefix
     * @param string $iCallNum  Item-level call number
     *
     * @return array with call number and call number prefix.
     */
    protected function chooseCallNumber($hCallNumP, $hCallNum, $iCallNumP, $iCallNum)
    {
        if (empty($iCallNum)) {
            return ['callnumber_prefix' => $hCallNumP, 'callnumber' => $hCallNum];
        }
        return ['callnumber_prefix' => $iCallNumP, 'callnumber' => $iCallNum];
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
        $showDueDate = $this->config['Availability']['showDueDate'] ?? true;
        $showTime = $this->config['Availability']['showTime'] ?? false;
        $maxNumDueDateItems = $this->config['Availability']['maxNumberItems'] ?? 5;
        $dueDateItemCount = 0;

        $instance = $this->getInstanceByBibId($bibId);
        $query = [
            'query' => '(instanceId=="' . $instance->id
                . '" NOT discoverySuppress==true)'
        ];
        $items = [];
        foreach ($this->getPagedResults(
            'holdingsRecords',
            '/holdings-storage/holdings',
            $query
        ) as $holding) {
            $query = [
                'query' => '(holdingsRecordId=="' . $holding->id
                    . '" NOT discoverySuppress==true)'
            ];
            $notesFormatter = function ($note) {
                return !($note->staffOnly ?? false)
                    && !empty($note->note) ? $note->note : '';
            };
            $textFormatter = function ($supplement) {
                $format = '%s %s';
                $supStat = $supplement->statement ?? '';
                $supNote = $supplement->note ?? '';
                $statement = trim(sprintf($format, $supStat, $supNote));
                return $statement;
            };
            $holdingNotes = array_filter(
                array_map($notesFormatter, $holding->notes ?? [])
            );
            $hasHoldingNotes = !empty(implode($holdingNotes));
            $holdingsStatements = array_map(
                $textFormatter,
                $holding->holdingsStatements ?? []
            );
            $holdingsSupplements = array_map(
                $textFormatter,
                $holding->holdingsStatementsForSupplements ?? []
            );
            $holdingsIndexes = array_map(
                $textFormatter,
                $holding->holdingsStatementsForIndexes ?? []
            );
            $holdingCallNumber = $holding->callNumber ?? '';
            $holdingCallNumberPrefix = $holding->callNumberPrefix ?? '';
            foreach ($this->getPagedResults(
                'items',
                '/item-storage/items',
                $query
            ) as $item) {
                $itemNotes = array_filter(
                    array_map($notesFormatter, $item->notes ?? [])
                );
                $locationId = $item->effectiveLocationId;
                $locationData = $this->getLocationData($locationId);
                $locationName = $locationData['name'];
                $locationCode = $locationData['code'];
                // concatenate enumeration fields if present
                $enum = implode(
                    ' ',
                    array_filter(
                        [
                            $item->volume ?? null,
                            $item->enumeration ?? null,
                            $item->chronology ?? null
                        ]
                    )
                );
                $callNumberData = $this->chooseCallNumber(
                    $holdingCallNumberPrefix,
                    $holdingCallNumber,
                    $item->effectiveCallNumberComponents->prefix
                        ?? $item->itemLevelCallNumberPrefix ?? '',
                    $item->effectiveCallNumberComponents->callNumber
                        ?? $item->itemLevelCallNumber ?? ''
                );

                $dueDateValue = '';
                if ($item->status->name == 'Checked out'
                    && $showDueDate
                    && $dueDateItemCount < $maxNumDueDateItems
                ) {
                    $dueDateValue = $this->getDueDate($item->id, $showTime);
                    $dueDateItemCount++;
                }

                $items[] = $callNumberData + [
                    'id' => $bibId,
                    'item_id' => $item->id,
                    'holding_id' => $holding->id,
                    'number' => count($items) + 1,
                    'enumchron' => $enum,
                    'barcode' => $item->barcode ?? '',
                    'status' => $item->status->name,
                    'duedate' => $dueDateValue,
                    'availability' => $item->status->name == 'Available',
                    'is_holdable' => $this->isHoldable($locationName),
                    'holdings_notes'=> $hasHoldingNotes ? $holdingNotes : null,
                    'item_notes' => !empty(implode($itemNotes)) ? $itemNotes : null,
                    'issues' => $holdingsStatements,
                    'supplements' => $holdingsSupplements,
                    'indexes' => $holdingsIndexes,
                    'location' => $locationName,
                    'location_code' => $locationCode,
                    'reserve' => 'TODO',
                    'addLink' => true
                ];
            }
        }
        return $items;
    }

    /**
     * Convert a FOLIO date string to a DateTime object.
     *
     * @param string $str FOLIO date string
     *
     * @return DateTime
     */
    protected function getDateTimeFromString(string $str): DateTime
    {
        $dateTime = new DateTime($str, new DateTimeZone('UTC'));
        $localTimezone = (new DateTime)->getTimezone();
        $dateTime->setTimezone($localTimezone);
        return $dateTime;
    }

    /**
     * Support method for getHolding(): obtaining the Due Date from OKAPI
     * by calling /circulation/loans with the item->id, adjusting the
     * timezone and formatting in universal time with or without due time
     *
     * @param string $itemId   ID for the item to query
     * @param bool   $showTime Determines if date or date & time is returned
     *
     * @return string
     */
    protected function getDueDate($itemId, $showTime)
    {
        $query = 'itemId==' . $itemId;
        foreach ($this->getPagedResults(
            'loans',
            '/circulation/loans',
            compact('query')
        ) as $loan) {
            // many loans are returned for an item, the one we want
            // is the one without a returnDate
            if (!isset($loan->returnDate) && isset($loan->dueDate)) {
                $dueDate = $this->getDateTimeFromString($loan->dueDate);
                $method = $showTime
                    ? 'convertToDisplayDateAndTime' : 'convertToDisplayDate';
                return $this->dateConverter->$method('U', $dueDate->format('U'));
            }
        }
        return '';
    }

    /**
     * Support method for patronLogin(): authenticate the patron with an Okapi
     * login attempt. Returns a CQL query for retrieving more information about
     * the authenticated user.
     *
     * @param string $username The patron username
     * @param string $password The patron password
     *
     * @return string
     */
    protected function patronLoginWithOkapi($username, $password)
    {
        $tenant = $this->config['API']['tenant'];
        $credentials = compact('tenant', 'username', 'password');
        // Get token
        $response = $this->makeRequest(
            'POST',
            '/authn/login',
            json_encode($credentials)
        );
        $debugMsg = 'User logged in. User: ' . $username . '.';
        // We've authenticated the user with Okapi, but we only have their
        // username; set up a query to retrieve full info below.
        $query = 'username == ' . $username;
        // Replace admin with user as tenant if configured to do so:
        if ($this->config['User']['use_user_token'] ?? false) {
            $this->token = $response->getHeaders()->get('X-Okapi-Token')
                ->getFieldValue();
            $debugMsg .= ' Token: ' . substr($this->token, 0, 30) . '...';
        }
        $this->debug($debugMsg);
        return $query;
    }

    /**
     * Support method for patronLogin(): authenticate the patron with a CQL looup.
     * Returns the CQL query for retrieving more information about the user.
     *
     * @param string $username The patron username
     * @param string $password The patron password
     *
     * @return string
     */
    protected function getUserWithCql($username, $password)
    {
        // Construct user query using barcode, username, etc.
        $usernameField = $this->config['User']['username_field'] ?? 'username';
        $passwordField = $this->config['User']['password_field'] ?? false;
        $cql = $this->config['User']['cql']
            ?? '%%username_field%% == "%%username%%"'
            . ($passwordField ? ' and %%password_field%% == "%%password%%"' : '');
        $placeholders = [
            '%%username_field%%',
            '%%password_field%%',
            '%%username%%',
            '%%password%%',
        ];
        $values = [
            $usernameField,
            $passwordField,
            $this->escapeCql($username),
            $this->escapeCql($password),
        ];
        return str_replace($placeholders, $values, $cql);
    }

    /**
     * Given a CQL query, fetch a single user; if we get an unexpected count, treat
     * that as an unsuccessful login by returning null.
     *
     * @param string $query CQL query
     *
     * @return object
     */
    protected function fetchUserWithCql($query)
    {
        $response = $this->makeRequest('GET', '/users', compact('query'));
        $json = json_decode($response->getBody());
        return count($json->users ?? []) === 1 ? $json->users[0] : null;
    }

    /**
     * Helper function to retrieve paged results from FOLIO API
     *
     * @param string $responseKey Key containing values to collect in response
     * @param string $interface   FOLIO api interface to call
     * @param array  $query       CQL query
     *
     * @return array
     */
    protected function getPagedResults($responseKey, $interface, $query = [])
    {
        $count = 0;
        $limit = 1000;
        $offset = 0;

        do {
            $combinedQuery = array_merge($query, compact('offset', 'limit'));
            $response = $this->makeRequest(
                'GET',
                $interface,
                $combinedQuery
            );
            $json = json_decode($response->getBody());
            if (!$response->isSuccess() || !$json) {
                $msg = $json->errors[0]->message ?? json_last_error_msg();
                throw new ILSException($msg);
            }
            $total = $json->totalRecords ?? 0;
            $previousCount = $count;
            foreach ($json->$responseKey ?? [] as $item) {
                $count++;
                if ($count % $limit == 0) {
                    $offset += $limit;
                }
                yield $item ?? '';
            }
            // Continue until the count reaches the total records
            // found, if count does not increase, something has gone
            // wrong. Stop so we don't loop forever.
        } while ($count < $total && $previousCount != $count);
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
     * null on unsuccessful login.
     */
    public function patronLogin($username, $password)
    {
        $profile = null;
        $doOkapiLogin = $this->config['User']['okapi_login'] ?? false;
        $usernameField = $this->config['User']['username_field'] ?? 'username';

        // If the username field is not the default 'username' we will need to
        // do a lookup to find the correct username value for Okapi login. We also
        // need to do this lookup if we're skipping Okapi login entirely.
        if (!$doOkapiLogin || $usernameField !== 'username') {
            $query = $this->getUserWithCql($username, $password);
            $profile = $this->fetchUserWithCql($query);
            if ($profile === null) {
                return null;
            }
        }

        // If we need to do an Okapi login, we have the information we need to do
        // it at this point.
        if ($doOkapiLogin) {
            try {
                // If we fetched the profile earlier, we want to use the username
                // from there; otherwise, we'll use the passed-in version.
                $query = $this->patronLoginWithOkapi(
                    $profile->username ?? $username,
                    $password
                );
            } catch (Exception $e) {
                return null;
            }
            // If we didn't load a profile earlier, we should do so now:
            if (!isset($profile)) {
                $profile = $this->fetchUserWithCql($query);
                if ($profile === null) {
                    return null;
                }
            }
        }

        return [
            'id' => $profile->id,
            'username' => $username,
            'cat_username' => $username,
            'cat_password' => $password,
            'firstname' => $profile->personal->firstName ?? null,
            'lastname' => $profile->personal->lastName ?? null,
            'email' => $profile->personal->email ?? null,
        ];
    }

    /**
     * Given a user UUID, return the user's profile object (null if not found).
     *
     * @param string $id User UUID
     *
     * @return ?object
     */
    protected function getUserById(string $id): ?object
    {
        $query = ['query' => 'id == "' . $id . '"'];
        $response = $this->makeRequest('GET', '/users', $query);
        $users = json_decode($response->getBody());
        return $users->users[0] ?? null;
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
        $profile = $this->getUserById($patron['id']);
        $expiration = isset($profile->expirationDate)
            ? $this->dateConverter->convertToDisplayDate(
                "Y-m-d H:i",
                $profile->expirationDate
            )
            : null;
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
            'expiration_date' => $expiration,
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
        $query = ['query' => 'userId==' . $patron['id'] . ' and status.name==Open'];
        $transactions = [];
        foreach ($this->getPagedResults(
            'loans',
            '/circulation/loans',
            $query
        ) as $trans) {
            $dueStatus = false;
            $date = $this->getDateTimeFromString($trans->dueDate);
            $dueDateTimestamp = $date->getTimestamp();

            $now = time();
            if ($now > $dueDateTimestamp) {
                $dueStatus = 'overdue';
            } elseif ($now > $dueDateTimestamp - (1 * 24 * 60 * 60)) {
                $dueStatus = 'due';
            }
            $transactions[] = [
                'duedate' =>
                    $this->dateConverter->convertToDisplayDate(
                        'U',
                        $dueDateTimestamp
                    ),
                'dueTime' =>
                    $this->dateConverter->convertToDisplayTime(
                        'U',
                        $dueDateTimestamp
                    ),
                'dueStatus' => $dueStatus,
                'id' => $this->getBibId($trans->item->instanceId),
                'item_id' => $trans->item->id,
                'barcode' => $trans->item->barcode,
                'renew' => $trans->renewalCount ?? 0,
                'renewable' => true,
                'title' => $trans->item->title,
            ];
        }
        return $transactions;
    }

    /**
     * Get FOLIO loan IDs for use in renewMyItems.
     *
     * @param array $transaction An single transaction
     * array from getMyTransactions
     *
     * @return string The FOLIO loan ID for this loan
     */
    public function getRenewDetails($transaction)
    {
        return $transaction['item_id'];
    }

    /**
     * Attempt to renew a list of items for a given patron.
     *
     * @param array $renewDetails An associative array with
     * patron and details
     *
     * @return array $renewResult result of attempt to renew loans
     */
    public function renewMyItems($renewDetails)
    {
        $renewalResults = ['details' => []];
        foreach ($renewDetails['details'] ?? [] as $loanId) {
            $requestbody = [
                'itemId' => $loanId,
                'userId' => $renewDetails['patron']['id']
            ];
            try {
                $response = $this->makeRequest(
                    'POST',
                    '/circulation/renew-by-id',
                    json_encode($requestbody),
                    [],
                    true
                );
                if ($response->isSuccess()) {
                    $json = json_decode($response->getBody());
                    $renewal = [
                        'success' => true,
                        'new_date' => $this->dateConverter->convertToDisplayDate(
                            "Y-m-d H:i",
                            $json->dueDate
                        ),
                        'new_time' => $this->dateConverter->convertToDisplayTime(
                            "Y-m-d H:i",
                            $json->dueDate
                        ),
                        'item_id' => $json->itemId,
                        'sysMessage' => $json->action
                    ];
                } else {
                    $json = json_decode($response->getBody());
                    $sysMessage = $json->errors[0]->message;
                    $renewal = [
                        'success' => false,
                        'sysMessage' => $sysMessage
                    ];
                }
            } catch (Exception $e) {
                $this->debug(
                    "Unexpected exception renewing $loanId: " . $e->getMessage()
                );
                $renewal = [
                    'success' => false,
                    'sysMessage' => "Renewal Failed",
                ];
            }
            $renewalResults['details'][$loanId] = $renewal;
        }
        return $renewalResults;
    }

    /**
     * Get Pick Up Locations
     *
     * This is responsible get a list of valid locations for holds / recall
     * retrieval
     *
     * @param array $patron   Patron information returned by $this->patronLogin
     * @param array $holdInfo Optional array, only passed in when getting a list
     * in the context of placing or editing a hold.  When placing a hold, it contains
     * most of the same values passed to placeHold, minus the patron data.  When
     * editing a hold it contains all the hold information returned by getMyHolds.
     * May be used to limit the pickup options or may be ignored.  The driver must
     * not add new options to the return array based on this data or other areas of
     * VuFind may behave incorrectly.
     *
     * @return array An array of associative arrays with locationID and
     * locationDisplay keys
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getPickupLocations($patron, $holdInfo = null)
    {
        $query = ['query' => 'pickupLocation=true'];
        $locations = [];
        foreach ($this->getPagedResults(
            'servicepoints',
            '/service-points',
            $query
        ) as $servicepoint) {
            $locations[] = [
                'locationID' => $servicepoint->id,
                'locationDisplay' => $servicepoint->discoveryDisplayName
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
        $userQuery = '(requesterId == "' . $patron['id'] . '"'
            . 'or proxyUserId == "' . $patron['id'] . '")';
        $query = ['query' => '(' . $userQuery . ' and status == Open*)'];
        $holds = [];
        foreach ($this->getPagedResults(
            'requests',
            '/request-storage/requests',
            $query
        ) as $hold) {
            $requestDate = $this->dateConverter->convertToDisplayDate(
                "Y-m-d H:i",
                $hold->requestDate
            );
            // Set expire date if it was included in the response
            $expireDate = isset($hold->requestExpirationDate)
                ? $this->dateConverter->convertToDisplayDate(
                    "Y-m-d H:i",
                    $hold->requestExpirationDate
                )
                : null;
            // Set lastPickup Date if provided, format to j M Y
            $lastPickup = isset($hold->holdShelfExpirationDate)
                ? $this->dateConverter->convertToDisplayDate(
                    "Y-m-d H:i",
                    $hold->holdShelfExpirationDate
                )
                : null;
            $currentHold = [
                'type' => $hold->requestType,
                'create' => $requestDate,
                'expire' => $expireDate ?? "",
                'id' => $this->getBibId(
                    $hold->instanceId,
                    $hold->holdingsRecordId,
                    $hold->itemId
                ),
                'item_id' => $hold->itemId,
                'reqnum' => $hold->id,
                // Title moved from item to instance in Lotus release:
                'title' => $hold->instance->title ?? $hold->item->title ?? '',
                'available' => in_array(
                    $hold->status,
                    $this->config['Holds']['available']
                    ?? $this->defaultAvailabilityStatuses
                ),
                'in_transit' => in_array(
                    $hold->status,
                    $this->config['Holds']['in_transit']
                    ?? $this->defaultInTransitStatuses
                ),
                'last_pickup_date' => $lastPickup,
                'position' => $hold->position ?? null,
            ];
            // If this request was created by a proxy user, and the proxy user
            // is not the current user, we need to indicate their name.
            if (($hold->proxyUserId ?? $patron['id']) !== $patron['id']
                && isset($hold->proxy)
            ) {
                $currentHold['proxiedBy']
                    = $this->userObjectToNameString($hold->proxy);
            }
            // If this request was not created for the current user, it must be
            // a proxy request created by the current user. We should indicate this.
            if (($hold->requesterId ?? $patron['id']) !== $patron['id']
                && isset($hold->requester)
            ) {
                $currentHold['proxiedFor']
                    = $this->userObjectToNameString($hold->requester);
            }
            $holds[] = $currentHold;
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
        $default_request = $this->config['Holds']['default_request'] ?? 'Hold';
        try {
            $requiredBy = $this->dateConverter->convertFromDisplayDate(
                'Y-m-d',
                $holdDetails['requiredBy']
            );
        } catch (Exception $e) {
            $this->throwAsIlsException($e, 'hold_date_invalid');
        }
        $isTitleLevel = ($holdDetails['level'] ?? '') === 'title';
        if ($isTitleLevel) {
            $instance = $this->getInstanceByBibId($holdDetails['id']);
            $baseParams = [
                'instanceId' => $instance->id,
                'requestLevel' => 'Title'
            ];
        } else {
            // Note: early Lotus releases require instanceId and holdingsRecordId
            // to be set here as well, but the requirement was lifted in a hotfix
            // to allow backward compatibility. If you need compatibility with one
            // of those versions, you can add additional identifiers here, but
            // applying the latest hotfix is a better solution!
            $baseParams = ['itemId' => $holdDetails['item_id']];
        }
        $requestBody = $baseParams + [
            'requestType' => $holdDetails['status'] == 'Available'
                ? 'Page' : $default_request,
            'requesterId' => $holdDetails['patron']['id'],
            'requestDate' => date('c'),
            'fulfilmentPreference' => 'Hold Shelf',
            'requestExpirationDate' => $requiredBy,
            'pickupServicePointId' => $holdDetails['pickUpLocation']
        ];
        if (!empty($holdDetails['proxiedUser'])) {
            $requestBody['requesterId'] = $holdDetails['proxiedUser'];
            $requestBody['proxyUserId'] = $holdDetails['patron']['id'];
        }
        if (!empty($holdDetails['comment'])) {
            $requestBody['patronComments'] = $holdDetails['comment'];
        }
        $response = $this->makeRequest(
            'POST',
            '/circulation/requests',
            json_encode($requestBody),
            [],
            true
        );
        if ($response->isSuccess()) {
            $json = json_decode($response->getBody());
            $result = [
                'success' => true,
                'status' => $json->status
            ];
        } else {
            try {
                $json = json_decode($response->getBody());
                $result = [
                    'success' => false,
                    'status' => $json->errors[0]->message
                ];
            } catch (Exception $e) {
                $this->throwAsIlsException($e, $response->getBody());
            }
        }
        return $result;
    }

    /**
     * Get FOLIO hold IDs for use in cancelHolds.
     *
     * @param array $hold   A single hold array from getMyHolds
     * @param array $patron Patron information from patronLogin
     *
     * @return string request ID for this request
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getCancelHoldDetails($hold, $patron = [])
    {
        return $hold['reqnum'];
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
        $cancelResult = ['items' => []];

        foreach ($details as $requestId) {
            $response = $this->makeRequest(
                'GET',
                '/circulation/requests/' . $requestId
            );
            $request_json = json_decode($response->getBody());

            // confirm request belongs to signed in patron
            if ($request_json->requesterId != $patron['id']
                && ($request_json->proxyUserId ?? null) != $patron['id']
            ) {
                throw new ILSException("Invalid Request");
            }
            // Change status to Closed and add cancellationID
            $request_json->status = 'Closed - Cancelled';
            $request_json->cancellationReasonId
                = $this->config['Holds']['cancellation_reason']
                ?? '75187e8d-e25a-47a7-89ad-23ba612338de';
            $success = false;
            try {
                $cancel_response = $this->makeRequest(
                    'PUT',
                    '/circulation/requests/' . $requestId,
                    json_encode($request_json),
                    [],
                    true
                );
                $success = $cancel_response->getStatusCode() === 204;
            } catch (\Exception $e) {
                // Do nothing; the $success flag is already false by default.
            }
            $count += $success ? 1 : 0;
            $cancelResult['items'][$request_json->itemId] = [
                'success' => $success,
                'status' => $success ? 'hold_cancel_success' : 'hold_cancel_fail',
            ];
        }
        $cancelResult['count'] = $count;
        return $cancelResult;
    }

    /**
     * Obtain a list of course resources, creating an id => value associative array.
     *
     * @param string       $type        Type of resource to retrieve from the API.
     * @param string       $responseKey Key containing useful values in response
     * (defaults to $type if unspecified)
     * @param string|array $valueKey    Key containing value(s) to extract from
     * response (defaults to 'name')
     * @param string       $formatStr   A sprintf format string for assembling the
     * parameters retrieved using $valueKey
     *
     * @return array
     */
    protected function getCourseResourceList(
        $type,
        $responseKey = null,
        $valueKey = 'name',
        $formatStr = '%s'
    ) {
        $retVal = [];

        // Results can be paginated, so let's loop until we've gotten everything:
        foreach ($this->getPagedResults(
            $responseKey ?? $type,
            '/coursereserves/' . $type
        ) as $item) {
            $callback = function ($key) use ($item) {
                return $item->$key ?? '';
            };
            $retVal[$item->id]
                = sprintf($formatStr, ...array_map($callback, (array)$valueKey));
        }
        return $retVal;
    }

    /**
     * Get Departments
     *
     * Obtain a list of departments for use in limiting the reserves list.
     *
     * @return array An associative array with key = dept. ID, value = dept. name.
     */
    public function getDepartments()
    {
        return $this->getCourseResourceList('departments');
    }

    /**
     * Get Instructors
     *
     * Obtain a list of instructors for use in limiting the reserves list.
     *
     * @return array An associative array with key = ID, value = name.
     */
    public function getInstructors()
    {
        $retVal = [];
        $ids = array_keys(
            $this->getCourseResourceList('courselistings', 'courseListings')
        );
        foreach ($ids as $id) {
            $retVal += $this->getCourseResourceList(
                'courselistings/' . $id . '/instructors',
                'instructors'
            );
        }
        return $retVal;
    }

    /**
     * Get Courses
     *
     * Obtain a list of courses for use in limiting the reserves list.
     *
     * @return array An associative array with key = ID, value = name.
     */
    public function getCourses()
    {
        $showCodes = $this->config['CourseReserves']['displayCourseCodes'] ?? false;
        $courses = $this->getCourseResourceList(
            'courses',
            null,
            $showCodes ? ['courseNumber', 'name'] : ['name'],
            $showCodes ? '%s: %s' : '%s'
        );
        $callback = function ($course) {
            return trim(ltrim($course, ':'));
        };
        return array_map($callback, $courses);
    }

    /**
     * Given a course listing ID, get an array of associated courses.
     *
     * @param string $courseListingId Course listing ID
     *
     * @return array
     */
    protected function getCourseDetails($courseListingId)
    {
        $values = empty($courseListingId)
            ? []
            : $this->getCourseResourceList(
                'courselistings/' . $courseListingId . '/courses',
                'courses',
                'departmentId'
            );
        // Return an array with empty values in it if we can't find any values,
        // because we want to loop at least once to build our reserves response.
        return empty($values) ? ['' => ''] : $values;
    }

    /**
     * Given a course listing ID, get an array of associated instructors.
     *
     * @param string $courseListingId Course listing ID
     *
     * @return array
     */
    protected function getInstructorIds($courseListingId)
    {
        $values = empty($courseListingId)
            ? []
            : $this->getCourseResourceList(
                'courselistings/' . $courseListingId . '/instructors',
                'instructors'
            );
        // Return an array with null in it if we can't find any values, because
        // we want to loop at least once to build our course reserves response.
        return empty($values) ? [null] : array_keys($values);
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
     */
    public function findReserves($course, $inst, $dept)
    {
        $retVal = [];

        // Results can be paginated, so let's loop until we've gotten everything:
        foreach ($this->getPagedResults(
            'reserves',
            '/coursereserves/reserves'
        ) as $item) {
            $idProperty = $this->getBibIdType() === 'hrid'
                ? 'instanceHrid' : 'instanceId';
            $bibId = $item->copiedItem->$idProperty ?? null;
            if ($bibId !== null) {
                $courseData = $this->getCourseDetails(
                    $item->courseListingId ?? null
                );
                $instructorIds = $this->getInstructorIds(
                    $item->courseListingId ?? null
                );
                foreach ($courseData as $courseId => $departmentId) {
                    foreach ($instructorIds as $instructorId) {
                        $retVal[] = [
                            'BIB_ID' => $bibId,
                            'COURSE_ID' => $courseId == '' ? null : $courseId,
                            'DEPARTMENT_ID' => $departmentId == ''
                                ? null : $departmentId,
                            'INSTRUCTOR_ID' => $instructorId,
                        ];
                    }
                }
            }
        }

        // If the user has requested a filter, apply it now:
        if (!empty($course) || !empty($inst) || !empty($dept)) {
            $filter = function ($value) use ($course, $inst, $dept) {
                return (empty($course) || $course == $value['COURSE_ID'])
                    && (empty($inst) || $inst == $value['INSTRUCTOR_ID'])
                    && (empty($dept) || $dept == $value['DEPARTMENT_ID']);
            };
            return array_filter($retVal, $filter);
        }
        return $retVal;
    }

    /**
     * This method queries the ILS for a patron's current fines
     *
     * @param array $patron The patron array from patronLogin
     *
     * @return array
     */
    public function getMyFines($patron)
    {
        $query = ['query' => 'userId==' . $patron['id'] . ' and status.name==Open'];
        $fines = [];
        foreach ($this->getPagedResults(
            'accounts',
            '/accounts',
            $query
        ) as $fine) {
            $date = date_create($fine->metadata->createdDate);
            $title = $fine->title ?? null;
            $bibId = isset($fine->instanceId)
                ? $this->getBibId($fine->instanceId)
                : null;
            $fines[] = [
                'id' => $bibId,
                'amount' => $fine->amount * 100,
                'balance' => $fine->remaining * 100,
                'status' => $fine->paymentStatus->name,
                'type' => $fine->feeFineType,
                'title' => $title,
                'createdate' => date_format($date, "j M Y")
            ];
        }
        return $fines;
    }

    /**
     * Given a user object from the FOLIO API, return a name string.
     *
     * @param object $user User object
     *
     * @return string
     */
    protected function userObjectToNameString(object $user): string
    {
        $firstParts = ($user->firstName ?? '')
            . ' ' . ($user->middleName ?? '');
        $parts = [
            trim($user->lastName ?? ''),
            trim($firstParts)
        ];
        return implode(', ', array_filter($parts));
    }

    /**
     * Given a user object returned by getUserById(), return a string representing
     * the user's name.
     *
     * @param object $proxy User object from FOLIO
     *
     * @return string
     */
    protected function formatUserNameForProxyList(object $proxy): string
    {
        return $this->userObjectToNameString($proxy->personal);
    }

    /**
     * Get list of users for whom the provided patron is a proxy.
     *
     * This requires the FOLIO user configured in Folio.ini to have the permission:
     * proxiesfor.collection.get
     *
     * @param array $patron The patron array with username and password
     *
     * @return array
     */
    public function getProxiedUsers(array $patron): array
    {
        $query = [
            'query' => '(proxyUserId=="' . $patron['id'] . '")'
        ];
        $results = [];
        $proxies = $this->getPagedResults('proxiesFor', '/proxiesfor', $query);
        foreach ($proxies as $current) {
            if ($current->status ?? '' === 'Active'
                && $current->requestForSponsor ?? '' === 'Yes'
                && isset($current->userId)
            ) {
                if ($proxy = $this->getUserById($current->userId)) {
                    $results[$proxy->id] = $this->formatUserNameForProxyList($proxy);
                }
            }
        }
        return $results;
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
    public function getNewItems($page, $limit, $daysOld, $fundID = null)
    {
        return [];
    }

    // @codingStandardsIgnoreEnd
}
