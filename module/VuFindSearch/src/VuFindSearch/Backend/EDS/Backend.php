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
use VuFindSearch\Response\RecordCollectionFactoryInterface as
    RecordCollectionFactoryInterface;

use VuFindSearch\Backend\BackendInterface as BackendInterface;
use VuFindSearch\Backend\Exception\BackendException;

use Zend\Log\LoggerInterface;
use VuFindSearch\Backend\EDS\Response\RecordCollection;
use VuFindSearch\Backend\EDS\Response\RecordCollectionFactory;

use Zend\ServiceManager\ServiceLocatorInterface;

/**
 *  EDS API Backend
 *
 * @category VuFind2
 * @package  Search
 * @author   Michelle Milton <mmilton@epnet.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */
class Backend implements BackendInterface
{
    /**
     * Client user to make the actually requests to the EdsApi
     *
     * @var ApiClient
     */
    protected $client;

    /**
     * Backend identifier
     *
     * @var identifier
     */
    protected $identifier;

    /**
     * Query builder
     *
     * @var QueryBuilder
     */
    protected $queryBuilder;

    /**
     * Record collection factory
     *
     * @var RecordCollectionFactory
     */
    protected $collectionFactory;

    /**
     * Logger, if any.
     *
     * @var LoggerInterface
     */
    protected $logger;

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
     * Profile for EBSCO EDS API account
     *
     * @var string
     */
    protected $profile = null;

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
    protected $orgid = null;

    /**
     * Superior service manager.
     *
     * @var ServiceLocatorInterface
     */
    protected $serviceLocator;

    /**
     * Vufind Authentication manager
     *
     * @var \VuFind\Auth\Manager
     */
    protected $authManager = null;

    /**
     * Constructor.
     *
     * @param ApiClient                        $client  EdsApi client to use
     * @param RecordCollectionFactoryInterface $factory Record collection factory
     * @param array                            $account Account details
     */
    public function __construct(ApiClient $client,
        RecordCollectionFactoryInterface $factory, array $account
    ) {
        $this->setRecordCollectionFactory($factory);
        $this->client = $client;
        $this->identifier   = null;
        $this->userName = isset($account['username']) ? $account['username'] : null;
        $this->password = isset($account['password']) ? $account['password'] : null;
        $this->ipAuth = isset($account['ipauth']) ? $account['ipauth'] : null;
        $this->profile = isset($account['profile']) ? $account['profile'] : null;
        $this->orgId = isset($account['orgid']) ? $account['orgid'] : null;
    }

    /**
     * Set the backend identifier.
     *
     * @param string $identifier Backend identifier
     *
     * @return void
     */
    public function setIdentifier($identifier)
    {
        $this->identifier = $identifier;
    }

    /**
     * Return backend identifier.
     *
     * @return string
     */
    public function getIdentifier()
    {
        return $this->identifier;
    }

    /**
     * Sets the superior service locator
     *
     * @param ServiceLocatorInterface $serviceLocator Superior service locator
     *
     * @return void
     */
    public function setServiceLocator($serviceLocator)
    {
        $this->serviceLocator =  $serviceLocator;
    }

