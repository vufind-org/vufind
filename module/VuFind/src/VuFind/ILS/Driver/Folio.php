<?php

/**
 * FOLIO REST API driver
 *
 * PHP version 8
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
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
use Laminas\Http\Response;
use VuFind\Config\Feature\SecretTrait;
use VuFind\Exception\ILS as ILSException;
use VuFind\I18n\Translator\TranslatorAwareInterface;
use VuFind\ILS\Logic\AvailabilityStatus;
use VuFindHttp\HttpServiceAwareInterface as HttpServiceAwareInterface;

use function array_key_exists;
use function count;
use function in_array;
use function is_callable;
use function is_int;
use function is_object;
use function is_string;
use function sprintf;

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
    HttpServiceAwareInterface,
    TranslatorAwareInterface
{
    use SecretTrait;
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
     * Maximum number of ids to pass in the query to load FOLIO records with getByBatch()
     */
    protected const QUERY_BY_IDS_BATCH_SIZE = 20;

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
     * Authentication token expiration time
     *
     * @var string
     */
    protected $tokenExpiration = null;

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
        'Open - Awaiting delivery',
    ];

    /**
     * Cache for course reserves course data (null if not yet populated)
     *
     * @var ?array
     */
    protected $courseCache = null;

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
     * Support method for makeRequest to process an unexpected status code. Can return true to trigger
     * a retry of the API call or false to throw an exception.
     *
     * @param Response $response      HTTP response
     * @param int      $attemptNumber Counter to keep track of attempts (starts at 1 for the first attempt)
     *
     * @return bool
     */
    protected function shouldRetryAfterUnexpectedStatusCode(Response $response, int $attemptNumber): bool
    {
        // If the unexpected status is 401, and the token renews successfully, and we have not yet
        // retried, we should try again:
        if ($response->getStatusCode() === 401 && !$this->checkTenantToken() && $attemptNumber < 2) {
            $this->debug('Retrying request after token expired...');
            return true;
        }
        return parent::shouldRetryAfterUnexpectedStatusCode($response, $attemptNumber);
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
        if (
            $method == 'GET'
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
            ' Params: ' . $this->varDump($logParams) . '.' .
            ' Headers: ' . $this->varDump($logHeaders)
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
        // If not using legacy authentication, see if the token has expired before trying to renew it
        if (!$this->useLegacyAuthentication() && !$this->checkTenantTokenExpired()) {
            $currentTime = gmdate('D, d-M-Y H:i:s T', strtotime('now'));
            $this->debug(
                'No need to renew token; not yet expired. ' . $currentTime . ' < ' . $this->tokenExpiration .
                'Username: ' . $this->config['API']['username'] . ' Token: ' . substr($this->token, 0, 30) . '...'
            );
            return;
        }
        $startTime = microtime(true);
        $this->token = null;
        $response = $this->performOkapiUsernamePasswordAuthentication(
            $this->config['API']['username'],
            $this->getSecretFromConfig($this->config['API'], 'password')
        );
        $this->setTokenValuesFromResponse($response);
        $endTime = microtime(true);
        $responseTime = $endTime - $startTime;
        $this->debug(
            'Token renewed in ' . $responseTime . ' seconds. Username: ' . $this->config['API']['username'] .
            ' Token: ' . substr($this->token, 0, 30) . '...'
        );
    }

    /**
     * Check if our token is still valid. Return true if the token was already valid, false if it had to be renewed.
     *
     * Method taken from Stripes JS (loginServices.js:validateUser)
     *
     * @return bool
     */
    protected function checkTenantToken()
    {
        if ($this->useLegacyAuthentication()) {
            $response = $this->makeRequest('GET', '/users', [], [], [401, 403]);
            if ($response->getStatusCode() < 400) {
                return true;
            }
            // Clear token data to ensure that checkTenantTokenExpired triggers a renewal:
            $this->token = $this->tokenExpiration = null;
        }
        if ($this->checkTenantTokenExpired()) {
            $this->token = $this->tokenExpiration = null;
            $this->renewTenantToken();
            return false;
        }
        return true;
    }

    /**
     * Check if our token has expired. Return true if it has expired, false if it has not.
     *
     * @return bool
     */
    protected function checkTenantTokenExpired()
    {
        return
            $this->token == null
            || $this->tokenExpiration == null
            || strtotime('now') >= strtotime($this->tokenExpiration);
    }

    /**
     * Should we use a global cache for FOLIO API tokens?
     *
     * @return bool
     */
    protected function useGlobalTokenCache(): bool
    {
        // If we're configured to store user-specific tokens, we can't use the global
        // token cache.
        $useUserToken = $this->config['User']['use_user_token'] ?? false;
        return !$useUserToken && ($this->config['API']['global_token_cache'] ?? true);
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
        $cacheType = 'session';
        if ($this->useGlobalTokenCache()) {
            $globalTokenData = (array)($this->getCachedData('token') ?? []);
            if (count($globalTokenData) === 2) {
                $cacheType = 'global';
                [$this->sessionCache->folio_token, $this->sessionCache->folio_token_expiration] = $globalTokenData;
            }
        }
        if ($this->sessionCache->folio_token ?? false) {
            $this->token = $this->sessionCache->folio_token;
            $this->tokenExpiration = $this->sessionCache->folio_token_expiration ?? null;
            $this->debug(
                'Token taken from ' . $cacheType . ' cache: ' . substr($this->token, 0, 30) . '...'
            );
        }
        try {
            if ($this->token == null) {
                $this->renewTenantToken();
            } else {
                $this->checkTenantToken();
            }
        } catch (\Exception $e) {
            // Errors in init() should not be fatal,
            // it could prevent using other configured search handlers when FOLIO fails
            $this->token = $this->tokenExpiration = null;
            $this->logError('Failed to get a token to initialize the FOLIO driver: ' . $e->getMessage());
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
                $item = $this->getItemById($itemId);
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
     * Get an item record by its UUID.
     *
     * @param string $itemId UUID
     *
     * @return \stdClass The item
     */
    protected function getItemById($itemId)
    {
        $response = $this->makeRequest(
            'GET',
            '/item-storage/items/' . $itemId
        );
        $item = json_decode($response->getBody());
        return $item;
    }

    /**
     * Given an instance object or identifier, or a holding or item identifier,
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
     * Get FOLIO records by batches of ids.
     * When using a unique field for $idField (such as 'id'), this function does not check
     * if all records are found, and returned records are not guaranteed to be in the order of the given ids.
     *
     * @param string[] $ids         ids to look for in the records
     * @param string   $idField     field to compare to given ids
     * @param string   $responseKey response key with the records to retrieve
     * @param string   $endpoint    FOLIO API endpoint
     * @param string   $querySuffix optional string to append to the queries
     *
     * @return \Generator<object>
     * @throws ILSException if there is an issue with the FOLIO response
     */
    protected function getByBatch($ids, $idField, $responseKey, $endpoint, $querySuffix = '')
    {
        if (count($ids) == 0) {
            return;
        }
        $idToKey = fn ($id) => $endpoint . '[' . $idField . '=' . $id . ']';
        $idsToLookFor = [];
        foreach ($ids as $id) {
            $items = $this->getCachedData($idToKey($id));
            if ($items == null) {
                $idsToLookFor[] = $id;
            } else {
                foreach ($items as $item) {
                    yield $item;
                }
            }
        }
        $resultsToCache = [];
        foreach (array_chunk($idsToLookFor, self::QUERY_BY_IDS_BATCH_SIZE) as $idsInBatch) {
            $idsWithQuotes = array_map(fn ($id) => '"' . $this->escapeCql($id) . '"', $idsInBatch);
            $query = [
                'query' => $idField . ' == (' . implode(' OR ', $idsWithQuotes) . ')' . $querySuffix,
            ];
            foreach (
                $this->getPagedResults(
                    $responseKey,
                    $endpoint,
                    $query
                ) as $item
            ) {
                $key = $idToKey($item->$idField);
                if (isset($resultsToCache[$key])) {
                    $resultsToCache[$key][] = $item;
                } else {
                    $resultsToCache[$key] = [$item];
                }
                yield $item;
            }
        }
        foreach ($resultsToCache as $key => $items) {
            $this->putCachedData($key, $items);
        }
    }

    /**
     * Support method for getHoldings() -- retrieve holdings by instance ids
     *
     * @param string[] $instanceIds the FOLIO instance ids
     *
     * @return object[]
     * @throws ILSException if there is an issue with the FOLIO response
     */
    protected function getHoldingsByInstanceIds(array $instanceIds)
    {
        if (count($instanceIds) == 0) {
            return [];
        }
        $holdings = [];
        $querySuffix = ' NOT discoverySuppress==true';
        foreach (
            $this->getByBatch(
                $instanceIds,
                'instanceId',
                'holdingsRecords',
                '/holdings-storage/holdings',
                $querySuffix
            ) as $holding
        ) {
            $holdings[] = $holding;
        }
        return $holdings;
    }

    /**
     * Support method for getHoldings() -- retrieve items by holding ids (including bound-with items)
     *
     * @param string[] $holdingIds the FOLIO holdings ids
     *
     * @return object[] The items, with an additional queryHoldingsRecordId property with the matching holdings id
     * @throws ILSException if there is an issue with the FOLIO response
     */
    protected function getItemsByHoldingIds(array $holdingIds)
    {
        if (count($holdingIds) == 0) {
            return [];
        }
        $items = [];
        $folioItemSort = $this->config['Holdings']['folio_sort'] ?? '';
        $querySuffix = !empty($folioItemSort) ? ' sortby ' . $folioItemSort : '';
        if (count($holdingIds) == 1) {
            // /inventory/items-by-holdings-id returns bound-with items too (but it only takes one holdingsRecordId)
            foreach (
                $this->getByBatch(
                    $holdingIds,
                    'holdingsRecordId',
                    'items',
                    '/inventory/items-by-holdings-id',
                    $querySuffix
                ) as $item
            ) {
                $item->queryHoldingsRecordId = $holdingIds[0];
                $items[] = $item;
            }
            return $items;
        }
        // /inventory/items does not return bound-with items, we have to retrieve them afterwards
        foreach (
            $this->getByBatch(
                $holdingIds,
                'holdingsRecordId',
                'items',
                '/inventory/items',
                $querySuffix
            ) as $item
        ) {
            $item->queryHoldingsRecordId = $item->holdingsRecordId;
            $items[] = $item;
        }
        $boundWithItemIds = [];
        $itemIdToHoldingsRecordId = [];
        foreach (
            $this->getByBatch(
                $holdingIds,
                'holdingsRecordId',
                'boundWithParts',
                '/inventory-storage/bound-with-parts',
                $querySuffix
            ) as $boundWithPart
        ) {
            $boundWithItemIds[] = $boundWithPart->itemId;
            $itemIdToHoldingsRecordId[$boundWithPart->itemId] = $boundWithPart->holdingsRecordId;
        }
        foreach (
            $this->getByBatch(
                $boundWithItemIds,
                'id',
                'items',
                '/inventory/items'
            ) as $item
        ) {
            $item->queryHoldingsRecordId = $itemIdToHoldingsRecordId[$item->id];
            $items[] = $item;
        }
        return $items;
    }

    /**
     * Retrieve FOLIO instances using VuFind's chosen bibliographic identifiers.
     * Returned instances may not be in the order of the given ids.
     *
     * @param string[] $bibIds Bib-level ids
     *
     * @return object[]
     * @throws ILSException if there is an issue with the FOLIO response or an instance is not found
     */
    protected function getInstancesByBibIds($bibIds)
    {
        // Figure out which ID type to use in the CQL query; if the user configured
        // instance IDs, use the 'id' field, otherwise pass the setting through
        // directly:
        $idType = $this->getBibIdType();
        $idField = $idType === 'instance' ? 'id' : $idType;
        $instances = [];
        foreach (
            $this->getByBatch(
                $bibIds,
                $idField,
                'instances',
                '/instance-storage/instances'
            ) as $instance
        ) {
            $instances[] = $instance;
        }
        if (count($instances) != count($bibIds)) {
            throw new ILSException('An instance was not found, bibIds=' . implode(',', $bibIds));
        }
        return $instances;
    }

    /**
     * Retrieve FOLIO instance using VuFind's chosen bibliographic identifier.
     *
     * @param string $bibId Bib-level id
     *
     * @return object
     * @throws ILSException if there is an issue with the FOLIO response or the instance is not found
     */
    protected function getInstanceByBibId($bibId)
    {
        // NOTE: getInstancesByBibIds() throws an exception if there is no instance matching bibId
        return $this->getInstancesByBibIds([$bibId])[0];
    }

    /**
     * Returns the status for the given bib-level id
     *
     * @param string $bibId Bib-level id
     *
     * @return array an array of associative arrays, one for each item
     * @throws ILSException if there is an issue with a FOLIO response or the instance is not found
     */
    public function getStatus($bibId)
    {
        $holding = $this->getHolding($bibId);
        return $holding['holdings'];
    }

    /**
     * Return statuses for an array of bibIds, optimizing retrieval with bulk calls
     *
     * @param string[] $idList array of bibIds
     *
     * @return array[] the items for each bibId (in the given order)
     * @throws ILSException if there is an issue with a FOLIO response or an instance is not found
     */
    public function getStatuses($idList)
    {
        $holdings = $this->getHoldings($idList);
        return array_map(fn ($holding) => $holding['holdings'], $holdings);
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
        $key = match ($function) {
            'getMyTransactions' => 'Loans',
            default => $function
        };
        return $this->config[$key] ?? false;
    }

    /**
     * Check whether an item is holdable based on its location and any
     * current loan
     *
     * @param string     $locationName locationName from getHolding
     * @param ?\stdClass $currentLoan  The current loan, or null if none
     *
     * @return bool
     */
    protected function isHoldable($locationName, $currentLoan = null)
    {
        return $this->isHoldableLocation($locationName) &&
            (!$currentLoan || $this->isHoldableByCurrentLoan($currentLoan));
    }

    /**
     * Check item location against list of configured locations
     * where holds should be offered
     *
     * @param string $locationName locationName from getHolding
     *
     * @return bool
     */
    protected function isHoldableLocation($locationName)
    {
        $mode = $this->config['Holds']['excludeHoldLocationsCompareMode'] ?? 'exact';
        $excludeLocs = (array)($this->config['Holds']['excludeHoldLocations'] ?? []);

        // Exclude checking by regex match
        if (trim(strtolower($mode)) == 'regex') {
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
     * Check whether an item is holdable based on any current loan
     *
     * @param \stdClass $currentLoan The current loan
     *
     * @return bool
     */
    protected function isHoldableByCurrentLoan(\stdClass $currentLoan)
    {
        $currentLoanPatronGroup = $currentLoan->patronGroupAtCheckout->name ?? '';
        $excludePatronGroups = $this->config['Holds']['excludeHoldCurrentLoanPatronGroups'] ?? [];
        return !in_array($currentLoanPatronGroup, $excludePatronGroups);
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
            foreach (
                $this->getPagedResults(
                    'locations',
                    '/locations'
                ) as $location
            ) {
                $name = $location->discoveryDisplayName ?? $location->name;
                $code = $location->code;
                $isActive = $location->isActive ?? true;
                $servicePointIds = $location->servicePointIds;
                $locationMap[$location->id] = compact('name', 'code', 'isActive', 'servicePointIds');
            }
            $this->putCachedData($cacheKey, $locationMap);
        }
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
        $isActive = true;
        $servicePointIds = [];
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
                $isActive = $location->isActive ?? $isActive;
                $servicePointIds = $location->servicePointIds;
            }
        }

        return compact('name', 'code', 'isActive', 'servicePointIds');
    }

    /**
     * Get data about a loan type.
     *
     * @param string $loanTypeId UUID
     *
     * @return array
     */
    protected function getLoanTypeData($loanTypeId)
    {
        $cacheKey = 'loanTypeMap';
        $loanTypeMap = $this->getCachedData($cacheKey);
        if (null === $loanTypeMap) {
            $loanTypeMap = [];
            foreach (
                $this->getPagedResults(
                    'loantypes',
                    '/loan-types'
                ) as $loanType
            ) {
                if (isset($loanType->name)) {
                    $name = $loanType->name;
                    $loanTypeMap[$loanType->id] = compact('name');
                }
            }
        }
        $this->putCachedData($cacheKey, $loanTypeMap);
        return $loanTypeMap[$loanTypeId];
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
     * Support method: format a note for display
     *
     * @param object $note Note object decoded from FOLIO JSON.
     *
     * @return string
     */
    protected function formatNote($note): string
    {
        return !($note->staffOnly ?? false) && !empty($note->note)
            ? $note->note : '';
    }

    /**
     * Support method for getHoldings(): extract details from the holding record that
     * will be needed by formatHoldingItem() below.
     *
     * @param object $holding FOLIO holding record (decoded from JSON)
     *
     * @return array
     */
    protected function getHoldingDetailsForItem($holding): array
    {
        $textFormatter = function ($supplement) {
            $format = '%s %s';
            $supStat = $supplement->statement ?? '';
            $supNote = $supplement->note ?? '';
            $statement = trim(
                // Avoid duplicate display if note and statement are identical:
                $supStat === $supNote ? $supStat : sprintf($format, $supStat, $supNote)
            );
            return $statement;
        };
        $id = $holding->id;
        $holdingNotes = array_filter(
            array_map([$this, 'formatNote'], $holding->notes ?? [])
        );
        $hasHoldingNotes = !empty(implode($holdingNotes));
        $holdingsStatements = array_values(array_filter(array_map(
            $textFormatter,
            $holding->holdingsStatements ?? []
        )));
        $holdingsSupplements = array_values(array_filter(array_map(
            $textFormatter,
            $holding->holdingsStatementsForSupplements ?? []
        )));
        $holdingsIndexes = array_values(array_filter(array_map(
            $textFormatter,
            $holding->holdingsStatementsForIndexes ?? []
        )));
        $holdingCallNumber = $holding->callNumber ?? '';
        $holdingCallNumberPrefix = $holding->callNumberPrefix ?? '';
        return compact(
            'id',
            'holdingNotes',
            'hasHoldingNotes',
            'holdingsStatements',
            'holdingsSupplements',
            'holdingsIndexes',
            'holdingCallNumber',
            'holdingCallNumberPrefix'
        );
    }

    /**
     * Support method for getHoldings() -- return an array of item-level details from
     * other data: the location, the holdings record, and any current loan on the item.
     *
     * Depending on where this method is called, $locationId will be the holdings record
     * location (in the case where no items are attached to a holding) or the item record
     * location (in cases where there are attached items).
     *
     * @param string     $locationId     Location identifier from FOLIO
     * @param array      $holdingDetails Holding details produced by getHoldingDetailsForItem()
     * @param ?\stdClass $currentLoan    Any current loan on this item
     *
     * @return array
     */
    protected function getItemFieldsFromNonItemData(
        string $locationId,
        array $holdingDetails,
        ?\stdClass $currentLoan = null,
    ): array {
        $locationData = $this->getLocationData($locationId);
        $locationName = $locationData['name'];
        return [
            'is_holdable' => $this->isHoldable($locationName, $currentLoan),
            'holdings_notes' => $holdingDetails['hasHoldingNotes']
                ? $holdingDetails['holdingNotes'] : null,
            'summary' => array_unique($holdingDetails['holdingsStatements']),
            'supplements' => $holdingDetails['holdingsSupplements'],
            'indexes' => $holdingDetails['holdingsIndexes'],
            'location' => $locationName,
            'location_code' => $locationData['code'],
            'folio_location_is_active' => $locationData['isActive'],
        ];
    }

    /**
     * Support method for getHoldings() -- given a few key details, format an item
     * for inclusion in the return value.
     *
     * @param string     $bibId            Current bibliographic ID
     * @param array      $holdingDetails   Holding details produced by
     *                                     getHoldingDetailsForItem()
     * @param object     $item             FOLIO item record (decoded from JSON)
     * @param int        $number           The current item number (position within
     *                                     current holdings record)
     * @param string     $dueDateValue     The due date to display to the user
     * @param array      $boundWithRecords Any bib records this holding is bound with
     * @param ?\stdClass $currentLoan      Any current loan on this item
     *
     * @return array
     */
    protected function formatHoldingItem(
        string $bibId,
        array $holdingDetails,
        $item,
        $number,
        string $dueDateValue,
        $boundWithRecords,
        $currentLoan
    ): array {
        $itemNotes = array_filter(
            array_map([$this, 'formatNote'], $item->notes ?? [])
        );
        $locationId = $item->effectiveLocation->id;

        // concatenate enumeration fields if present
        $enum = implode(
            ' ',
            array_filter(
                [
                    $item->volume ?? null,
                    $item->enumeration ?? null,
                    $item->chronology ?? null,
                ]
            )
        );
        $callNumberData = $this->chooseCallNumber(
            $holdingDetails['holdingCallNumberPrefix'],
            $holdingDetails['holdingCallNumber'],
            $item->effectiveCallNumberComponents->prefix
                ?? $item->itemLevelCallNumberPrefix ?? '',
            $item->effectiveCallNumberComponents->callNumber
                ?? $item->itemLevelCallNumber ?? ''
        );
        $locAndHoldings = $this->getItemFieldsFromNonItemData($locationId, $holdingDetails, $currentLoan);

        $loanTypeName = '';
        $tempLoanTypeId = $item->temporaryLoanType->id ?? '';
        $permLoanTypeId = $item->permanentLoanType->id ?? '';
        $loanTypeId = !empty($tempLoanTypeId) ? $tempLoanTypeId : $permLoanTypeId;
        if (!empty($loanTypeId)) {
            $loanData = $this->getLoanTypeData($loanTypeId);
            $loanTypeName = $loanData['name'];
        }

        return $callNumberData + $locAndHoldings + [
            'id' => $bibId,
            'item_id' => $item->id,
            'holdings_id' => $holdingDetails['id'],
            'number' => $number,
            'enumchron' => $enum,
            'barcode' => $item->barcode ?? '',
            'status' => $item->status->name,
            'duedate' => $dueDateValue,
            'availability' => $item->status->name == 'Available',
            'item_notes' => !empty(implode($itemNotes)) ? $itemNotes : null,
            'reserve' => 'TODO',
            'addLink' => 'check',
            'bound_with_records' => $boundWithRecords,
            'loan_type_id' => $loanTypeId,
            'loan_type_name' => $loanTypeName,
        ];
    }

    /**
     * Given a holdings array and a sort field, sort the array.
     *
     * @param array  $holdings  Holdings to sort
     * @param string $sortField Sort field
     *
     * @return array
     */
    protected function sortHoldings(array $holdings, string $sortField): array
    {
        usort(
            $holdings,
            function ($a, $b) use ($sortField) {
                return strnatcasecmp($a[$sortField], $b[$sortField]);
            }
        );
        // Renumber the re-sorted batch:
        $nbCount = count($holdings);
        for ($nbIndex = 0; $nbIndex < $nbCount; $nbIndex++) {
            $holdings[$nbIndex]['number'] = $nbIndex + 1;
        }
        return $holdings;
    }

    /**
     * Get all bib records bound-with this item, including
     * the directly-linked bib record.
     *
     * @param object $item The item record
     *
     * @return array An array of key metadata for each bib record
     */
    protected function getBoundWithRecords($item)
    {
        $boundWithRecords = [];
        // Get the full item record, which includes the boundWithTitles data
        $response = $this->makeRequest(
            'GET',
            '/inventory/items/' . $item->id
        );
        $item = json_decode($response->getBody());
        foreach ($item->boundWithTitles ?? [] as $boundWithTitle) {
            $boundWithRecords[] = [
                'title' => $boundWithTitle->briefInstance?->title,
                'bibId' => $this->getBibId($boundWithTitle->briefInstance->id),
            ];
        }
        return $boundWithRecords;
    }

    /**
     * Support method for getHoldings() -- processes a FOLIO item
     *
     * @param string $bibId            Bib-level id
     * @param array  $holdingDetails   details for the holding
     * @param object $item             item to process
     * @param int    $dueDateItemCount number of times getCurrentLoan()/getDueDate() were called (passed by reference)
     * @param int    $number           item number
     *
     * @return array An associative array
     */
    protected function processItem($bibId, $holdingDetails, $item, &$dueDateItemCount, $number)
    {
        $showDueDate = $this->config['Availability']['showDueDate'] ?? true;
        $showTime = $this->config['Availability']['showTime'] ?? false;
        $maxNumDueDateItems = $this->config['Availability']['maxNumberItems'] ?? 5;
        $currentLoan = null;
        $dueDateValue = '';
        $boundWithRecords = null;
        if (
            $item->status->name == 'Checked out'
            && $showDueDate
            && $dueDateItemCount < $maxNumDueDateItems
        ) {
            $currentLoan = $this->getCurrentLoan($item->id);
            $dueDateValue = $currentLoan ? $this->getDueDate($currentLoan, $showTime) : '';
            $dueDateItemCount++;
        }
        if ($item->isBoundWith ?? false) {
            $boundWithRecords = $this->getBoundWithRecords($item);
        }
        $nextItem = $this->formatHoldingItem(
            $bibId,
            $holdingDetails,
            $item,
            $number,
            $dueDateValue,
            $boundWithRecords ?? [],
            $currentLoan
        );
        return $nextItem;
    }

    /**
     * Support method for getHoldings() -- processes FOLIO records for a single instance
     *
     * @param string   $bibId      Bib-level id
     * @param object[] $holdings   holdings for the instance
     * @param object[] $folioItems items to look into to find the holdings items
     *
     * @return array An associative array with information about the instance holdings
     */
    protected function processInstanceHoldings($bibId, $holdings, $folioItems)
    {
        $showHoldingsNoItems = $this->config['Holdings']['show_holdings_no_items'] ?? false;
        $dueDateItemCount = 0;
        $items = [];
        $vufindItemSort = $this->config['Holdings']['vufind_sort'] ?? '';
        foreach ($holdings as $holding) {
            $holdingDetails = $this->getHoldingDetailsForItem($holding);
            $nextBatch = [];
            $sortNeeded = false;
            $number = 0;
            $folioItemsForHolding = array_filter(
                $folioItems,
                fn ($item) => $item->queryHoldingsRecordId == $holding->id
            );
            foreach ($folioItemsForHolding as $item) {
                if ($item->discoverySuppress ?? false) {
                    continue;
                }
                $number++;
                $nextItem = $this->processItem($bibId, $holdingDetails, $item, $dueDateItemCount, $number);
                if (!empty($vufindItemSort) && !empty($nextItem[$vufindItemSort])) {
                    $sortNeeded = true;
                }
                $nextBatch[] = $nextItem;
            }

            // If there are no item records on this holding, we're going to create a fake one,
            // fill it with data from the FOLIO holdings record, and make it not appear in
            // the full record display using a non-visible AvailabilityStatus.
            if ($number == 0 && $showHoldingsNoItems) {
                $locAndHoldings = $this->getItemFieldsFromNonItemData($holding->effectiveLocationId, $holdingDetails);
                $invisibleAvailabilityStatus = new AvailabilityStatus(
                    true,
                    'HoldingStatus::holding_no_items_availability_message'
                );
                $invisibleAvailabilityStatus->setVisibilityInHoldings(false);
                $nextBatch[] = $locAndHoldings + [
                    'id' => $bibId,
                    'callnumber' => $holdingDetails['holdingCallNumber'],
                    'callnumber_prefix' => $holdingDetails['holdingCallNumberPrefix'],
                    'reserve' => 'N',
                    'availability' => $invisibleAvailabilityStatus,
                ];
            }
            $items = array_merge(
                $items,
                $sortNeeded
                    ? $this->sortHoldings($nextBatch, $vufindItemSort) : $nextBatch
            );
        }

        return [
            'total' => count($items),
            'holdings' => $items,
            'electronic_holdings' => [],
        ];
    }

    /**
     * Query the ILS for information about a single holdings.
     *
     * @param string $bibId   Bib-level id
     * @param ?array $patron  Patron login information from $this->patronLogin
     * @param array  $options Extra options (not currently used)
     *
     * @return array An associative array with the keys: total, holdings, electronic_holdings
     * @throws ILSException if there is an issue with a FOLIO response or the instance is not found
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getHolding($bibId, ?array $patron = null, array $options = [])
    {
        // NOTE: getHoldings() always returns something for a bibId, unless the instance is not found.
        // If the instance is not found, an ILSException is thrown.
        return $this->getHoldings([$bibId])[0];
    }

    /**
     * Query the ILS for holdings information.
     *
     * @param string[] $bibIds Bib-level ids
     *
     * @return array[] An array of associative arrays, one for each bibId
     * @throws ILSException if there is an issue with a FOLIO response or an instance is not found
     */
    public function getHoldings($bibIds)
    {
        $idType = $this->getBibIdType();
        $bibIdToInstanceId = [];
        if ($idType === 'instance') {
            // Do not retrieve the instances if we already have their ids
            $instanceIds = $bibIds;
            foreach ($bibIds as $bibId) {
                $bibIdToInstanceId[$bibId] = $bibId;
            }
        } else {
            $instances = $this->getInstancesByBibIds($bibIds);
            $instanceIds = array_map(fn ($instance) => $instance->id, $instances);
            foreach ($instances as $instance) {
                $bibIdToInstanceId[$instance->$idType] = $instance->id;
            }
        }
        $holdings = $this->getHoldingsByInstanceIds($instanceIds);
        $holdingIds = array_map(fn ($holding) => $holding->id, $holdings);
        $folioItems = count($holdings) == 0 ? [] : $this->getItemsByHoldingIds($holdingIds);
        $results = [];
        foreach ($bibIds as $bibId) {
            $instanceId = $bibIdToInstanceId[$bibId];
            $holdingsForInstance = array_filter($holdings, fn ($holding) => $holding->instanceId == $instanceId);
            $results[] = $this->processInstanceHoldings($bibId, $holdingsForInstance, $folioItems);
        }
        return $results;
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
        $localTimezone = (new DateTime())->getTimezone();
        $dateTime->setTimezone($localTimezone);
        return $dateTime;
    }

    /**
     * Support method for getHoldings(): obtaining the Due Date from the
     * current loan, adjusting the timezone and formatting in universal
     * time with or without due time
     *
     * @param \stdClass|string $loan     The current loan, or its itemId for legacy backwards compatibility
     * @param bool             $showTime Determines if date or date & time is returned
     *
     * @return string
     */
    protected function getDueDate($loan, $showTime)
    {
        if (is_string($loan)) {
            $loan = $this->getCurrentLoan($loan);
        }
        $dueDate = $this->getDateTimeFromString($loan->dueDate);
        $method = $showTime
            ? 'convertToDisplayDateAndTime' : 'convertToDisplayDate';
        return $this->dateConverter->$method('U', $dueDate->format('U'));
    }

    /**
     * Support method for getHoldings(): obtaining any current loan from OKAPI
     * by calling /circulation/loans with the item->id
     *
     * @param string $itemId ID for the item to query
     *
     * @return \stdClass|void
     */
    protected function getCurrentLoan($itemId)
    {
        $query = 'itemId==' . $itemId . ' AND status.name==Open';
        foreach (
            $this->getPagedResults(
                'loans',
                '/circulation/loans',
                compact('query')
            ) as $loan
        ) {
            // many loans are returned for an item, the one we want
            // is the one without a returnDate
            if (!isset($loan->returnDate) && isset($loan->dueDate)) {
                return $loan;
            }
        }
        return null;
    }

    /**
     * Should we use the legacy authentication mechanism?
     *
     * @return bool
     */
    protected function useLegacyAuthentication(): bool
    {
        return $this->config['API']['legacy_authentication'] ?? true;
    }

    /**
     * Support method to perform a username/password login to Okapi.
     *
     * @param string $username The patron username
     * @param string $password The patron password
     *
     * @return Response
     */
    protected function performOkapiUsernamePasswordAuthentication(string $username, string $password): Response
    {
        $tenant = $this->config['API']['tenant'];
        $credentials = compact('tenant', 'username', 'password');
        // Get token
        return $this->makeRequest(
            method: 'POST',
            path: $this->useLegacyAuthentication() ? '/authn/login' : '/authn/login-with-expiry',
            params: json_encode($credentials),
            debugParams: '{"username":"...","password":"..."}'
        );
    }

    /**
     * Given a response from performOkapiUsernamePasswordAuthentication(),
     * extract the requested cookie.
     *
     * @param Response $response   Response from performOkapiUsernamePasswordAuthentication().
     * @param string   $cookieName Name of the cookie to get from the response.
     *
     * @return \Laminas\Http\Header\SetCookie
     */
    protected function getCookieByName(Response $response, string $cookieName): \Laminas\Http\Header\SetCookie
    {
        $folioUrl = $this->config['API']['base_url'];
        $cookies = new \Laminas\Http\Cookies();
        $cookies->addCookiesFromResponse($response, $folioUrl);
        $results = $cookies->getAllCookies();
        foreach ($results as $cookie) {
            if ($cookie->getName() == $cookieName) {
                return $cookie;
            }
        }
        throw new \Exception('Could not find ' . $cookieName . ' cookie in response');
    }

    /**
     * Given a response from performOkapiUsernamePasswordAuthentication(),
     * extract and save authentication data we want to preserve.
     *
     * @param Response $response Response from performOkapiUsernamePasswordAuthentication().
     *
     * @return null
     */
    protected function setTokenValuesFromResponse(Response $response)
    {
        // If using legacy authentication, there is no option to renew tokens,
        // so assume the token is expired as of now
        if ($this->useLegacyAuthentication()) {
            $this->token = $response->getHeaders()->get('X-Okapi-Token')->getFieldValue();
            $this->tokenExpiration = gmdate('D, d-M-Y H:i:s T', strtotime('now'));
            $tokenCacheLifetime = 600; // cache old-fashioned tokens for 10 minutes
        } elseif ($cookie = $this->getCookieByName($response, 'folioAccessToken')) {
            $this->token = $cookie->getValue();
            $this->tokenExpiration = $cookie->getExpires();
            // cache RTR tokens using their known lifetime:
            $tokenCacheLifetime = strtotime($this->tokenExpiration) - strtotime('now');
        }
        if ($this->token != null && $this->tokenExpiration != null) {
            $this->sessionCache->folio_token = $this->token;
            $this->sessionCache->folio_token_expiration = $this->tokenExpiration;
            if ($this->useGlobalTokenCache()) {
                $this->putCachedData('token', [$this->token, $this->tokenExpiration], $tokenCacheLifetime);
            }
        } else {
            throw new \Exception('Could not find token data in response');
        }
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
        $response = $this->performOkapiUsernamePasswordAuthentication($username, $password);
        $debugMsg = 'User logged in. User: ' . $username . '.';
        // We've authenticated the user with Okapi, but we only have their
        // username; set up a query to retrieve full info below.
        $query = 'username == ' . $username;
        // Replace admin with user as tenant if configured to do so:
        if ($this->config['User']['use_user_token'] ?? false) {
            $this->setTokenValuesFromResponse($response);
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
     * Get a total count of records from a FOLIO endpoint.
     *
     * @param string $interface FOLIO api interface to call
     * @param array  $query     Extra GET parameters (e.g. ['query' => 'your cql here'])
     *
     * @return int
     */
    protected function getResultCount(string $interface, array $query = []): int
    {
        $combinedQuery = array_merge($query, ['limit' => 0]);
        $response = $this->makeRequest(
            'GET',
            $interface,
            $combinedQuery
        );
        $json = json_decode($response->getBody());
        return $json->totalRecords ?? 0;
    }

    /**
     * Helper function to retrieve a single page of results from FOLIO API
     *
     * @param string $interface FOLIO api interface to call
     * @param array  $query     Extra GET parameters (e.g. ['query' => 'your cql here'])
     * @param int    $offset    Starting record index
     * @param int    $limit     Max number of records to retrieve
     *
     * @return array
     * @throws ILSException if the response code is not a success or the response is not JSON
     */
    protected function getResultPage($interface, $query = [], $offset = 0, $limit = 1000)
    {
        $combinedQuery = array_merge($query, compact('offset', 'limit'));
        $response = $this->makeRequest(
            'GET',
            $interface,
            $combinedQuery
        );
        $json = json_decode($response->getBody());
        if (!$response->isSuccess() || !$json) {
            $msg = $json->errors[0]->message ?? json_last_error_msg();
            throw new ILSException("Error: '$msg' fetching from '$interface'");
        }
        return $json;
    }

    /**
     * Helper function to retrieve paged results from FOLIO API
     *
     * @param string $responseKey Key containing values to collect in response
     * @param string $interface   FOLIO api interface to call
     * @param array  $query       Extra GET parameters (e.g. ['query' => 'your cql here'])
     * @param int    $limit       How many results to retrieve from FOLIO per call
     *
     * @return array
     * @throws ILSException if there is an issue with the response
     */
    protected function getPagedResults($responseKey, $interface, $query = [], $limit = 1000)
    {
        $offset = 0;

        do {
            $json = $this->getResultPage($interface, $query, $offset, $limit);
            $totalEstimate = $json->totalRecords ?? 0;
            foreach ($json->$responseKey ?? [] as $item) {
                yield $item ?? '';
            }
            $offset += $limit;

            // Continue until the current offset is greater than the totalRecords value returned
            // from the API (which could be an estimate if more than 1000 results are returned).
        } while ($offset <= $totalEstimate);
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
        return $this->createPatronArray(
            id: $profile->id,
            cat_username: $username,
            cat_password: $password,
            firstname: $profile->personal->firstName ?? null,
            lastname: $profile->personal->lastName ?? null,
            email: $profile->personal->email ?? null,
            nonDefaultFields: [
                // Add username just in case for legacy
                'username' => $username,
                'addressTypeIds' => array_map(
                    fn ($address) => $address->addressTypeId,
                    $profile->personal->addresses ?? []
                ),
            ],
        );
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
        $address = $profile->personal->addresses[0] ?? null;
        return $this->createProfileArray(
            firstname: $profile->personal->firstName ?? null,
            lastname: $profile->personal->lastName ?? null,
            address1: $address->addressLine1 ?? null,
            city: $address->city ?? null,
            country: $address->countryId ?? null,
            zip: $address->postalCode ?? null,
            phone: $profile->personal->phone ?? null,
            mobile_phone: $profile->personal->mobilePhone ?? null,
            expiration_date: isset($profile->expirationDate)
                ? $this->dateConverter->convertToDisplayDate(
                    'Y-m-d H:i',
                    $profile->expirationDate
                )
                : null,
            nonDefaultFields: [
                'id' => $profile->id,
            ]
        );
    }

    /**
     * This method queries the ILS for a patron's current checked out items
     *
     * Input: Patron array returned by patronLogin method
     * Output: Returns with a 'count' key (overall result set size) and a 'records'
     *         key (current page of results) containing subarrays representing records
     *         and containing these keys:
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
     * @param array $params Additional parameters (limit, page, sort)
     *
     * @return array Transaction data as described above
     */
    public function getMyTransactions($patron, $params = [])
    {
        $limit = $params['limit'] ?? 1000;
        $offset = isset($params['page']) ? ($params['page'] - 1) * $limit : 0;

        $query = 'userId==' . $patron['id'] . ' and status.name==Open';
        if (isset($params['sort'])) {
            $query .= ' sortby ' . $this->escapeCql($params['sort']);
        }
        $resultPage = $this->getResultPage('/circulation/loans', compact('query'), $offset, $limit);
        $transactions = [];
        foreach ($resultPage->loans ?? [] as $trans) {
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
        // If we have a full page or have applied an offset, we need to look up the total count of transactions:
        $count = count($transactions);
        if ($offset > 0 || $count >= $limit) {
            // We could use the count in the result page, but that may be an estimate;
            // safer to do a separate lookup to be sure we have the right number!
            $count = $this->getResultCount('/circulation/loans', compact('query'));
        }
        return ['count' => $count, 'records' => $transactions];
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
                'userId' => $renewDetails['patron']['id'],
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
                            'Y-m-d H:i',
                            $json->dueDate
                        ),
                        'new_time' => $this->dateConverter->convertToDisplayTime(
                            'Y-m-d H:i',
                            $json->dueDate
                        ),
                        'item_id' => $json->itemId,
                        'sysMessage' => $json->action,
                    ];
                } else {
                    $json = json_decode($response->getBody());
                    $sysMessage = $json->errors[0]->message;
                    $renewal = [
                        'success' => false,
                        'sysMessage' => $sysMessage,
                    ];
                }
            } catch (Exception $e) {
                $this->debug(
                    "Unexpected exception renewing $loanId: " . $e->getMessage()
                );
                $renewal = [
                    'success' => false,
                    'sysMessage' => 'Renewal Failed',
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
    public function getPickupLocations($patron, $holdInfo = null)
    {
        if ('Delivery' == ($holdInfo['requestGroupId'] ?? null)) {
            $addressTypes = $this->getAddressTypes();
            $limitDeliveryAddressTypes = $this->config['Holds']['limitDeliveryAddressTypes'] ?? [];
            $deliveryPickupLocations = [];
            foreach ($patron['addressTypeIds'] as $addressTypeId) {
                $addressType = $addressTypes[$addressTypeId];
                if (empty($limitDeliveryAddressTypes) || in_array($addressType, $limitDeliveryAddressTypes)) {
                    $deliveryPickupLocations[] = [
                        'locationID' => $addressTypeId,
                        'locationDisplay' => $addressType,
                    ];
                }
            }
            return $deliveryPickupLocations;
        }

        $limitedServicePoints = null;
        if (
            str_contains($this->config['Holds']['limitPickupLocations'] ?? '', 'itemEffectiveLocation')
            // If there's no item ID, it must be a title-level hold,
            // so limiting by itemEffectiveLocation does not apply
            && $holdInfo['item_id'] ?? false
        ) {
            $item = $this->getItemById($holdInfo['item_id']);
            $itemLocationId = $item->effectiveLocationId;
            $limitedServicePoints = $this->getLocationData($itemLocationId)['servicePointIds'];
        }

        // If we have $holdInfo, we can limit ourselves to pickup locations that are valid in context. Because the
        // allowed service point list doesn't include discovery display names, we can't use it directly; we just
        // have to obtain a list of IDs to use as a filter below.
        $legalServicePoints = null;
        if ($holdInfo) {
            $allowed = $this->getAllowedServicePoints(
                $this->getInstanceByBibId($holdInfo['id'])->id,
                $holdInfo['item_id'] ?? null,
                $patron['id']
            );
            if ($allowed !== null) {
                $legalServicePoints = [];
                $preferredRequestType = $this->getPreferredRequestType($holdInfo);
                foreach ($this->getRequestTypeList($preferredRequestType) as $requestType) {
                    foreach ($allowed[$requestType] ?? [] as $servicePoint) {
                        $legalServicePoints[] = $servicePoint['id'];
                    }
                }
            }
        }

        $query = ['query' => 'pickupLocation=true'];
        $locations = [];
        foreach (
            $this->getPagedResults(
                'servicepoints',
                '/service-points',
                $query
            ) as $servicePoint
        ) {
            if ($legalServicePoints !== null && !in_array($servicePoint->id, $legalServicePoints)) {
                continue;
            }
            if ($limitedServicePoints && !in_array($servicePoint->id, $limitedServicePoints)) {
                continue;
            }

            $locations[] = [
                'locationID' => $servicePoint->id,
                'locationDisplay' => $servicePoint->discoveryDisplayName,
            ];
        }
        return $locations;
    }

    /**
     * Get Default Pick Up Location
     *
     * Returns the default pick up location set in HorizonXMLAPI.ini
     *
     * @param array $patron      Patron information returned by the patronLogin
     * method.
     * @param array $holdDetails Optional array, only passed in when getting a list
     * in the context of placing a hold; contains most of the same values passed to
     * placeHold, minus the patron data. May be used to limit the pickup options
     * or may be ignored.
     *
     * @return false|string      The default pickup location for the patron or false
     * if the user has to choose.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getDefaultPickUpLocation($patron = false, $holdDetails = null)
    {
        if ('Delivery' == ($holdDetails['requestGroupId'] ?? null)) {
            $deliveryPickupLocations = $this->getPickupLocations($patron, $holdDetails);
            if (count($deliveryPickupLocations) == 1) {
                return $deliveryPickupLocations[0]['locationDisplay'];
            }
        }
        return false;
    }

    /**
     * Get request groups
     *
     * @param int   $bibId       BIB ID
     * @param array $patron      Patron information returned by the patronLogin
     * method.
     * @param array $holdDetails Optional array, only passed in when getting a list
     * in the context of placing a hold; contains most of the same values passed to
     * placeHold, minus the patron data. May be used to limit the request group
     * options or may be ignored.
     *
     * @return array  False if request groups not in use or an array of
     * associative arrays with id and name keys
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getRequestGroups(
        $bibId = null,
        $patron = null,
        $holdDetails = null
    ) {
        // circulation-storage.request-preferences.collection.get
        $response = $this->makeRequest(
            'GET',
            '/request-preference-storage/request-preference?query=userId==' . $patron['id']
        );
        $requestPreferencesResponse = json_decode($response->getBody());
        $requestPreferences = $requestPreferencesResponse->requestPreferences[0];
        $allowHoldShelf = $requestPreferences->holdShelf;
        $allowDelivery = $requestPreferences->delivery && ($this->config['Holds']['allowDelivery'] ?? true);
        $locationsLabels = $this->config['Holds']['locationsLabelByRequestGroup'] ?? [];
        if ($allowHoldShelf && $allowDelivery) {
            return [
                [
                    'id' => 'Hold Shelf',
                    'name' => 'fulfillment_method_hold_shelf',
                    'locationsLabel' => $locationsLabels['Hold Shelf'] ?? null,
                ],
                [
                    'id' => 'Delivery',
                    'name' => 'fulfillment_method_delivery',
                    'locationsLabel' => $locationsLabels['Delivery'] ?? null,
                ],
            ];
        }
        return false;
    }

    /**
     * Get list of address types from FOLIO.  Cache as needed.
     *
     * @return array An array mapping an address type id to its name.
     */
    protected function getAddressTypes()
    {
        $cacheKey = 'addressTypes';
        $addressTypes = $this->getCachedData($cacheKey);
        if (null == $addressTypes) {
            $addressTypes = [];
            // addresstypes.collection.get
            foreach (
                $this->getPagedResults(
                    'addressTypes',
                    '/addresstypes'
                ) as $addressType
            ) {
                $addressTypes[$addressType->id] = $addressType->addressType;
            }
            $this->putCachedData($cacheKey, $addressTypes);
        }
        return $addressTypes;
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
        $userQuery = '(requesterId == "' . $patron['id'] . '" '
            . 'or proxyUserId == "' . $patron['id'] . '")';
        $query = ['query' => '(' . $userQuery . ' and status == Open*)'];
        $holds = [];
        foreach (
            $this->getPagedResults(
                'requests',
                '/request-storage/requests',
                $query
            ) as $hold
        ) {
            $requestDate = $this->dateConverter->convertToDisplayDate(
                'Y-m-d H:i',
                $hold->requestDate
            );
            // Set expire date if it was included in the response
            $expireDate = isset($hold->requestExpirationDate)
                ? $this->dateConverter->convertToDisplayDate(
                    'Y-m-d H:i',
                    $hold->requestExpirationDate
                )
                : null;
            // Set lastPickup Date if provided, format to j M Y
            $lastPickup = isset($hold->holdShelfExpirationDate)
                ? $this->dateConverter->convertToDisplayDate(
                    'Y-m-d H:i',
                    $hold->holdShelfExpirationDate
                )
                : null;
            $currentHold = [
                'type' => $hold->requestType,
                'create' => $requestDate,
                'expire' => $expireDate ?? '',
                'id' => $this->getBibId(
                    $hold->instanceId,
                    $hold->holdingsRecordId ?? null,
                    $hold->itemId ?? null
                ),
                'item_id' => $hold->itemId ?? null,
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
            if (
                ($hold->proxyUserId ?? $patron['id']) !== $patron['id']
                && isset($hold->proxy)
            ) {
                $currentHold['proxiedBy']
                    = $this->userObjectToNameString($hold->proxy);
            }
            // If this request was not created for the current user, it must be
            // a proxy request created by the current user. We should indicate this.
            if (
                ($hold->requesterId ?? $patron['id']) !== $patron['id']
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
     * Get latest major version of a $moduleName enabled for a tenant.
     * Result is cached.
     *
     * @param string $moduleName module name
     *
     * @return int module version or 0 if no module found
     */
    protected function getModuleMajorVersion(string $moduleName): int
    {
        $cacheKey = 'module_version:' . $moduleName;
        $version = $this->getCachedData($cacheKey);
        if ($version === null) {
            // get latest version of a module enabled for a tenant
            $response = $this->makeRequest(
                'GET',
                '/_/proxy/tenants/' . $this->tenant . '/modules?filter=' . $moduleName . '&latest=1'
            );

            // get version major from json result
            $versions = json_decode($response->getBody());
            $latest = $versions[0]->id ?? '0';
            preg_match_all('!\d+!', $latest, $matches);
            $version = (int)($matches[0][0] ?? 0);
            if ($version === 0) {
                $this->debug('Unable to find version in ' . $response->getBody());
            } else {
                // Only cache non-zero values, so we don't persist an error condition:
                $this->putCachedData($cacheKey, $version);
            }
        }
        return $version;
    }

    /**
     * Support method for placeHold(): get a list of request types to try.
     *
     * @param string $preferred Method to try first.
     *
     * @return array
     */
    protected function getRequestTypeList(string $preferred): array
    {
        $backupMethods = (array)($this->config['Holds']['fallback_request_type'] ?? []);
        return array_merge(
            [$preferred],
            array_diff($backupMethods, [$preferred])
        );
    }

    /**
     * Support method for placeHold(): send the request and process the response.
     *
     * @param array $requestBody Request body
     *
     * @return array
     * @throws ILSException
     */
    protected function performHoldRequest(array $requestBody): array
    {
        $response = $this->makeRequest(
            'POST',
            '/circulation/requests',
            json_encode($requestBody),
            [],
            true
        );
        try {
            $json = json_decode($response->getBody());
        } catch (Exception $e) {
            $this->throwAsIlsException($e, $response->getBody());
        }
        if ($response->isSuccess() && isset($json->status)) {
            return [
                'success' => true,
                'status' => $json->status,
            ];
        }
        return [
            'success' => false,
            'status' => $json->errors[0]->message ?? '',
        ];
    }

    /**
     * Get allowed service points for a request. Returns null if data cannot be obtained.
     *
     * @param string  $instanceId  Instance UUID being requested
     * @param ?string $itemId      Item UUID being requested (or null if unavailable/inapplicable)
     * @param string  $requesterId Patron UUID placing request
     * @param string  $operation   Operation type (default = create)
     *
     * @return ?array
     */
    protected function getAllowedServicePoints(
        string $instanceId,
        ?string $itemId,
        string $requesterId,
        string $operation = 'create'
    ): ?array {
        try {
            // circulation.requests.allowed-service-points.get
            $response = $this->makeRequest(
                'GET',
                '/circulation/requests/allowed-service-points?'
                . http_build_query(compact(empty($itemId) ? 'instanceId' : 'itemId', 'requesterId', 'operation'))
            );
            if (!$response->isSuccess()) {
                $this->warning('Unexpected service point lookup response: ' . $response->getBody());
                return null;
            }
        } catch (\Exception $e) {
            $this->warning('Exception during allowed service point lookup: ' . (string)$e);
            return null;
        }
        return json_decode($response->getBody(), true);
    }

    /**
     * Get the preferred request type for the provided hold details.
     *
     * @param array $holdDetails An array of item and patron data
     *
     * @return string
     */
    protected function getPreferredRequestType(array $holdDetails): string
    {
        $default_request = $this->config['Holds']['default_request'] ?? 'Hold';
        $isTitleLevel = ($holdDetails['level'] ?? '') === 'title';
        if ($isTitleLevel) {
            return $default_request;
        }
        return ($holdDetails['status'] ?? '') == 'Available' ? 'Page' : $default_request;
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
        if (
            !empty($holdDetails['requiredByTS'])
            && !is_int($holdDetails['requiredByTS'])
        ) {
            throw new ILSException('hold_date_invalid');
        }
        $requiredBy = !empty($holdDetails['requiredByTS'])
            ? gmdate('Y-m-d', $holdDetails['requiredByTS']) : null;

        $instance = $this->getInstanceByBibId($holdDetails['id']);
        $isTitleLevel = ($holdDetails['level'] ?? '') === 'title';
        if ($isTitleLevel) {
            $baseParams = [
                'instanceId' => $instance->id,
                'requestLevel' => 'Title',
            ];
        } else {
            // Note: early Lotus releases require instanceId and holdingsRecordId
            // to be set here as well, but the requirement was lifted in a hotfix
            // to allow backward compatibility. If you need compatibility with one
            // of those versions, you can add additional identifiers here, but
            // applying the latest hotfix is a better solution!
            $baseParams = ['itemId' => $holdDetails['item_id']];
        }
        // Account for an API spelling change introduced in mod-circulation v24:
        $fulfillmentKey = $this->getModuleMajorVersion('mod-circulation') >= 24
            ? 'fulfillmentPreference' : 'fulfilmentPreference';
        $fulfillmentValue = $holdDetails['requestGroupId'] ?? 'Hold Shelf';
        $fulfillmentLocationKey = match ($fulfillmentValue) {
            'Hold Shelf' => 'pickupServicePointId',
            'Delivery' => 'deliveryAddressTypeId',
        };
        $requestBody = $baseParams + [
            'requesterId' => $holdDetails['patron']['id'],
            'requestDate' => date('c'),
            $fulfillmentKey => $fulfillmentValue,
            'requestExpirationDate' => $requiredBy,
            $fulfillmentLocationKey => $holdDetails['pickUpLocation'],
        ];
        if (!empty($holdDetails['proxiedUser'])) {
            $requestBody['requesterId'] = $holdDetails['proxiedUser'];
            $requestBody['proxyUserId'] = $holdDetails['patron']['id'];
        }
        if (!empty($holdDetails['comment'])) {
            $requestBody['patronComments'] = $holdDetails['comment'];
        }
        $allowed = $this->getAllowedServicePoints(
            $instance->id,
            $holdDetails['item_id'] ?? null,
            $holdDetails['patron']['id']
        );
        $preferredRequestType = $this->getPreferredRequestType($holdDetails);
        foreach ($this->getRequestTypeList($preferredRequestType) as $requestType) {
            // Skip illegal request types, if we have validation data available:
            if (null !== $allowed) {
                if (
                    // Unsupported request type:
                    !isset($allowed[$requestType])
                    // Unsupported pickup location:
                    || !in_array($holdDetails['pickUpLocation'], array_column($allowed[$requestType] ?? [], 'id'))
                ) {
                    continue;
                }
            }
            $requestBody['requestType'] = $requestType;
            $result = $this->performHoldRequest($requestBody);
            if ($result['success']) {
                break;
            }
        }
        return $result ?? ['success' => false, 'status' => 'Unexpected failure'];
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
            if (
                $request_json->requesterId != $patron['id']
                && ($request_json->proxyUserId ?? null) != $patron['id']
            ) {
                throw new ILSException('Invalid Request');
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
     * Check if request is valid
     *
     * This is responsible for determining if an item is requestable
     *
     * @param string $id     The record id
     * @param array  $data   An array of item data
     * @param array  $patron An array of patron data
     *
     * @return array Two entries: 'valid' (boolean) plus 'status' (message to display to user)
     */
    public function checkRequestIsValid($id, $data, $patron)
    {
        // First check outstanding loans:
        $currentLoan = empty($data['item_id'])
            ? null
            : $this->getCurrentLoan($data['item_id']);
        if ($currentLoan && !$this->isHoldableByCurrentLoan($currentLoan)) {
            return [
                'valid' => false,
                'status' => 'hold_error_current_loan_patron_group',
            ];
        }

        $allowed = $this->getAllowedServicePoints(
            $this->getInstanceByBibId($id)->id,
            $data['item_id'] ?? null,
            $patron['id']
        );

        // If we got this far, it's valid if we can't obtain allowed service point
        // data, or if the allowed service point data is non-empty:
        $valid = null === $allowed || !empty($allowed);
        return [
            'valid' => $valid,
            'status' => $valid ? 'request_place_text' : 'No pickup locations available',
        ];
    }

    /**
     * Obtain a list of course resources, creating an id => value associative array.
     *
     * @param string       $type           Type of resource to retrieve from the API.
     * @param string       $responseKey    Key containing useful values in response
     * (defaults to $type if unspecified)
     * @param string|array $valueKey       Key containing value(s) to extract from
     * response (defaults to 'name')
     * @param string       $formatStr      A sprintf format string for assembling the
     * parameters retrieved using $valueKey
     * @param callable     $filterCallback An optional callback that can return true
     * to flag values that should be filtered out.
     *
     * @return array
     */
    protected function getCourseResourceList(
        $type,
        $responseKey = null,
        $valueKey = 'name',
        $formatStr = '%s',
        $filterCallback = null
    ) {
        $retVal = [];

        // Results can be paginated, so let's loop until we've gotten everything:
        foreach (
            $this->getPagedResults(
                $responseKey ?? $type,
                '/coursereserves/' . $type
            ) as $item
        ) {
            if (is_callable($filterCallback) && $filterCallback($item)) {
                continue;
            }
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
     * Get the callback (or null for no callback) for filtering the course listings used to retrieve instructor data.
     *
     * @return ?callable
     */
    protected function getInstructorsCourseListingsFilterCallback(): ?callable
    {
        // Unless we explicitly want to include expired course data, set up a filter to exclude it:
        if ($this->config['CourseReserves']['includeExpiredCourses'] ?? false) {
            return null;
        }
        $termsFilterCallback = function ($item) {
            return isset($item->endDate) && strtotime($item->endDate) < time();
        };
        $activeTerms = $this->getCourseResourceList('terms', filterCallback: $termsFilterCallback);
        return function ($item) use ($activeTerms) {
            return !isset($activeTerms[$item->termId ?? null]);
        };
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
        $filterCallback = $this->getInstructorsCourseListingsFilterCallback();
        $ids = array_keys(
            $this->getCourseResourceList('courselistings', 'courseListings', filterCallback: $filterCallback)
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
        if ($this->courseCache === null) {
            $showCodes = $this->config['CourseReserves']['displayCourseCodes'] ?? false;
            // Unless we explicitly want to include expired course data, set up a filter to exclude it:
            $includeExpired = $this->config['CourseReserves']['includeExpiredCourses'] ?? false;
            $filterCallback = $includeExpired ? null : function ($item) {
                return isset($item->courseListingObject->termObject->endDate)
                    && strtotime($item->courseListingObject->termObject->endDate) < time();
            };
            $courses = $this->getCourseResourceList(
                'courses',
                null,
                $showCodes ? ['courseNumber', 'name'] : ['name'],
                $showCodes ? '%s: %s' : '%s',
                $filterCallback
            );
            $callback = function ($course) {
                return trim(ltrim($course, ':'));
            };
            $this->courseCache = array_map($callback, $courses);
        }
        return $this->courseCache;
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
        $query = [];
        $legalCourses = $this->getCourses();

        $includeSuppressed = $this->config['CourseReserves']['includeSuppressed'] ?? false;

        if (!$includeSuppressed) {
            $query = [
                'query' => 'copiedItem.instanceDiscoverySuppress==false',
            ];
        }

        // Results can be paginated, so let's loop until we've gotten everything:
        foreach (
            $this->getPagedResults(
                'reserves',
                '/coursereserves/reserves',
                $query
            ) as $item
        ) {
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
                    // If the present course ID is not in the legal course list, it is likely
                    // expired data and should be skipped.
                    if (!isset($legalCourses[$courseId])) {
                        continue;
                    }
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
        foreach (
            $this->getPagedResults(
                'accounts',
                '/accounts',
                $query
            ) as $fine
        ) {
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
                'fine' => $fine->feeFineType,
                'title' => $title,
                'createdate' => date_format($date, 'j M Y'),
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
            trim($firstParts),
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
     * Support method for getProxiedUsers() and getProxyingUsers() to load proxy user data.
     *
     * This requires the FOLIO user configured in Folio.ini to have the permission:
     * proxiesfor.collection.get
     *
     * @param array  $patron       The patron array with username and password
     * @param string $lookupField  Field to use for looking up matching users
     * @param string $displayField Field in response to use for displaying user names
     *
     * @return array
     */
    protected function loadProxyUserData(array $patron, string $lookupField, string $displayField): array
    {
        $query = [
            'query' => '(' . $lookupField . '=="' . $patron['id'] . '")',
        ];
        $results = [];
        $proxies = $this->getPagedResults('proxiesFor', '/proxiesfor', $query);
        foreach ($proxies as $current) {
            if (
                $current->status ?? '' === 'Active'
                && $current->requestForSponsor ?? '' === 'Yes'
                && isset($current->$displayField)
            ) {
                if ($proxy = $this->getUserById($current->$displayField)) {
                    $results[$proxy->id] = $this->formatUserNameForProxyList($proxy);
                }
            }
        }
        return $results;
    }

    /**
     * Get list of users for whom the provided patron is a proxy.
     *
     * @param array $patron The patron array with username and password
     *
     * @return array
     */
    public function getProxiedUsers(array $patron): array
    {
        return $this->loadProxyUserData($patron, 'proxyUserId', 'userId');
    }

    /**
     * Get list of users who act as proxies for the provided patron.
     *
     * @param array $patron The patron array with username and password
     *
     * @return array
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getProxyingUsers(array $patron): array
    {
        return $this->loadProxyUserData($patron, 'userId', 'proxyUserId');
    }

    /**
     * NOT FINISHED BELOW THIS LINE
     **/

    /**
     * Check for request blocks.
     *
     * @param array $patron The patron array with username and password
     *
     * @return array|bool An array of block messages or false if there are no blocks
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getRequestBlocks($patron)
    {
        return false;
    }

    /**
     * Get Purchase History Data
     *
     * This is responsible for retrieving the acquisitions history data for the
     * specific record (usually recently received issues of a serial). It is used
     * by getHoldings() and getPurchaseHistory() depending on whether the purchase
     * history is displayed by holdings or in a separate list.
     *
     * @param string $bibID The record id to retrieve the info for
     *
     * @return array An array with the acquisitions data on success.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getPurchaseHistory($bibID)
    {
        return [];
    }

    /**
     * Get Funds
     *
     * Return a list of funds which may be used to limit the getNewItems list.
     *
     * @return array An associative array with key = fund ID, value = fund name.
     */
    public function getFunds()
    {
        return [];
    }

    /**
     * Get Patron Loan History
     *
     * This is responsible for retrieving all historic loans (i.e. items previously
     * checked out and then returned), for a specific patron.
     *
     * @param array $patron The patron array from patronLogin
     * @param array $params Parameters
     *
     * @return array Array of the patron's transactions on success.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getMyTransactionHistory($patron, $params)
    {
        return[];
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
     * @return array Associative array with 'count' and 'results' keys
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getNewItems($page, $limit, $daysOld, $fundId = null)
    {
        return [];
    }
}
