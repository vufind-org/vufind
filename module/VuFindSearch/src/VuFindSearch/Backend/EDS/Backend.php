<?php
/**
 * EDS API Backend
 *
 * PHP version 5
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Search
 * @author   Michelle Milton <mmilton@epnet.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */
namespace VuFindSearch\Backend\EDS;

use VuFindSearch\Backend\EDS\Zend2 as ApiClient;

use VuFindSearch\Query\AbstractQuery;

use VuFindSearch\ParamBag;

use VuFindSearch\Response\RecordCollectionInterface;
use VuFindSearch\Response\RecordCollectionFactoryInterface;

use VuFindSearch\Backend\AbstractBackend;
use VuFindSearch\Backend\Exception\BackendException;

use Zend\Cache\Storage\Adapter\AbstractAdapter as CacheAdapter;
use Zend\Config\Config;
use Zend\Session\Container as SessionContainer;

/**
 *  EDS API Backend
 *
 * @category VuFind2
 * @package  Search
 * @author   Michelle Milton <mmilton@epnet.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */
class Backend extends AbstractBackend
{
    /**
     * Client user to make the actually requests to the EdsApi
     *
     * @var ApiClient
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
    protected $userName = null;

    /**
     * Password for EBSCO EDS API account if using UID Authentication
     *
     * @var string
     */
    protected $password = null;

    /**
     * Profile for EBSCO EDS API account (may be overridden)
     *
     * @var string
     */
    protected $profile = null;

    /**
     * Default profile for EBSCO EDS API account (taken from initial config and
     * never changed)
     *
     * @var string
     */
    protected $defaultProfile = null;

    /**
     * Whether or not to use IP Authentication for communication with the EDS API
     *
     * @var boolean
     */
    protected $ipAuth = false;

    /**
     * Organization EDS API requests are being made for
     *
     * @var string
     */
    protected $orgId = null;

    /**
     * Vufind Authentication manager
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
     * Constructor.
     *
     * @param ApiClient                        $client  EdsApi client to use
     * @param RecordCollectionFactoryInterface $factory Record collection factory
     * @param CacheAdapter                     $cache   Object cache
     * @param SessionContainer                 $session Session container
     * @param Config                           $config  Object representing EDS.ini
     */
    public function __construct(ApiClient $client,
        RecordCollectionFactoryInterface $factory, CacheAdapter $cache,
        SessionContainer $session, Config $config = null
    ) {
        // Save dependencies:
        $this->client = $client;
        $this->setRecordCollectionFactory($factory);
        $this->cache = $cache;
        $this->session = $session;

        // Extract key values from configuration:
        if (isset($config->EBSCO_Account->user_name)) {
            $this->userName = $config->EBSCO_Account->user_name;
        }
        if (isset($config->EBSCO_Account->password)) {
            $this->password = $config->EBSCO_Account->password;
        }
        if (isset($config->EBSCO_Account->ip_auth)) {
            $this->ipAuth = $config->EBSCO_Account->ip_auth;
        }
        if (isset($config->EBSCO_Account->profile)) {
            $this->profile = $config->EBSCO_Account->profile;
        }
        if (isset($config->EBSCO_Account->organization_id)) {
            $this->orgId = $config->EBSCO_Account->organization_id;
        }

        // Save default profile value, since profile property may be overriden:
        $this->defaultProfile = $this->profile;
    }