    /**
     * gets the superior service locator
     *
     * @return ServiceLocatorInterface Superior service locator
     */
    public function getServiceLocator()
    {
        return $this->serviceLocator;
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
        //process EDS API communication tokens.
        $authenticationToken = $this->getAuthenticationToken();
        $sessionToken = $this->getSessionToken();
        $this->debugPrint(
            "Authentication Token: $authenticationToken, SessionToken: $sessionToken"
        );

        //check to see if there is a parameter to only process this call as a setup
        if (null != $params->get('setuponly') && true == $params->get('setuponly')) {
            return false;
        }

        //create query parameters from VuFind data
        $queryString = !empty($query) ? $query->getAllTerms() : '';
        $paramsString = implode('&', $params->request());
        $this->debugPrint(
            "Query: $queryString, Limit: $limit, Offset: $offset, "
            . "Params: $paramsString"
        );

        $baseParams = $this->getQueryBuilder()->build($query);
        $paramsString = implode('&', $baseParams->request());
        $this->debugPrint("BaseParams: $paramsString ");
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
            // if the auth token was invalid, try once more
            if ($e->getApiErrorCode() == 104) {
                try {
                    $authenticationToken = $this->getAuthenticationToken(true);
                    $response = $this->client
                        ->search($searchModel, $authenticationToken, $sessionToken);
                } catch(Exception $e) {
                    throw new BackendException(
                        $e->getMessage(),
                        $e->getCode(),
                        $e
                    );

                }
            } else if (108 == $e->getApiErrorCode()
                || 109 == $e->getApiErrorCode()
            ) {
                try {
                    $sessionToken = $this->getSessionToken(true);
                    $response = $this->client
                        ->search($searchModel, $authenticationToken, $sessionToken);
                } catch(Exception $e) {
                    throw new BackendException(
                        $e->getMessage(),
                        $e->getCode(),
                        $e
                    );

                }
            } else {
                $response = array();
            }

        } catch(Exception $e) {
            $this->debugPrint("Exception found: " . $e->getMessage());

            throw new BackendException(
                $e->getMessage(),
                $e->getCode(),
                $e
            );
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
            //check to see if the profile is overriden
            $overrideProfile =  $params->get('profile');
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
            $dbId = $parts[0];
            $an  = $parts[1];
            $highlightTerms = null;
            if (null != $params) {
                $highlightTerms = $params->get('highlightterms');
            }
            $response = $this->client->retrieve(
                $an, $dbId, $authenticationToken, $sessionToken, $highlightTerms
            );
        } catch (\EbscoEdsApiException $e) {
            if ($e->getApiErrorCode() == 104) {
                try {
                    $authenticationToken = $this->getAuthenticationToken(true);
                    $response = $this->client->retrieve(
                        $an, $dbId,  $authenticationToken,
                        $sessionToken, $highlightTerms
                    );
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
        $collection = $this->createRecordCollection(array('Records'=> $response));
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
        $params= $params->getArrayCopy();
        $options = array();
        // Most parameters need to be flattened from array format, but a few
        // should remain as arrays:
        $arraySettings = array(
            'query', 'facets', 'filters', 'groupFilters', 'rangeFilters', 'limiters'
        );
        foreach ($params as $key => $param) {
            $options[$key] = in_array($key, $arraySettings)
                ? $param : $param[0];
        }
        return new SearchRequestModel($options);
    }

    /**
     * Set the record collection factory.
     *
     * @param RecordCollectionFactoryInterface $factory Factory
     *
     * @return void
     */
    public function setRecordCollectionFactory(
        RecordCollectionFactoryInterface $factory
    ) {
        $this->collectionFactory = $factory;
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
     *
     */
    public function setQueryBuilder(QueryBuilder $queryBuilder)
    {
        $this->queryBuilder = $queryBuilder;
    }

    /// Internal API

    /**
     * Inject source identifier in record collection and all contained records.
     *
     * @param ResponseInterface $response Response
     *
     * @return void
     */
    protected function injectSourceIdentifier(RecordCollectionInterface $response)
    {
        $response->setSourceIdentifier($this->identifier);
        foreach ($response as $record) {
            $record->setSourceIdentifier($this->identifier);
        }
        return $response;
    }

    /**
     * Send a message to the logger.
     *
     * @param string $level   Log level
     * @param string $message Log message
     * @param array  $context Log context
     *
     * @return void
     */
    protected function log($level, $message, array $context = array())
    {
        if ($this->logger) {
            $this->logger->$level($message, $context);
        }
    }

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
     * Set the Logger.
     *
     * @param LoggerInterface $logger Logger
     *
     * @return void
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
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
        if (!empty($this->ipAuth) && true == $this->ipAuth) {
            return $token;
        }
        $cache = $this->getServiceLocator()->get('VuFind\CacheManager')
            ->getCache('object');
        if ($isInvalid) {
            $cache->setItem('edsAuthenticationToken', null);
        }
        $authTokenData = $cache->getItem('edsAuthenticationToken');
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
            if (!empty($currentToken) && (time() <= ($expirationTime - (60*5)))) {
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
            $authTokenData = array('token' => $token, 'expiration' => $timeout);
            $cache->setItem('edsAuthenticationToken', $authTokenData);
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
        if ($this->logger) {
            $this->logger->debug("$msg\n");
        } else {
            parent::debugPrint($msg);
        }
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
        $sessionToken = '';
        $container = new \Zend\Session\Container('EBSCO');
        if (!$isInvalid && !empty($container->sessionID)) {
            // check to see if the user has logged in/out between the creation
            // of this session token and now
            $sessionGuest = $container->sessionGuest;
            $currentGuest = $this->isGuest();

            if ($sessionGuest == $currentGuest) {
                return $container->sessionID;
            }
        }

        $sessionToken = $this->createEBSCOSession();
        // When creating a new session, also call the INFO method to pull the
        // available search criteria for this profile
        $this->createSearchCriteria($sessionToken);

        return $sessionToken;
    }

    /**
     * Generate a new session token and store it in the Session container.
     *
     * @return string
     */
    protected function createEBSCOSession()
    {
        // If the user is not logged in, the treat them as a guest. Unless they are
        // using IP Authentication.
        // If IP Authentication is used, then don't treat them as a guest.
        $guest = ($this->isAuthenticationIP()) ? 'n' : $this->isGuest();
        $container = new \Zend\Session\Container('EBSCO');

        // if there is no profile passed, use the one set in the configuration file
        $profile = $this->profile;
        if (null == $profile) {
            $config = $this->getServiceLocator()->get('VuFind\Config')->get('EDS');
            if (isset($config->EBSCO_Account->profile)) {
                $profile = $config->EBSCO_Account->profile;
            }
        }
        $session = $this->createSession($guest, $profile);
        $container->sessionID = $session;
        $container->profileID = $profile;
        $container->sessionGuest = $guest;
        return $container->sessionID;

    }

    /**
     * Determines whether or not the current user session is identifed as a guest
     * session
     *
     * @return string 'y'|'n'
     */
    protected function isGuest()
    {
        if (isset($this->authManager)) {
            return $this->authManager->isLoggedIn() ? 'n' : 'y';
        }
        return 'y';
    }

     /**
     * Is IP Authentication being used?
     *
     * @return bool
     */
    protected function isAuthenticationIP()
    {
        $config = $this->getServiceLocator()->get('VuFind\Config')->get('EDS');
        return (isset($config->EBSCO_Account->ip_auth)
            && 'true' ==  $config->EBSCO_Account->ip_auth);
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
    public function createSession($isGuest, $profile='')
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
                        ->info($searchModel, $authenticationToken, $sessionToken);
                } catch(Exception $e) {
                    throw new BackendException(
                        $e->getMessage(),
                        $e->getCode(),
                        $e
                    );

                }
            } else {
                $response = array();
            }
        }
        return $response;

    }

    /**
     * Obtain available search criteria from the info method and store it in the
     * session container
     *
     * @param string $sessionToken Session token to use to call the INFO method.
     *
     * @return array
    */
    protected function createSearchCriteria($sessionToken)
    {
        $container = new \Zend\Session\Container('EBSCO');
        $info = $this->getInfo($sessionToken);
        $container->info = $info;
        return $container->info;
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
