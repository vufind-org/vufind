<?php
/**
 * PHP version 7
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  AJAX
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Brent Palmer <brent-palmer@icpl.org>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace VuFind\DigitalContent;

use VuFind\View\Helper\Root\DateTime;
use Zend\Log\LoggerAwareInterface;
use Zend\Session\Container;
use ZfcRbac\Service\AuthorizationServiceAwareInterface;
use ZfcRbac\Service\AuthorizationServiceAwareTrait;



/**
 * OverdriveConnector
 *
 * Class responsible for connecting to the Overdrive API
 *
 * @category VuFind
 * @package  AJAX
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Brent Palmer <brent-palmer@icpl.org>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 * @todo provide option for autocheckout by default in config
 *       allow override for cover display using Overdrive covers
 *       provide option for not requiring email for holds
 *       provide option for giving users option for every hold
 *       provide option for asking about autocheckout for every hold
 *       provide config options for how to handle patrons with no access to OD
 *       look into storing collection token in application (object) cache
 *         instead of the users session.
 */
class OverdriveConnector implements LoggerAwareInterface, 
     AuthorizationServiceAwareInterface
{
    use \VuFind\Log\LoggerAwareTrait {
        logError as error;
    }
    use AuthorizationServiceAwareTrait;
    
    /**
     * Session Container
     * desc
     * @var string
     */
    protected $sessionContainer;

    /**
     * desc
     *
     * @var string
     */
    protected $recordConfig;

    /**
     * desc
     * @var string
     */
    protected $mainConfig;

    /**
     * desc
     * @var string
     */
    protected $ilsAuth;
    
    /**
     * Constructor
     *
     * @param \Zend\Config\Config $recordConfig   VuFind main configuration (omit for
     *                                            built-in defaults)
     *
     * @param \Zend\Config\Config $recordConfig   Record-specific configuration file
     *                                            (omit to use $recordConfig as $recordConfig)
     * @param \Zend\Config\Config $searchSettings Search-specific configuration file
     * @param Callable            $sessionFactory Factory function returning SessionContainer object
     */
    public function __construct(
        $mainConfig = null,
        $recordConfig = null,
        $sessManager,
        $ilsAuth
    ) {
        $this->debug("SolrOverdrive Connector");
        $this->mainConfig = $mainConfig;
        $this->recordConfig = $recordConfig;
        $this->ilsAuth = $ilsAuth;
        
        
        // Init session cache for session-specific data
        if ($conf=$this->getConfig()) {
            $namespace = md5("VuFind\DigitalContent");
            $this->sessionContainer = new Container($namespace);
        }
    }
    
    /**
     * Get (Logged-in) User
     *
     * Returns the currently logged in user or false if the user is not
     *
     * @since 5.0
     *
     * @return type Description.
     */ 
    public function getUser()
    {
        try {
            $user = $this->ilsAuth->storedCatalogLogin();
        } catch (ILSException $e) {
            return false;
        }
        return $user;
    }
        
    /**
     * Get Overdrive Access
     *
     * Whether the patron should have access to overdrive actions (hold, checkout etc.).
     * This is stored and retrieved from the session.
     *
     * @since 5.0
     *
     * @return bool Whether the logged-in user has access to Overdrive.
     */
    public function getAccess()
    {
        if (!$user = $this->getUser()) {
            return false;
        }
         
        $odAccess = $this->sessionContainer->odAccess;
        $this->debug("odaccess: $odAccess");
        if (empty($odAccess)) {
            if ($this->_connectToPatronAPI($user["cat_username"], 
                  $user["cat_password"], true)) {
                $this->sessionContainer->odAccess=true;
            } else {
                $this->sessionContainer->odAccess=false;
            }
        }
        $this->debug("odaccess: ".$this->sessionContainer->odAccess);
        return $this->sessionContainer->odAccess;
    }
    
    /**
     * Get Availability
     *
     * Retrieves the availability for a single resource from Overdrive API
     * with information like copiesOwned, copiesAvailable, numberOfHolds et.
     *
     * @link https://developer.overdrive.com/apis/library-availability-new
     * @since 5.0
     *
     * @param  str $overDriveId The Overdrive ID (reserve ID) of the eResource
     * @return obj  Standard object with availability info
     */
    public function getAvailability($overDriveId)
    {
        $res = false;
        if (!$overDriveId) {
            $this->logWarning("no overdrive content ID was passed in.", ["getOverdriveAvailability"]);
            return false;
        }
        if ($conf=$this->getConfig()) {
            $productsKey = $this->getProductsKey();

            $baseUrl = $conf->discURL;
            $availabilityUrl = "$baseUrl/v2/collections/$productsKey/products/$overDriveId/availability";
            $res = $this->_callUrl($availabilityUrl);
        }
        return $res;
    }

    /**
     * Get Availability (in) Bulk
     *
     * Gets availability for up to 25 titles at once.  This is used by the
     * the ajax availability system
     *
     * @since 5.0
     *
     * @param  array $overDriveIds The Overdrive ID (reserve IDs) of the eResources
     * @return array see getAvailability
     
     * @todo if more tan 25 passed in, make multiple calls
     */
    public function getAvailabilityBulk($overDriveIds = array())
    {
        $res = false;
        if (count($overDriveIds)< 1) {
            $this->logWarning("no overdrive content ID was passed in.",
               ["getOverdriveAvailability"]);
            return false;
        }
        if ($conf=$this->getConfig()) {
            $productsKey = $this->getProductsKey();

            $baseUrl = $conf->discURL;
            $availabilityPath ="/v2/collections/$productsKey/availability?products=";
            $availabilityUrl = $baseUrl.$availabilityPath.implode(",", $overDriveIds);
            $res = $this->_callUrl($availabilityUrl);
        }
        return $res;
    }
    
    /**
     * Get Products Key
     *
     * Gets the products key for the Overdrive collection (also sometimes called the collection token.)
     * The collection token doesn't change much but according to the OD API docs
     * it could change and should be retrieved each session.  The token itself is returned but
     * it's also saved in the session and automatically returned. In the future, I'll move this
     * the object cache probably.
     *
     * @since 5.0
     *
     * @return type A collection token for the library's collection.
     */
    public function getProductsKey()
    {
        $collectionToken = $this->sessionContainer->collectionToken;
        $this->debug("using collectionToken: $collectionToken");
        if (empty($collectionToken)) {
            $conf=$this->getConfig();
            $baseUrl = $conf->discURL;
            $libraryID = $conf->libraryID;
            $libraryURL = "$baseUrl/v1/libraries/$libraryID";
            $res = $this->_callUrl($libraryURL);
            if ($res) {
                $collectionToken = $res->collectionToken;
                $this->sessionContainer->collectionToken = $collectionToken;
            } else {
                return false;
            }
        }
        return $collectionToken;
    }
    

    /**
     * Overdrive Checkout
     * Processes a request to checkout a title from Overdrive 
     * @todo use the logged in user instead of passing it in.
     * 
     * @param      $overDriveId
     * @param bool        $user
     *
     * @return array
     */
    public function doOverdriveCheckout($overDriveId, $user=false)
    {
        
        $this->debug("doOverdriveCheckout: overdriveID: ". $overDriveId);
        if (!$user) {
            $this->error("no user was passed in.");
        }
        if ($config=$this->getConfig()) {
            $url = $config->circURL . '/v1/patrons/me/checkouts';
            $params = array(
                'reserveId' => $overDriveId,
            );
            
            $response = $this->_callPatronUrl($user["cat_username"], $user["cat_password"], $url, $params);
            $holdResult = array();
            $holdResult['result'] = false;
            $holdResult['message'] = '';
            
            if (!empty($response)) {
                if (isset($response->reserveId)) {
                    $expires = "";
                    if ($dt = new \DateTime($response->expires)) {
                        $expires = $dt->format((string)$config->displayDateFormat);
                    }
                    $holdResult['result'] = true;
                    $holdResult['data']['expires'] = $expires;
                } else {
                    //todo: translate
                    $holdResult['message'] = '<i class=\'fa fa-exclamation-triangle\'></i>Sorry, but we could not check this title out for you.  ' . $response->message;
                }
            } else {
                //todo: translate
                $holdResult['message'] = 'There was an unexpected error while connecting to Overdrive.';
            }
        }
        return $holdResult;
    }
        
    /**
     * Places a hold on an item within OverDrive
     *
     * @param string $overDriveId
     * @param int    $format
     * @param User   $user
     *
     * @return array (result, message)
     */
    public function placeOverDriveHold($overDriveId, $user=false, $email)
    {
        $this->debug("placeOverdriveHold");
        $this->debug(print_r($user, true));
        
        if ($config=$this->getConfig()) {
            //TODO: Make this a configuration option
            $autoCheckout = true;
            $ignoreHoldEmail = false;
            $url = $config->circURL . '/v1/patrons/me/holds';
            $params = array(
                'reserveId' => $overDriveId,
                'emailAddress' => $email,
                'autoCheckout' => $autoCheckout,
                'ignoreHoldEmail' => $ignoreHoldEmail,
            );
            
            $response = $this->_callPatronUrl($user["cat_username"], 
                                $user["cat_password"], $url, $params);
            $holdResult = array();
            $holdResult['result'] = false;
            $holdResult['message'] = '';
            
            if (!empty($response)) {
                if (isset($response->holdListPosition)) {
                    $holdResult['result'] = true;
                    $holdResult['data']['holdListPosition'] = $response->holdListPosition;
                } else {
                    $holdResult['data']['moreInfo'] = $response->message;
                }
            } else {
                //todo: translate
                $holdResult['message'] = 'There was an unexpected error while connecting to Overdrive.';
            }
        }
        return $holdResult;
    }

    /**
     *
     * cancelHold
     *
     * @param $resourceID
     * @param bool       $user
     * @param $email
     *
     * @return array
     */
    public function cancelHold($resourceID, $user=false)
    {
        $holdResult = new OverdriveResult();
        $this->debug("OverdriveConnector: cancelHold");
        //$this->debug(print_r($user,true));
        if (!$user) {
            $this->error("no user passed in", false, true);
            return $holdResult;
        }
        if ($config=$this->getConfig()) {
            $url = $config->circURL . "/v1/patrons/me/holds/$resourceID";
            $response = $this->_callPatronUrl($user["cat_username"], $user["cat_password"], $url, null, "DELETE");

            //because this is a DELETE Call, we are just looking for a boolean
            if ($response) {
                $holdResult->status = true;
            } else {
                $holdResult->msg = $response->message;
            }
        }
        return $holdResult;
    }

    
    /**
     *
     * Return Resource
     *
     * @param $resourceID
     * @param bool       $user
     * @param $email
     *
     * @return array
     *
     * DELETE https://patron.api.overdrive.com/v1/patrons/me/checkouts/08F7D7E6-423F-45A6-9A1E-5AE9122C82E7
     */
    public function returnResource($resourceID)
    {
        $result = new OverdriveResult();
        $this->debug("OverdriveConnector: returnResource");
        //$this->debug(print_r($user,true));
        if (!$user = $this->getUser()) {
            $this->error("user is not logged in", false, true);
            return $result;
        }
        if ($config=$this->getConfig()) {
            $url = $config->circURL . "/v1/patrons/me/checkouts/$resourceID";
            $response = $this->_callPatronUrl($user["cat_username"], $user["cat_password"], $url, null, "DELETE");

            //because this is a DELETE Call, we are just looking for a boolean
            if ($response) {
                $result->status = true;
            } else {
                $result->msg = $response->message;
            }
        }
        return $result;
    }
    

    /**
     * @return bool|\stdClass
     */
    public function getConfig()
    {
        $conf = new \stdClass();
        if (!$this->recordConfig) {
            $this->error("Could not locate the Overdrive Record Driver configuration.");
            return false;
        }
        if ($this->recordConfig->API->productionMode==false) {
            $conf->discURL = $this->recordConfig->API->integrationDiscoveryURL;
            $conf->circURL = $this->recordConfig->API->integrationCircURL;
            $conf->libraryID = $this->recordConfig->API->integrationLibraryID;
            $conf->websiteID = $this->recordConfig->API->integrationWebsiteID;
        } else {
            $conf->discURL = $this->recordConfig->API->productionDiscoveryURL;
            $conf->circURL = $this->recordConfig->API->productionCircURL;
            $conf->libraryID = $this->recordConfig->API->productionLibraryID;
            $conf->websiteID = $this->recordConfig->API->productionWebsiteID;
        }

        $conf->clientKey =  $this->recordConfig->API->clientKey;
        $conf->clientSecret  =  $this->recordConfig->API->clientSecret;
        $conf->tokenURL  =  $this->recordConfig->API->tokenURL;
        $conf->idField = $this->recordConfig->Overdrive->overdriveIdMarcField;
        $conf->idSubfield = $this->recordConfig->Overdrive->overdriveIdMarcSubfield;
        $conf->ILSname = $this->recordConfig->API->ILSname;
        //TODO
        $conf->isMarc = false;
        $conf->displayDateFormat = $this->mainConfig->Site->displayDateFormat;
        //$this->debug("OD Record driver config: ".print_r($this->recordConfig,true));
        return $conf;
    }
    
    /**
     * Returns a hash of metadata keyed on overdrive reserveID
     *
     * @param array $overDriveIds
     *
     * @return array ()
     */
    public function getMetadata($overdriveIDs = array())
    {
        //example URL:
        //https://api.overdrive.com/v1/collections/v1L1BYwAAAA2Q/bulkmetadata?reserveIds=33312cf6-2696-4aae-9b25-59a28874b6e6,679b3696-ef6f-4738-b4b3-363c6b096012
        $res = false;
        $metadata = array();
        if (!$overdriveIDs || count($overdriveIDs)<1) {
            $this->logWarning("no overdrive content IDs waere passed in.", ["getMetadata"]);
            return array();
        }
        if ($conf=$this->getConfig()) {
            $productsKey = $this->getProductsKey();
            $baseUrl = $conf->discURL;
            $metadataUrl = "$baseUrl/v1/collections/$productsKey/bulkmetadata?reserveIds=".implode(",", $overdriveIDs);
            $res = $this->_callUrl($metadataUrl);
            $md = $res->metadata;
            foreach ($md as $item) {
                $metadata[$item->id] = $item;
            }
        }
        return $metadata;
    }


    /**
     * Get Overdrive Checkouts (or a user)
     * @param      $user
     * @param bool $refresh
     * @param bool $withMetadata
     * @todo use the logged in user
     * @return OverdriveResult
     */
    public function getCheckouts($user, $refresh=true, $withMetadata=false)
    {

        //the checkouts are cached in the session, but we can force a refresh
        $this->debug("get Overdrive Checkouts");
        // $this->debug(print_r($user,true));
        $result = new OverdriveResult();
        
        if (!$user = $this->getUser()) {
            $this->error("user is not logged in");
            return $result;
        }
        
        $checkouts = $this->sessionContainer->checkouts;
        if (!$checkouts || $refresh) {
            if ($config=$this->getConfig()) {
                $url = $config->circURL . '/v1/patrons/me/checkouts';

                $response = $this->_callPatronUrl($user["cat_username"], 
                   $user["cat_password"], $url, false);

                if (!empty($response)) {
                    $result->status = true;
                    $result->message = '';
                    $result->data = $response->checkouts;
                    //Convert dates to desired format
                    foreach ($response->checkouts as $key=>$checkout) {
                        $coExpires = new \DateTime($checkout->expires);
                        $result->data[$key]->expires = $coExpires->format($config->displayDateFormat);
                        $result->data[$key]->isReturnable = !$checkout->isFormatLockedIn;
                    }
                        
                    $this->sessionContainer->checkouts = $response->checkouts;
                } else {
                    $result->message = 'There was an unexpected error while connecting to Overdrive.';
                }
            }
        } else {
            $result->status = true;
            $result->msg = [];
            $result->data = $this->sessionContainer->checkouts;
        }
        return $result;
    }
   
    /**
     * Get Overdrive Holds (or a user)
     * @param      $user
     * @param bool $refresh

     * @todo use the logged in user
     * @return OverdriveResult
     */    
    public function getHolds($user, $refresh=true)
    {

        //the checkouts are cached in the session, but we can force a refresh
        //
        $this->debug("get Overdrive Holds");
        // $this->debug(print_r($user,true));
        $result = new OverdriveResult();
        $holds = $this->sessionContainer->holds;
        if (!$holds || $refresh) {
            if ($config=$this->getConfig()) {
                $url = $config->circURL . '/v1/patrons/me/holds';

                $response = $this->_callPatronUrl($user["cat_username"], $user["cat_password"], $url, $params);

                $result->status = false;
                $result->message = '';
                
                if (!empty($response)) {
                    $result->status = true;
                    $result->message = 'hold_place_success_html';
                    $result->data = $response->holds;
                    //Check for holds ready for chechout
                    foreach ($response->holds as $key=>$hold) {
                        if (!$hold->autoCheckout && holdListPosition==1) {
                            $result->data[$key]->holdReadyForCheckout = true;
                            //format the expires date.
                            $holdExpires = new \DateTime($hold->holdExpires);
                            $result->data[$key]->holdExpires = $holdExpires->format((string)$config->displayDateFormat);
                        }
                        $holdPlacedDate = new \DateTime($hold->holdPlacedDate);
                        $result->data[$key]->holdPlacedDate = $holdPlacedDate->format((string)$config->displayDateFormat);
                    }
                    $this->sessionContainer->holds = $response->holds;
                } else {
                    $result->message = 'There was an unexpected error while connecting to Overdrive.';
                }
            }
        } else {
            $this->debug("found Overdrive Holds in cache");
            $result->status = true;
            $result->message = [];
            $result->data = $this->sessionContainer->holds;
        }
        return $result;
    }
    
    /**
     * Connect to API
     * 
     * @param bool force a new connection (get a new token)
     * @return string token for the session
     */
    protected function _connectToAPI($forceNewConnection = false)
    {
        $conf = $this->getConfig();
        $tokenData = $this->sessionContainer->tokenData;
        if ($forceNewConnection || $tokenData == null || time() >= $tokenData->expirationTime) {
            $authHeader = base64_encode($conf->clientKey . ":" . $conf->clientSecret);
            $this->debug("tokenURL: ".$conf->tokenURL);
            $this->debug("authHeader: $authHeader");
            $ch = curl_init($conf->tokenURL);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
            curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1)");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded;charset=UTF-8', "Authorization: Basic $authHeader"));
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, "grant_type=client_credentials");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $return = curl_exec($ch);
            curl_close($ch);
            $tokenData = json_decode($return);
            $this->debug("return from OD API Call: ". print_r($tokenData, true));
            
            if ($tokenData != null) {
                if (isset($tokenData->error)) {
                    $this->error("Overdrive Token Error: ".$tokenData->error);
                    return false;
                } else {
                    $tokenData->expirationTime = time() + $tokenData->expires_in;
                    $this->sessionContainer->tokenData = $tokenData;
                }
            }
        }
        
        return $tokenData;
    }
    
    /**
     * Connect to Patron API
     * 
     * @param string barcode Patrons barcode
     * @param string patronPin Patrons password
     * @param bool force a new connection (get a new token)
     * @return string token for the session
     */
    protected function _connectToPatronAPI($patronBarcode, $patronPin = 1234, $forceNewConnection = false)
    {
        $patronTokenData = $this->sessionContainer->patronTokenData;
        $config = $this->getConfig();
        if ($forceNewConnection || $patronTokenData == null || time() >= $patronTokenData->expirationTime) {
            $this->debug("connecting to patron API for new token.");
            $ch = curl_init("https://oauth-patron.overdrive.com/patrontoken");
            $websiteId = $config->websiteID;
            $ilsname = $config->ILSname;

            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
            curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1)");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
            $encodedAuthValue = base64_encode($config->clientKey . ":" . $config->clientSecret);
            curl_setopt(
                $ch,
                CURLOPT_HTTPHEADER,
                array(
                'Content-Type: application/x-www-form-urlencoded;charset=UTF-8',
                "Authorization: Basic " . $encodedAuthValue,
                "User-Agent: VuFind-Plus"
                )
            );

            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_POST, 1);
            
            //grant_type=password&username=1234567890&password=1234&scope=websiteid:12345 authorizationname:default
            if ($patronPin == null) {
                $postFields = "grant_type=password&username={$patronBarcode}&password=ignore&password_required=false&scope=websiteId:{$websiteId}%20authorizationname:{$ilsname}";
            } else {
                $postFields = "grant_type=password&username={$patronBarcode}&password={$patronPin}&scope=websiteId:{$websiteId}%20authorizationname:{$ilsname}";
            }
            //$this->debug("postFields: $postFields");
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $return = curl_exec($ch);
            $curlInfo = curl_getinfo($ch);
            curl_close($ch);
            $patronTokenData = json_decode($return);
            $this->debug("return from OD patron API Call: ". print_r($patronTokenData, true));
            if (isset($patronTokenData->expires_in)) {
                $patronTokenData->expirationTime = time() + $patronTokenData->expires_in;
            } else {
                $patronTokenData = null;
            }
            $this->sessionContainer->patronTokenData = $patronTokenData;
        }
        return $patronTokenData;
    }
    
    /**
     * Call a URL on the API
     * 
     * @param string url the url to call
     *
     * @return string token for the session
     */
    protected function _callUrl($url)
    {
        if ($this->_connectToAPI()) {
            $tokenData = $this->sessionContainer->tokenData;
            $this->debug("url for OD API Call: $url");
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
            curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1)");
            curl_setopt($ch, CURLOPT_HTTPHEADER, array("Authorization: {$tokenData->token_type} {$tokenData->access_token}", "User-Agent: VuFind-Plus"));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $return = curl_exec($ch);
            $curlInfo = curl_getinfo($ch);
            $this->debug("curl Info: ".print_r($curlInfo, true));
            curl_close($ch);
            
            if ($curlInfo['http_code']!= 200 && $curlInfo['http_code']!= 204) {
                $this->error("Overdrive HTTP Error: ".$curlInfo['http_code']);
                return false;
            }
            $returnVal = json_decode($return);
            $this->debug("return value from OD API Call: ". print_r($returnVal, true));
            if ($returnVal != null) {
                if (isset($returnVal->errorCode)) {
                    $this->error("Overdrive Error: ".$returnVal->errorCode);
                    return false;
                } else {
                    return $returnVal;
                }
            } else {
                $this->error("Overdrive Error: Nothing returned from API call.");
            }
        }//if this didn't work, it should have generated an error in the logs above
        return false;
    }
    protected function _callPatronUrl($patronBarcode, $patronPin, $url, $params = null, $requestType = null)
    {
        $this->debug("calling patronURL: $url");
        if ($this->_connectToPatronAPI($patronBarcode, $patronPin, false)) {
            $patronTokenData = $this->sessionContainer->patronTokenData;
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
            curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1)");
            $authorizationData = $patronTokenData->token_type . ' ' . $patronTokenData->access_token;
            $headers = array(
                "Authorization: $authorizationData",
                "User-Agent: VuFind-Plus",
                "Content-Type: application/json"
            );
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            if ($requestType != null) {
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $requestType);
            }
            if ($params != null) {
                curl_setopt($ch, CURLOPT_POST, 1);
                //Convert post fields to json
                $jsonData = array('fields' => array());
                foreach ($params as $key => $value) {
                    $jsonData['fields'][] = array(
                        'name' => $key,
                        'value' => $value
                    );
                }
                $postData = json_encode($jsonData);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            } else {
                curl_setopt($ch, CURLOPT_HTTPGET, true);
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            
            $return = curl_exec($ch);
            $curlInfo = curl_getinfo($ch);
            curl_close($ch);
            
            $this->debug("curl Info: ".print_r($curlInfo, true));
            //if all goes well for DELETE, the code will be 204 and response is empty.
            if ($requestType=="DELETE") { 
                if($curlInfo['http_code'] == 204) {
                    $this->debug("DELETE Patron call appears to have worked.");
                    return true;
                } else {
                    $this->error("DELETE Patron call failed. HTTP return code: ".$curlInfo['http_code']);
                    return false;
                }
            }
            
            $returnVal = json_decode($return);
            $this->debug("response from call: ".print_r($returnVal, true));
            if ($returnVal != null) {
                if (!isset($returnVal->message) 
                    || $returnVal->message != 'An unexpected error has occurred.') {
                    return $returnVal;
                }
            } else {
                return $result;
            }
        } else {
            $this->Error("not connected to Patron API");
        }
        return false;
    }
}

/*
Placeholder for ID prefix stuff. not sure if I need this

    private function _stripPrefixes($ids)
    {
        $result = array();
        foreach ($ids as &$id) {
            $result[]= $this->_stripPrefix($id);
        }
        return $result;
    }
    private function _stripPrefix($id)
    {
        //TODO SET prefix in CONFIG
        $prefix = 'overdrive.';
        return substr($id, strlen($prefix));
    }
*/

/**
 * Class OverdriveResult
 *
 * @package VuFind\DigitalContent
 */
class OverdriveResult
{
    /**
     * @var bool
     */
    public $status = false;
    /**
     * @var string
     */
    public $msg = "";
    /**
     * @var mixed
     */
    public $data = false;
}