     /**
     * Perform a search and return record collection.
     *
     * @param AbstractQuery $query  Search query
     * @param integer       $offset Search offset
     * @param integer       $limit  Search limit
     * @param ParamBag      $params Search backend parameters
     *
     *@return \VuFindSearch\Response\RecordCollectionInterface
     **/
    public function search(AbstractQuery $query, $offset, $limit,
        ParamBag $params = null
    ) {
        // process EDS API communication tokens.
        $authenticationToken = $this->getAuthenticationToken();
        $sessionToken = $this->getSessionToken();
        $this->debugPrint(
            "Authentication Token: $authenticationToken, SessionToken: $sessionToken"
        );

        // check to see if there is a parameter to only process this call as a setup
        if (null !== $params && true == $params->get('setuponly')) {
            return false;
        }

        // create query parameters from VuFind data
        $queryString = !empty($query) ? $query->getAllTerms() : '';
        $paramsStr = implode('&', null !== $params ? $params->request() : []);
        $this->debugPrint(
            "Query: $queryString, Limit: $limit, Offset: $offset, "
            . "Params: $paramsStr"
        );

        $baseParams = $this->getQueryBuilder()->build($query);
        $paramsStr = implode('&', $baseParams->request());
        $this->debugPrint("BaseParams: $paramsStr ");
        if (null !== $params) {
            $baseParams->mergeWith($params);
        }
        $baseParams->set('resultsPerPage', $limit);
        $page = $limit > 0 ? floor($offset / $limit) + 1 : 1;
        $baseParams->set('pageNumber', $page);

        $searchModel = $this->paramBagToEBSCOSearchModel($baseParams);
        $qs = $searchModel->convertToQueryString();
        $this->debugPrint("Search Model query string: $qs");
        try {
            $response = $this->client
                ->search($searchModel, $authenticationToken, $sessionToken);
        } catch (\EbscoEdsApiException $e) {
            // if the auth or session token was invalid, try once more
            switch ($e->getApiErrorCode()) {
            case 104:
            case 108:
            case 109:
                try {
                    // For error 104, retry auth token; for 108/9, retry sess token:
                    if ($e->getApiErrorCode() == 104) {
                        $authenticationToken = $this->getAuthenticationToken(true);
                    } else {
                        $sessionToken = $this->getSessionToken(true);
                    }
                    $response = $this->client
                        ->search($searchModel, $authenticationToken, $sessionToken);
                } catch(Exception $e) {
                    throw new BackendException($e->getMessage(), $e->getCode(), $e);
                }
                break;
            default:
                $response = [];
                break;
            }
        } catch(Exception $e) {
            $this->debugPrint("Exception found: " . $e->getMessage());
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
        try {
            $authenticationToken = $this->getAuthenticationToken();
            // check to see if the profile is overriden
            $overrideProfile = (null !== $params) ? $params->get('profile') : null;
            if (isset($overrideProfile)) {
                $this->profile = $overrideProfile;
            }
            $sessionToken = $this->getSessionToken();
            $parts = explode(',', $id, 2);
            if (!isset($parts[1])) {
                throw new BackendException(
                    'Retrieval id is not in the correct format.'
                );
            }
            list($dbId, $an) = $parts;
            $hlTerms = (null != $params)
                ? $params->get('highlightterms') : null;
            $response = $this->client->retrieve(
                $an, $dbId, $authenticationToken, $sessionToken, $hlTerms
            );
        } catch (\EbscoEdsApiException $e) {
            // if the auth or session token was invalid, try once more
            switch ($e->getApiErrorCode()) {
            case 104:
            case 108:
            case 109:
                try {
                    // For error 104, retry auth token; for 108/9, retry sess token:
                    if ($e->getApiErrorCode() == 104) {
                        $authenticationToken = $this->getAuthenticationToken(true);
                    } else {
                        $sessionToken = $this->getSessionToken(true);
                    }
                    $response = $this->client->retrieve(
                        $an, $dbId,  $authenticationToken, $sessionToken, $hlTerms
                    );
                } catch(Exception $e) {
                    throw new BackendException($e->getMessage(), $e->getCode(), $e);
                }
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
            'query', 'facets', 'filters', 'groupFilters', 'rangeFilters', 'limiters'
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
            $currentToken =  isset($authTokenData['token'])
                ? $authTokenData['token'] : '';
            $expirationTime = isset($authTokenData['expiration'])
                ? $authTokenData['expiration'] : 0;
            $this->debugPrint(
                'Cached Authentication data: '
                . "$currentToken, expiration time: $expirationTime"
            );

            // Check to see if the token expiration time is greater than the current
            // time.  If the token is expired or within 5 minutes of expiring,
            // generate a new one.
            if (!empty($currentToken) && (time() <= ($expirationTime - (60 * 5)))) {
                return $currentToken;
            }
        }

        $username = $this->userName;
        $password = $this->password;
        $orgId = $this->orgId;
        if (!empty($username) && !empty($password)) {
            $this->debugPrint(
                'Calling Authenticate with username: '
                . "$username, password: $password, orgid: $orgId "
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
     * Print a message if debug is enabled.
     *
     * @param string $msg Message to print
     *
     * @return void
     */
    protected function debugPrint($msg)
    {
        $this->log('debug', "$msg\n");
    }

    /**
     * Obtain the session token from the Session container. If it doesn't exist,
     * generate a new one.
     *
     * @param boolean $isInvalid If a session token is invalid, generate a new one
     * regardless of what is in the session container
     *
     * @return string
     */
    public function getSessionToken($isInvalid = false)
    {
        // check to see if the user has logged in/out between the creation
        // of this session token and now
        if (!$isInvalid && !empty($this->session->sessionID)
            && $this->session->sessionGuest == $this->isGuest()
        ) {
            return $this->session->sessionID;
        }

        // When creating a new session, also call the INFO method to pull the
        // available search criteria for this profile
        $sessionToken = $this->createEBSCOSession();
        $this->session->info = $this->getInfo($sessionToken);

        return $sessionToken;
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
            $this->session->sessionGuest, $this->session->profileID
        );
        return $this->session->sessionID;
    }

    /**
     * Determines whether or not the current user session is identifed as a guest
     * session
     *
     * @return string 'y'|'n'
     */
    protected function isGuest()
    {
        // If the user is not logged in, then treat them as a guest. Unless they are
        // using IP Authentication.
        // If IP Authentication is used, then don't treat them as a guest.
        if ($this->ipAuth) {
            return 'n';
        }
        if (isset($this->authManager)) {
            return $this->authManager->isLoggedIn() ? 'n' : 'y';
        }
        return 'y';
    }

    /**
     * Obtain the session to use with the EDS API from cache if it exists. If not,
     * then generate a new one.
     *
     * @param bool   $isGuest Whether or not this sesssion will be a guest session
     * @param string $profile Authentication to use for generating a new session
     * if necessary
     *
     * @return string
     */
    public function createSession($isGuest, $profile = '')
    {
        try {
            $authToken = $this->getAuthenticationToken();
            $results = $this->client->createSession($profile,  $isGuest, $authToken);
        } catch(\EbscoEdsApiException $e) {
            $errorCode = $e->getApiErrorCode();
            $desc = $e->getApiErrorDescription();
            $this->debugPrint(
                'Error in create session request. Error code: '
                . "$errorCode, message: $desc, e: $e"
            );
            if ($e->getApiErrorCode() == 104) {
                try {
                    $authToken = $this->getAuthenticationToken(true);
                    $results = $this->client
                        ->createSession($this->profile,  $isGuest, $authToken);
                } catch(Exception $e) {
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
        $authenticationToken = $this->getAuthenticationToken();
        if (null == $sessionToken) {
            $sessionToken = $this->getSessionToken();
        }
        try {
            $response = $this->client->info($authenticationToken, $sessionToken);
        } catch (\EbscoEdsApiException $e) {
            if ($e->getApiErrorCode() == 104) {
                try {
                    $authenticationToken = $this->getAuthenticationToken(true);
                    $response = $this->client
                        ->info($authenticationToken, $sessionToken);
                } catch(Exception $e) {
                    throw new BackendException(
                        $e->getMessage(),
                        $e->getCode(),
                        $e
                    );

                }
            } else {
                $response = [];
            }
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
}
