<?php

/**
 * EDS API Backend
 *
 * PHP version 8
 *
 * Copyright (C) EBSCO Industries 2013
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
 * @package  Search
 * @author   Michelle Milton <mmilton@epnet.com>
 * @author   Cornelius Amzar <cornelius.amzar@bsz-bw.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */

namespace VuFindSearch\Backend\EDS;

use Exception;
use Laminas\Cache\Storage\StorageInterface as CacheAdapter;
use Laminas\Config\Config;
use Laminas\Session\Container as SessionContainer;
use VuFind\Config\Feature\SecretTrait;
use VuFindSearch\Backend\AbstractBackend;
use VuFindSearch\Backend\Exception\BackendException;
use VuFindSearch\ParamBag;
use VuFindSearch\Query\AbstractQuery;
use VuFindSearch\Response\RecordCollectionFactoryInterface;
use VuFindSearch\Response\RecordCollectionInterface;

use function in_array;

/**
 *  EDS API Backend
 *
 * @category VuFind
 * @package  Search
 * @author   Michelle Milton <mmilton@epnet.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class Backend extends AbstractBackend
{
    use SecretTrait;

    /**
     * Client user to make the actually requests to the EdsApi
     *
     * @var Connector
     */
    protected $client;

    /**
     * Query builder
     *
     * @var QueryBuilder
     */
    protected $queryBuilder;

    /**
     * User name for EBSCO EDS API account if using UID Authentication
     *
     * @var string
     */
    protected $userName;

    /**
     * Password for EBSCO EDS API account if using UID Authentication
     *
     * @var string
     */
    protected $password;

    /**
     * Profile for EBSCO EDS API account (may be overridden)
     *
     * @var string
     */
    protected $profile;

    /**
     * Default profile for EBSCO EDS API account (taken from initial config and
     * never changed)
     *
     * @var string
     */
    protected $defaultProfile;

    /**
     * Whether or not to use IP Authentication for communication with the EDS API
     *
     * @var bool
     */
    protected $ipAuth;

    /**
     * Organization EDS API requests are being made for
     *
     * @var string
     */
    protected $orgId;

    /**
     * VuFind Authentication manager
     *
     * @var \VuFind\Auth\Manager
     */
    protected $authManager = null;

    /**
     * Object cache (for storing authentication tokens)
     *
     * @var CacheAdapter
     */
    protected $cache;

    /**
     * Session container
     *
     * @var SessionContainer
     */
    protected $session;

    /**
     * Is the current user a guest?
     *
     * @var bool
     */
    protected $isGuest;

    /**
     * Backend type
     *
     * @var string
     */
    protected $backendType = null;

    /**
     * Constructor.
     *
     * @param Connector                        $client  EdsApi client to use
     * @param RecordCollectionFactoryInterface $factory Record collection factory
     * @param CacheAdapter                     $cache   Object cache
     * @param SessionContainer                 $session Session container
     * @param Config                           $config  Object representing EDS.ini
     * @param bool                             $isGuest Is the current user a guest?
     */
    public function __construct(
        Connector $client,
        RecordCollectionFactoryInterface $factory,
        CacheAdapter $cache,
        SessionContainer $session,
        Config $config = null,
        $isGuest = true
    ) {
        // Save dependencies/incoming parameters:
        $this->client = $client;
        $this->setRecordCollectionFactory($factory);
        $this->cache = $cache;
        $this->session = $session;
        $this->isGuest = $isGuest;

        // Extract key values from configuration:
        $this->userName = $config->EBSCO_Account->user_name ?? null;
        $this->password = $this->getSecretFromConfig($config->EBSCO_Account, 'password');
        $this->ipAuth = $config->EBSCO_Account->ip_auth ?? false;
        $this->profile = $config->EBSCO_Account->profile ?? null;
        $this->orgId = $config->EBSCO_Account->organization_id ?? null;

        // Save default profile value, since profile property may be overridden:
        $this->defaultProfile = $this->profile;
    }

    /**
     * Perform a search and return record collection.
     *
     * @param AbstractQuery $query  Search query
     * @param int           $offset Search offset
     * @param int           $limit  Search limit
     * @param ParamBag      $params Search backend parameters
     *
     * @return \VuFindSearch\Response\RecordCollectionInterface
     **/
    public function search(
        AbstractQuery $query,
        $offset,
        $limit,
        ParamBag $params = null
    ) {
        // process EDS API communication tokens.
        $authenticationToken = $this->getAuthenticationToken();
        $sessionToken = $this->getSessionToken();
        $this->debug(
            "Authentication Token: $authenticationToken, SessionToken: $sessionToken"
        );

        // create query parameters from VuFind data
        $queryString = $query->getAllTerms();
        $paramsStr = implode('&', null !== $params ? $params->request() : []);
        $this->debug(
            "Query: $queryString, Limit: $limit, Offset: $offset, "
            . "Params: $paramsStr"
        );

        $baseParams = $this->getQueryBuilder()->build($query);
        $paramsStr = implode('&', $baseParams->request());
        $this->debug("BaseParams: $paramsStr ");
        if (null !== $params) {
            $baseParams->mergeWith($params);
        }
        $baseParams->set('resultsPerPage', $limit);
        $page = $limit > 0 ? floor($offset / $limit) + 1 : 1;
        $baseParams->set('pageNumber', $page);

        $searchModel = $this->paramBagToEBSCOSearchModel($baseParams);
        $qs = $searchModel->convertToQueryString();
        $this->debug("Search Model query string: $qs");
        try {
            $response = $this->client
                ->search($searchModel, $authenticationToken, $sessionToken);
        } catch (ApiException $e) {
            // if the auth or session token was invalid, try once more
            switch ($e->getApiErrorCode()) {
                case 104:
                case 108:
                case 109:
                    try {
                        // For error 104, retry auth token; for 108/9, retry sess
                        // token:
                        if ($e->getApiErrorCode() == 104) {
                            $authenticationToken
                                = $this->getAuthenticationToken(true);
                        } else {
                            $sessionToken = $this->getSessionToken(true);
                        }
                        $response = $this->client->search(
                            $searchModel,
                            $authenticationToken,
                            $sessionToken
                        );
                    } catch (Exception $e) {
                        throw new BackendException(
                            $e->getMessage(),
                            $e->getCode(),
                            $e
                        );
                    }
                    break;
                case 138:
                    // User requested unavailable deep search results; first extract
                    // the next legal position from the error message:
                    $parts
                        = explode(' ', trim($e->getApiDetailedErrorDescription()));
                    $legalPos = array_pop($parts);
                    // Now calculate the legal page number and throw an exception so
                    // the controller can fix it from here:
                    $legalPage = floor($legalPos / $limit);
                    throw new \VuFindSearch\Backend\Exception\DeepPagingException(
                        $e->getMessage(),
                        $e->getCode(),
                        $legalPage,
                        $e
                    );
                default:
                    $errorMessage = "Unhandled EDS API error {$e->getApiErrorCode()} : {$e->getMessage()}";
                    $this->logError($errorMessage);
                    throw new BackendException($errorMessage, $e->getCode(), $e);
            }
        } catch (Exception $e) {
            $this->debug('Exception found: ' . $e->getMessage());
            throw new BackendException($e->getMessage(), $e->getCode(), $e);
        }
        $collection = $this->createRecordCollection($response);
        $this->injectSourceIdentifier($collection);
        return $collection;
    }

    /**
     * Retrieve a single document.
     *
     * @param string   $id     Document identifier
     * @param ParamBag $params Search backend parameters
     *
     * @return \VuFindSearch\Response\RecordCollectionInterface
     */
    public function retrieve($id, ParamBag $params = null)
    {
        $an = $dbId = $authenticationToken = $sessionToken = $hlTerms = null;
        try {
            $authenticationToken = $this->getAuthenticationToken();
            // check to see if the profile is overridden
            $overrideProfile = (null !== $params) ? $params->get('profile') : null;
            if (isset($overrideProfile)) {
                $this->profile = $overrideProfile;
            }
            $sessionToken = $this->getSessionToken();

            if ('EDS' === $this->backendType) {
                $parts = explode(',', $id, 2);
                if (!isset($parts[1])) {
                    throw new BackendException(
                        'Retrieval id is not in the correct format.'
                    );
                }
                [$dbId, $an] = $parts;
                $hlTerms = (null !== $params)
                    ? $params->get('highlightterms') : null;
                $extras = [];
                if (
                    null !== $params
                    && ($eBookFormat = $params->get('ebookpreferredformat'))
                ) {
                    $extras['ebookpreferredformat'] = $eBookFormat;
                }
                $response = $this->client->retrieveEdsItem(
                    $an,
                    $dbId,
                    $authenticationToken,
                    $sessionToken,
                    $hlTerms,
                    $extras
                );
            } elseif ('EPF' === $this->backendType) {
                $pubId = $id;
                $response = $this->client->retrieveEpfItem(
                    $pubId,
                    $authenticationToken,
                    $sessionToken
                );
            } else {
                throw new BackendException(
                    'Unknown backendType: ' . $this->backendType
                );
            }
        } catch (ApiException $e) {
            // Error codes can be reviewed at
            // https://connect.ebsco.com/s/article
            //    /EBSCO-Discovery-Service-API-Reference-Guide-Error-Codes
            // if the auth or session token was invalid, try once more
            switch ($e->getApiErrorCode()) {
                case 104:
                case 108:
                case 109:
                    try {
                        // For error 104, retry auth token; for 108/9, retry sess
                        // token:
                        if ($e->getApiErrorCode() == 104) {
                            $authenticationToken
                                = $this->getAuthenticationToken(true);
                        } else {
                            $sessionToken = $this->getSessionToken(true);
                        }
                        $response = $this->client->retrieve(
                            $an,
                            $dbId,
                            $authenticationToken,
                            $sessionToken,
                            $hlTerms
                        );
                    } catch (Exception $e) {
                        throw new BackendException(
                            $e->getMessage(),
                            $e->getCode(),
                            $e
                        );
                    }
                    break;
                case 132:
                case 133:
                case 135:
                    /* 132 Record not found
                     * 133 Simultaneous User Limit Reached
                     * 135 DbId not in profile
                     * -> fall through to treat as "record not found"
                     */
                    $response = [];
                    break;
                default:
                    throw $e;
            }
        }
        $collection = $this->createRecordCollection(['Records' => $response]);
        $this->injectSourceIdentifier($collection);
        return $collection;
    }

    /**
     * Convert a ParamBag to a EdsApi Search request object.
     *
     * @param ParamBag $params ParamBag to convert
     *
     * @return SearchRequestModel
     */
    protected function paramBagToEBSCOSearchModel(ParamBag $params)
    {
        $params = $params->getArrayCopy();
        $options = [];
        // Most parameters need to be flattened from array format, but a few
        // should remain as arrays:
        $arraySettings = [
            'query', 'facets', 'filters', 'groupFilters', 'rangeFilters', 'limiters',
        ];
        foreach ($params as $key => $param) {
            $options[$key] = in_array($key, $arraySettings)
                ? $param : $param[0];
        }
        return new SearchRequestModel($options);
    }

    /**
     * Return the record collection factory.
     *
     * Lazy loads a generic collection factory.
     *
     * @return RecordCollectionFactoryInterface
     */
    public function getRecordCollectionFactory()
    {
        return $this->collectionFactory;
    }

    /**
     * Return query builder.
     *
     * Lazy loads an empty QueryBuilder if none was set.
     *
     * @return QueryBuilder
     */
    public function getQueryBuilder()
    {
        if (!$this->queryBuilder) {
            $this->queryBuilder = new QueryBuilder();
        }
        return $this->queryBuilder;
    }

    /**
     * Set the query builder.
     *
     * @param QueryBuilder $queryBuilder Query builder
     *
     * @return void
     */
    public function setQueryBuilder(QueryBuilder $queryBuilder)
    {
        $this->queryBuilder = $queryBuilder;
    }

    /**
     * Get popular terms using the autocomplete API.
     *
     * @param string $query  Simple query string
     * @param string $domain Autocomplete type (e.g. 'rawqueries' or 'holdings')
     *
     * @return array of terms
     */
    public function autocomplete($query, $domain = 'rawqueries')
    {
        return $this->client
            ->autocomplete($query, $domain, $this->getAutocompleteData());
    }

    /// Internal API

    /**
     * Create record collection.
     *
     * @param array $records Records to process
     *
     * @return RecordCollectionInterface
     */
    protected function createRecordCollection($records)
    {
        return $this->getRecordCollectionFactory()->factory($records);
    }

    /**
     * Obtain the authentication to use with the EDS API from cache if it exists. If
     * not, then generate a new one.
     *
     * @param bool $isInvalid whether or not the the current token is invalid
     *
     * @return string
     */
    protected function getAuthenticationToken($isInvalid = false)
    {
        $token = null;
        if ($this->ipAuth) {
            return $token;
        }
        if ($isInvalid) {
            $this->cache->setItem('edsAuthenticationToken', null);
        }
        $authTokenData = $this->cache->getItem('edsAuthenticationToken');
        if (isset($authTokenData)) {
            $currentToken = $authTokenData['token'] ?? '';
            $expirationTime = $authTokenData['expiration'] ?? 0;
            $this->debug(
                'Cached Authentication data: '
                . "$currentToken, expiration time: $expirationTime"
            );

            // Check to see if the token expiration time is greater than the current
            // time. If the token is expired or within 5 minutes of expiring,
            // generate a new one.
            if (!empty($currentToken) && (time() <= ($expirationTime - (60 * 5)))) {
                return $currentToken;
            }
        }

        $username = $this->userName;
        $password = $this->password;
        $orgId = $this->orgId;
        if (!empty($username) && !empty($password)) {
            $this->debug(
                'Calling Authenticate with username: '
                . "$username, password: XXXXXXXX, orgid: $orgId "
            );
            $results = $this->client->authenticate($username, $password, $orgId);
            $token = $results['AuthToken'];
            $timeout = $results['AuthTimeout'] + time();
            $authTokenData = ['token' => $token, 'expiration' => $timeout];
            $this->cache->setItem('edsAuthenticationToken', $authTokenData);
        }
        return $token;
    }

    /**
     * Obtain the autocomplete authentication to use with the EDS API from cache
     * if it exists. If not, then generate a new set.
     *
     * @param bool $isInvalid whether or not the the current autocomplete data
     * is invalid and should be regenerated
     *
     * @return array autocomplete data
     */
    protected function getAutocompleteData($isInvalid = false)
    {
        // Autocomplete is currently unsupported with IP authentication
        if ($this->ipAuth) {
            return null;
        }
        if ($isInvalid) {
            $this->cache->setItem('edsAutocomplete', null);
        }
        $autocompleteData = $this->cache->getItem('edsAutocomplete');
        if (!empty($autocompleteData)) {
            $currentToken = $autocompleteData['token'] ?? '';
            $expirationTime = $autocompleteData['expiration'] ?? 0;

            // Check to see if the token expiration time is greater than the current
            // time. If the token is expired or within 5 minutes of expiring,
            // generate a new one.
            if (!empty($currentToken) && (time() <= ($expirationTime - (60 * 5)))) {
                return $autocompleteData;
            }
        }

        $username = $this->userName;
        $password = $this->password;
        if (!empty($username) && !empty($password)) {
            $results = $this->client
                ->authenticate($username, $password, $this->orgId, ['autocomplete']);
            $autoresult = $results['Autocomplete'] ?? [];
            if (
                isset($autoresult['Token']) && isset($autoresult['TokenTimeOut'])
                && isset($autoresult['CustId']) && isset($autoresult['Url'])
            ) {
                $token = $autoresult['Token'];
                $expiration = $autoresult['TokenTimeOut'] + time();
                $custid = $autoresult['CustId'];
                $url = $autoresult['Url'];

                $autocompleteData = compact('token', 'expiration', 'url', 'custid');
                // store token, expiration, url and custid in cache.
                $this->cache->setItem('edsAutocomplete', $autocompleteData);
            }
        }
        return $autocompleteData;
    }

    /**
     * Obtain the session token from the Session container. If it doesn't exist,
     * generate a new one.
     *
     * @param bool $isInvalid If a session token is invalid, generate a new one
     * regardless of what is in the session container
     *
     * @return string
     */
    public function getSessionToken($isInvalid = false)
    {
        // check to see if the user has logged in/out between the creation
        // of this session token and now
        if (
            !$isInvalid && !empty($this->session->sessionID)
            && $this->session->sessionGuest == $this->isGuest()
        ) {
            return $this->session->sessionID;
        }
        return $this->createEBSCOSession();
    }

    /**
     * Generate a new session token and store it in the Session container.
     *
     * @return string
     */
    protected function createEBSCOSession()
    {
        // if there is no profile passed, restore the default from the config file
        $this->session->profileID = (null == $this->profile)
            ? $this->defaultProfile : $this->profile;
        $this->session->sessionGuest = $this->isGuest();
        $this->session->sessionID = $this->createSession(
            $this->session->sessionGuest,
            $this->session->profileID
        );
        return $this->session->sessionID;
    }

    /**
     * Is the current user a guest? If so, return 'y' else 'n'.
     *
     * @return string
     */
    protected function isGuest()
    {
        return $this->isGuest ? 'y' : 'n';
    }

    /**
     * Obtain the session to use with the EDS API from cache if it exists. If not,
     * then generate a new one.
     *
     * @param bool   $isGuest Whether or not this session will be a guest session
     * @param string $profile Authentication to use for generating a new session
     * if necessary
     *
     * @return string
     */
    public function createSession($isGuest, $profile = '')
    {
        try {
            $authToken = $this->getAuthenticationToken();
            $results = $this->client->createSession($profile, $isGuest, $authToken);
        } catch (ApiException $e) {
            $errorCode = $e->getApiErrorCode();
            $desc = $e->getApiErrorDescription();
            $this->debug(
                'Error in create session request. Error code: '
                . "$errorCode, message: $desc, e: $e"
            );
            if ($e->getApiErrorCode() == 104) {
                try {
                    $authToken = $this->getAuthenticationToken(true);
                    $results = $this->client
                        ->createSession($this->profile, $isGuest, $authToken);
                } catch (Exception $e) {
                    throw new BackendException(
                        $e->getMessage(),
                        $e->getCode(),
                        $e
                    );
                }
            } else {
                throw $e;
            }
        }
        $sessionToken = $results['SessionToken'];
        return $sessionToken;
    }

    /**
     * Obtain data from the INFO method
     *
     * @param string $sessionToken Session token (optional)
     *
     * @return array
     */
    public function getInfo($sessionToken = null)
    {
        // Use a different cache key for guests, just in case info differs:
        $cacheKey = $this->isGuest ? 'edsGuestInfo' : 'edsLoggedInInfo';
        if ($data = $this->cache->getItem($cacheKey)) {
            return $data;
        }
        $authenticationToken = $this->getAuthenticationToken();
        if (null == $sessionToken) {
            try {
                $sessionToken = $this->getSessionToken();
            } catch (ApiException $e) {
                // Retry once to work around occasional 106 errors:
                $sessionToken = $this->getSessionToken();
            }
        }
        try {
            $response = $this->client->info($authenticationToken, $sessionToken);
        } catch (ApiException $e) {
            // if the auth or session token was invalid, try once more
            switch ($e->getApiErrorCode()) {
                case 104:
                case 108:
                case 109:
                    try {
                        // For error 104, retry auth token; for 108/9, retry sess
                        // token:
                        if ($e->getApiErrorCode() == 104) {
                            $authenticationToken
                                = $this->getAuthenticationToken(true);
                        } else {
                            $sessionToken = $this->getSessionToken(true);
                        }
                        $response = $this->client
                            ->info($authenticationToken, $sessionToken);
                    } catch (Exception $e) {
                        throw new BackendException(
                            $e->getMessage(),
                            $e->getCode(),
                            $e
                        );
                    }
                    break;
                default:
                    $response = [];
            }
        }
        if (!empty($response)) {
            $this->cache->setItem($cacheKey, $response);
        }
        return $response;
    }

    /**
     * Set the VuFind Authentication Manager
     *
     * @param \VuFind\Auth\Manager $authManager Authentication Manager
     *
     * @return void
     */
    public function setAuthManager($authManager)
    {
        $this->authManager = $authManager;
    }

    /**
     * Set the EBSCO backend type. Backend/EDS is used for both EDS and EPF.
     *
     * @param string $backendType 'EDS' or 'EPF'
     *
     * @return void
     */
    public function setBackendType($backendType)
    {
        $this->backendType = $backendType;
    }
}
