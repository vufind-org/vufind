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
 * @package  DigitalContent
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Brent Palmer <brent-palmer@icpl.org>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\DigitalContent;


use Zend\Log\LoggerAwareInterface;

use ZfcRbac\Service\AuthorizationServiceAwareInterface;
use ZfcRbac\Service\AuthorizationServiceAwareTrait;


//remove outside calls requring user
//migrate to result obj instead of array


/**
 * OverdriveConnector
 *
 * Class responsible for connecting to the Overdrive API
 *
 * @category VuFind
 * @package  DigitalContent
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Brent Palmer <brent-palmer@icpl.org>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 * @todo     provide option for autocheckout by default in config
 *       allow override for cover display using Overdrive covers
 *       provide option for not requiring email for holds
 *       provide option for giving users option for every hold
 *       provide option for asking about autocheckout for every hold
 *       provide config options for how to handle patrons with no access to OD
 *       look into storing collection token in application (object) cache
 *         instead of the users session.
 */
class OverdriveConnector implements LoggerAwareInterface,
    AuthorizationServiceAwareInterface, \VuFindHttp\HttpServiceAwareInterface
{
    use \VuFind\Log\LoggerAwareTrait {
        logError as error;
    }
    use AuthorizationServiceAwareTrait;
    use \VuFindHttp\HttpServiceAwareTrait;

    /**
     * Session Container
     *
     * @var \Zend\Session\Container
     */
    protected $sessionContainer;

    /**
     * Record Config
     *
     * Main configurations
     *
     * @var \Zend\Config\Config
     */
    protected $recordConfig;

    /**
     * Record Config
     *
     * Overdrive configurations
     *
     * @var \Zend\Config\Config
     */
    protected $mainConfig;

    /**
     * ILS Authorization
     *
     * @var \VuFind\Auth\ILSAuthenticator
     */
    protected $ilsAuth;

    /**
     * HTTP Client
     *
     * Client for making calls to the API
     *
     * @var  \Zend\Http\Client
     */
    protected $client;

    /**
     * Constructor
     *
     * @param \Zend\Config\Config            $mainConfig       VuFind main conf
     * @param \Zend\Config\Config            $recordConfig     Record-specific conf file
     * @param \Zend\Session\Container        $sessionContainer Session container
     * @param  \VuFind\Auth\ILSAuthenticator $ilsAuth          ILS Authenticator
     */
    public function __construct(
        $mainConfig,
        $recordConfig,
        $sessionContainer,
        $ilsAuth
    )
    {
        $this->debug("SolrOverdrive Connector");
        $this->mainConfig = $mainConfig;
        $this->recordConfig = $recordConfig;
        $this->sessionContainer = $sessionContainer;
        $this->ilsAuth = $ilsAuth;
    }

    /**
     * Get (Logged-in) User
     *
     * Returns the currently logged in user or false if the user is not
     *
     * @since 5.0
     *
     * @return array|boolean  an array of user info from the ILSAuthenticator
     *                        or false if user is not logged in.
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
     * Whether the patron should have access to overdrive actions (hold,
     * checkout etc.).
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
            if ($this->connectToPatronAPI(
                $user["cat_username"],
                $user["cat_password"], true
            )
            ) {
                $this->sessionContainer->odAccess = true;
            } else {
                $this->sessionContainer->odAccess = false;
            }
        }
        $this->debug("odaccess: " . $this->sessionContainer->odAccess);
        return $this->sessionContainer->odAccess;
    }

    /**
     * Get Availability
     *
     * Retrieves the availability for a single resource from Overdrive API
     * with information like copiesOwned, copiesAvailable, numberOfHolds et.
     *
     *
     * @param string $overDriveId The Overdrive ID (reserve ID) of the eResource
     *
     * @return object|bool  Standard object with availability info
     *
     * @link  https://developer.overdrive.com/apis/library-availability-new
     * @since 5.0
     */
    public function getAvailability($overDriveId)
    {
        $res = false;
        if (!$overDriveId) {
            $this->logWarning("no overdrive content ID was passed in.");
            return false;
        }
        if ($conf = $this->getConfig()) {
            $productsKey = $this->getProductsKey();

            $baseUrl = $conf->discURL;
            $availabilityUrl = "$baseUrl/v2/collections/$productsKey/products/";
            $availabilityUrl .= "$overDriveId/availability";
            $res = $this->callUrl($availabilityUrl);
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
     *
     * @return array|bool see getAvailability
     *
     * @todo  if more tan 25 passed in, make multiple calls
     */
    public function getAvailabilityBulk($overDriveIds = array())
    {
        $res = false;
        if (count($overDriveIds) < 1) {
            $this->logWarning("no overdrive content ID was passed in.");
            return false;
        }
        if ($conf = $this->getConfig()) {
            $productsKey = $this->getProductsKey();

            $baseUrl = $conf->discURL;
            $availabilityPath = "/v2/collections/";
            $availabilityPath .= "$productsKey/availability?products=";
            $availabilityUrl = $baseUrl . $availabilityPath .
                implode(",", $overDriveIds);
            $res = $this->callUrl($availabilityUrl);
        }
        return $res;
    }

    /**
     * Get Products Key
     *
     * Gets the products key for the Overdrive collection (also sometimes called the
     * collection token.) The collection token doesn't change much but according to
     * the OD API docs it could change and should be retrieved each session.
     * The token itself is returned but it's also saved in the session and
     * automatically returned. In the future, I'll move this the object cache.
     *
     * @return object|bool A collection token for the library's collection.
     */
    public function getProductsKey()
    {
        $collectionToken = $this->sessionContainer->collectionToken;
        $this->debug("collectionToken from session: $collectionToken");
        if (empty($collectionToken)) {
            $this->debug("getting new collectionToken");
            $conf = $this->getConfig();
            $baseUrl = $conf->discURL;
            $libraryID = $conf->libraryID;
            $libraryURL = "$baseUrl/v1/libraries/$libraryID";
            $res = $this->callUrl($libraryURL);
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
     *
     * @param  string $overDriveId The overdrive id for the title
     *
     * @return object $result Results of the call.
     */
    public function doOverdriveCheckout($overDriveId)
    {
        $result = $this->getResultObject();

        $this->debug("doOverdriveCheckout: overdriveID: " . $overDriveId);
        if (!$user = $this->getUser()) {
            $this->error("user is not logged in", false, true);
            return $result;
        }
        if ($config = $this->getConfig()) {
            $url = $config->circURL . '/v1/patrons/me/checkouts';
            $params = array(
                'reserveId' => $overDriveId,
            );

            $response = $this->callPatronUrl(
                $user["cat_username"],
                $user["cat_password"], $url, $params, "POST"
            );
            $result = array();
            $result['result'] = false;
            $result['message'] = '';

            if (!empty($response)) {
                if (isset($response->reserveId)) {
                    $expires = "";
                    if ($dt = new \DateTime($response->expires)) {
                        $expires = $dt->format((string)$config->displayDateFormat);
                    }
                    $result['result'] = true;
                    $result['data']['expires'] = $expires;
                } else {
                    //todo: translate
                    $result['message']
                        = '<i class=\'fa fa-exclamation-triangle\'></i>Sorry, but we could not check this title out for you.  '
                        . $response->message;
                }
            } else {
                //todo: translate
                $result['message']
                    = 'There was an unexpected error while connecting to Overdrive.';
            }
        }
        return $result;
    }

    /**
     * Places a hold on an item within OverDrive
     *
     * @param string $overDriveId The overdrive id for the title
     * @param string email
     *
     * @return \stdClass Object with result
     */
    public function placeOverDriveHold($overDriveId, $email)
    {
        $this->debug("placeOverdriveHold");
        $holdResult = $this->getResultObject();
        if (!$user = $this->getUser()) {
            $this->error("user is not logged in", false, true);
            return $holdResult;
        }

        if ($config = $this->getConfig()) {
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

            $response = $this->callPatronUrl(
                $user["cat_username"],
                $user["cat_password"],
                $url,
                $params,
                "POST"
            );

            if (!empty($response)) {
                if (isset($response->holdListPosition)) {
                    $holdResult->status = true;
                    $holdResult->data->holdListPosition
                        = $response->holdListPosition;
                } else {
                    $holdResult->data->moreInfo = $response->message;
                }
            } else {
                //todo: translate
                $holdResult['message']
                    = 'There was an unexpected error while connecting to Overdrive.';
            }
        }
        return $holdResult;
    }

    /**
     * Cancel Hold
     * Cancel and existing Overdrive Hold
     *
     * @param  string $overDriveId The overdrive id for the title
     *
     * @return \stdClass
     */
    public function cancelHold($overDriveId)
    {
        $holdResult = $this->getResultObject();
        $this->debug("OverdriveConnector: cancelHold");
        //$this->debug(print_r($user,true));
        if (!$user = $this->getUser()) {
            $this->error("user is not logged in", false, true);
            return $holdResult;
        }
        if ($config = $this->getConfig()) {
            $url = $config->circURL . "/v1/patrons/me/holds/$overDriveId";
            $response = $this->callPatronUrl(
                $user["cat_username"], $user["cat_password"], $url, null, "DELETE"
            );

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
     * Return Resource
     *
     * @param      $resourceID
     *
     * @return object|bool
     */
    public function returnResource($resourceID)
    {
        $result = $this->getResultObject();
        $this->debug("OverdriveConnector: returnResource");
        //$this->debug(print_r($user,true));
        if (!$user = $this->getUser()) {
            $this->error("user is not logged in", false, true);
            return $result;
        }
        if ($config = $this->getConfig()) {
            $url = $config->circURL . "/v1/patrons/me/checkouts/$resourceID";
            $response = $this->callPatronUrl(
                $user["cat_username"],
                $user["cat_password"], $url, null, "DELETE"
            );

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
     * Get Configuration
     * Sets up a local copy of configurations for convenience
     *
     * @return bool|\stdClass
     */
    public function getConfig()
    {
        $conf = new \stdClass();
        if (!$this->recordConfig) {
            $this->error(
                "Could not locate the Overdrive Record Driver "
                . "configuration."
            );
            return false;
        }
        if ($this->recordConfig->API->productionMode == false) {
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

        $conf->clientKey = $this->recordConfig->API->clientKey;
        $conf->clientSecret = $this->recordConfig->API->clientSecret;
        $conf->tokenURL = $this->recordConfig->API->tokenURL;
        $conf->patronTokenURL = $this->recordConfig->API->patronTokenURL;
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
     * @param array $overdriveIDs
     *
     * @return array ()
     */
    public function getMetadata($overdriveIDs = array())
    {
        $metadata = array();
        if (!$overdriveIDs || count($overdriveIDs) < 1) {
            $this->logWarning("no overdrive content IDs waere passed in.");
            return array();
        }
        if ($conf = $this->getConfig()) {
            $productsKey = $this->getProductsKey();
            $baseUrl = $conf->discURL;
            $metadataUrl = "$baseUrl/v1/collections/$productsKey/";
            $metadataUrl .= "bulkmetadata?reserveIds=" . implode(",", $overdriveIDs);
            $res = $this->callUrl($metadataUrl);
            $md = $res->metadata;
            foreach ($md as $item) {
                $metadata[$item->id] = $item;
            }
        }
        return $metadata;
    }


    /**
     * Get Overdrive Checkouts (or a user)
     *
     * @param  bool $refresh
     * @param  bool $withMetadata
     *
     * @return object Results of the call
     * @todo   use the logged in user
     */
    public function getCheckouts($refresh = true, $withMetadata = false)
    {

        //the checkouts are cached in the session, but we can force a refresh
        $this->debug("get Overdrive Checkouts");
        // $this->debug(print_r($user,true));
        $result = $this->getResultObject();

        if (!$user = $this->getUser()) {
            $this->error("user is not logged in");
            return $result;
        }

        $checkouts = $this->sessionContainer->checkouts;
        if (!$checkouts || $refresh) {
            if ($config = $this->getConfig()) {
                $url = $config->circURL . '/v1/patrons/me/checkouts';

                $response = $this->callPatronUrl(
                    $user["cat_username"],
                    $user["cat_password"], $url, false
                );

                if (!empty($response)) {
                    $result->status = true;
                    $result->message = '';
                    $result->data = $response->checkouts;
                    //Convert dates to desired format
                    foreach ($response->checkouts as $key => $checkout) {
                        $coExpires = new \DateTime($checkout->expires);
                        $result->data[$key]->expires = $coExpires->format(
                            $config->displayDateFormat
                        );
                        $result->data[$key]->isReturnable
                            = !$checkout->isFormatLockedIn;
                    }

                    $this->sessionContainer->checkouts = $response->checkouts;
                } else {
                    $result->message
                        = 'There was an unexpected error while connecting to Overdrive.';
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
     *
     * @param bool $refresh
     *
     * @return \stdClass Results of the call
     * @todo   use the logged in user
     */
    public function getHolds($refresh = true)
    {
        $this->debug("get Overdrive Holds");
        $result = $this->getResultObject();
        if (!$user = $this->getUser()) {
            $this->error("user is not logged in");
            return $result;
        }

        $holds = $this->sessionContainer->holds;
        if (!$holds || $refresh) {
            if ($config = $this->getConfig()) {
                $url = $config->circURL . '/v1/patrons/me/holds';

                $response = $this->callPatronUrl(
                    $user["cat_username"],
                    $user["cat_password"], $url
                );

                $result->status = false;
                $result->message = '';

                if (!empty($response)) {
                    $result->status = true;
                    $result->message = 'hold_place_success_html';
                    $result->data = $response->holds;
                    //Check for holds ready for chechout
                    foreach ($response->holds as $key => $hold) {
                        if (!$hold->autoCheckout && $hold->holdListPosition == 1) {
                            $result->data[$key]->holdReadyForCheckout = true;
                            //format the expires date.
                            $holdExpires = new \DateTime($hold->holdExpires);
                            $result->data[$key]->holdExpires = $holdExpires->format(
                                (string)$config->displayDateFormat
                            );
                        }
                        $holdPlacedDate = new \DateTime($hold->holdPlacedDate);
                        $result->data[$key]->holdPlacedDate
                            = $holdPlacedDate->format(
                            (string)$config->displayDateFormat
                        );
                    }
                    $this->sessionContainer->holds = $response->holds;
                } else {
                    $result->message
                        = 'There was an unexpected error while connecting to Overdrive.';
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
     * @param  bool force a new connection (get a new token)
     *
     * @return string token for the session
     *
     * protected function _connectToAPI($forceNewConnection = false)
     * {
     * $conf = $this->getConfig();
     * $tokenData = $this->sessionContainer->tokenData;
     * if ($forceNewConnection || $tokenData == null || time() >= $tokenData->expirationTime) {
     * $authHeader = base64_encode($conf->clientKey . ":" . $conf->clientSecret);
     * $this->debug("tokenURL: ".$conf->tokenURL);
     * $this->debug("authHeader: $authHeader");
     * $ch = curl_init($conf->tokenURL);
     * curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
     * curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1)");
     * curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
     * curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
     * curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
     * curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded;charset=UTF-8', "Authorization: Basic $authHeader"));
     * curl_setopt($ch, CURLOPT_TIMEOUT, 30);
     * curl_setopt($ch, CURLOPT_POST, 1);
     * curl_setopt($ch, CURLOPT_POSTFIELDS, "grant_type=client_credentials");
     * curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
     * $return = curl_exec($ch);
     * curl_close($ch);
     * $tokenData = json_decode($return);
     * $this->debug("return from OD API Call: ". print_r($tokenData, true));
     *
     * if ($tokenData != null) {
     * if (isset($tokenData->error)) {
     * $this->error("Overdrive Token Error: ".$tokenData->error);
     * return false;
     * } else {
     * $tokenData->expirationTime = time() + $tokenData->expires_in;
     * $this->sessionContainer->tokenData = $tokenData;
     * }
     * }
     * }
     *
     * return $tokenData;
     * }
     */


    /**
     * Connect to Patron API
     *
     * @param  string $barcode            Patrons barcode
     * @param  string $patronPin          Patrons password
     * @param  bool   $forceNewConnection Force a new connection (get a new token)
     *
     * @return string token for the session
     *
     * protected function _connectToPatronAPI($patronBarcode, $patronPin = 1234, $forceNewConnection = false)
     * {
     * $patronTokenData = $this->sessionContainer->patronTokenData;
     * $config = $this->getConfig();
     * if ($forceNewConnection || $patronTokenData == null || time() >= $patronTokenData->expirationTime) {
     * $this->debug("connecting to patron API for new token.");
     * $ch = curl_init("https://oauth-patron.overdrive.com/patrontoken");
     * $websiteId = $config->websiteID;
     * $ilsname = $config->ILSname;
     *
     * curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
     * curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1)");
     * curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
     * curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
     * curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
     * $encodedAuthValue = base64_encode($config->clientKey . ":" . $config->clientSecret);
     * curl_setopt(
     * $ch,
     * CURLOPT_HTTPHEADER,
     * array(
     * 'Content-Type: application/x-www-form-urlencoded;charset=UTF-8',
     * "Authorization: Basic " . $encodedAuthValue,
     * "User-Agent: VuFind-Plus"
     * )
     * );
     *
     * curl_setopt($ch, CURLOPT_TIMEOUT, 30);
     * curl_setopt($ch, CURLOPT_POST, 1);
     *
     * //grant_type=password&username=1234567890&password=1234&scope=websiteid:12345 authorizationname:default
     * if ($patronPin == null) {
     * $postFields = "grant_type=password&username={$patronBarcode}&password=ignore&password_required=false&scope=websiteId:{$websiteId}%20authorizationname:{$ilsname}";
     * } else {
     * $postFields = "grant_type=password&username={$patronBarcode}&password={$patronPin}&scope=websiteId:{$websiteId}%20authorizationname:{$ilsname}";
     * }
     * //$this->debug("postFields: $postFields");
     * curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
     * curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
     * $return = curl_exec($ch);
     * $curlInfo = curl_getinfo($ch);
     * curl_close($ch);
     * $patronTokenData = json_decode($return);
     * $this->debug("return from OD patron API Call: ". print_r($patronTokenData, true));
     * if (isset($patronTokenData->expires_in)) {
     * $patronTokenData->expirationTime = time() + $patronTokenData->expires_in;
     * } else {
     * $patronTokenData = null;
     * }
     * $this->sessionContainer->patronTokenData = $patronTokenData;
     * }
     * return $patronTokenData;
     * }
     */

    /**
     * Call a URL on the API
     *
     * @param string url the url to call
     *
     * @return object The json response from the API call
     *  converted to an object.  If the call fails at the
     *  HTTP level then the error is logged and false is returned.
     *
     * protected function _callUrl($url)
     * {
     * if ($this->_connectToAPI()) {
     * $tokenData = $this->sessionContainer->tokenData;
     * $this->debug("url for OD API Call: $url");
     * $ch = curl_init($url);
     * curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
     * curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1)");
     * curl_setopt($ch, CURLOPT_HTTPHEADER, array("Authorization: {$tokenData->token_type} {$tokenData->access_token}", "User-Agent: VuFind-Plus"));
     * curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
     * curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
     * curl_setopt($ch, CURLOPT_TIMEOUT, 30);
     * curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
     * $return = curl_exec($ch);
     * $curlInfo = curl_getinfo($ch);
     * $this->debug("curl Info: ".print_r($curlInfo, true));
     * curl_close($ch);
     *
     * if ($curlInfo['http_code']!= 200 && $curlInfo['http_code']!= 204) {
     * $this->error("Overdrive HTTP Error: ".$curlInfo['http_code']);
     * return false;
     * }
     * $returnVal = json_decode($return);
     * $this->debug("return value from OD API Call: ". print_r($returnVal, true));
     * if ($returnVal != null) {
     * if (isset($returnVal->errorCode)) {
     * $this->error("Overdrive Error: ".$returnVal->errorCode);
     * return false;
     * } else {
     * return $returnVal;
     * }
     * } else {
     * $this->error("Overdrive Error: Nothing returned from API call.");
     * }
     * }//if this didn't work, it should have generated an error in the logs above
     * return false;
     * }
     */

    /**
     * Call a URL on the API
     *
     * @param string $url        The url to call
     * @param array  $headers    Headers to set for the request.
     *                           if null, then the auth headers are used.
     * @param bool   $checkToken Whether to check and get a new token
     *
     * @param string $requestType
     *
     * @return object|bool The json response from the API call
     *  converted to an object.  If the call fails at the
     *  HTTP level then the error is logged and false is returned.
     */
    protected function callUrl(
        $url, $headers = null, $checkToken = true, $requestType = "GET"
    )
    {
        $this->debug("chktoken: $checkToken");
        if (!$checkToken || $this->connectToAPI()) {
            $tokenData = $this->sessionContainer->tokenData;
            $this->debug("url for OD API Call: $url");
            try {
                $client = $this->getHttpClient($url);
            } catch (\Exception $e) {
                $this->error(
                    "error while setting up the client: " . $e->getMessage()
                );
                return false;
            }
            if ($headers === null) {
                $headers = array(
                    "Authorization: {$tokenData->token_type} {$tokenData->access_token}",
                    "User-Agent: VuFind"
                );
            }
            $client->setHeaders($headers);
            $client->setMethod($requestType);
            $response = $client->setUri($url)->send();

            if ($response->isServerError()) {
                $this->error(
                    "Overdrive HTTP Error: " .
                    $response->getStatusCode()
                );
                $this->debug("Request: " . $client->getRequest());
                return false;
            }

            $body = $response->getBody();
            $returnVal = json_decode($body);
            $this->debug("Return from OD API Call: " . print_r($returnVal, true));
            if ($returnVal != null) {
                if (isset($returnVal->errorCode)) {
                    //In some cases, this should be returned perhaps...
                    $this->error("Overdrive Error: " . $returnVal->errorCode);
                    return false;
                } else {
                    return $returnVal;
                }
            } else {
                $this->error("Overdrive Error: Nothing returned from API call.");
                $this->debug(
                    "Body return from OD API Call: " . print_r($body, true)
                );
            }
        }
        $this->debug("here");
        return false;
    }

    /**
     * Connect to API
     *
     * @param  bool $forceNewConnection Force a new connection (get a new token)
     *
     * @return string token for the session or false
     *     if the token request failed
     */
    protected function connectToAPI($forceNewConnection = false)
    {
        $this->debug("connecting to API");
        $conf = $this->getConfig();
        $tokenData = $this->sessionContainer->tokenData;
        $this->debug("API Token from session: " . print_r($tokenData, true));
        if ($forceNewConnection || $tokenData == null
            || !isset($tokenData->access_token)
            || time() >= $tokenData->expirationTime
        ) {
            $authHeader = base64_encode(
                $conf->clientKey . ":" . $conf->clientSecret
            );
            $headers = array(
                'Content-Type: application/x-www-form-urlencoded;charset=UTF-8',
                "Authorization: Basic $authHeader"
            );

            try {
                $client = $this->getHttpClient();
            } catch (\Exception $e) {
                $this->error(
                    "error while setting up the client: " . $e->getMessage()
                );
                return false;
            }
            $client->setHeaders($headers);
            $client->setMethod("POST");
            $client->setRawBody("grant_type=client_credentials");
            $response = $client->setUri($conf->tokenURL)->send();

            if ($response->isServerError()) {
                $this->error(
                    "Overdrive HTTP Error: " .
                    $response->getStatusCode()
                );
                $this->debug("Request: " . $client->getRequest());
                return false;
            }

            $body = $response->getBody();
            $tokenData = json_decode($body);
            $this->debug(
                "TokenData returned from OD API Call: " . print_r($tokenData, true)
            );
            if ($tokenData != null) {
                if (isset($tokenData->errorCode)) {
                    //In some cases, this should be returned perhaps...
                    $this->error("Overdrive Error: " . $tokenData->errorCode);
                    return false;
                } else {
                    $tokenData->expirationTime = time() + $tokenData->expires_in;
                    $this->sessionContainer->tokenData = $tokenData;
                    return $tokenData;
                }
            } else {
                $this->error("Overdrive Error: Nothing returned from API call.");
                $this->debug(
                    "Body return from OD API Call: " . print_r($body, true)
                );
            }
        }
        return $tokenData;
    }


    /**
     * Call a Patron URL on the API
     *
     * The patron URL is used for the circulation API's and requires a patron
     * specific token.
     *
     * @param string $patronBarcode Patrons barcode
     * @param string $patronPin     Patrons password
     * @param string $url           The url to call
     * @param array  $params        parameters to call
     * @param string $requestType   HTTP request type (default=GET)
     *
     * @return object|bool The json response from the API call
     *  converted to an object.  If the call fails at the
     *  HTTP level then the error is logged and false is returned.
     */
    protected function callPatronUrl(
        $patronBarcode, $patronPin, $url, $params = null, $requestType = "GET"
    )
    {
        $this->debug("calling patronURL: $url");
        if ($this->connectToPatronAPI($patronBarcode, $patronPin, false)) {
            $patronTokenData = $this->sessionContainer->patronTokenData;
            $authorizationData = $patronTokenData->token_type .
                ' ' . $patronTokenData->access_token;
            $headers = array(
                "Authorization: $authorizationData",
                "User-Agent: VuFind",
                "Content-Type: application/json"
            );
            try {
                $client = $this->getHttpClient($url);
            } catch (\Exception $e) {
                $this->error(
                    "error while setting up the client: " . $e->getMessage()
                );
                return false;
            }
            $client->setHeaders($headers);
            $client->setMethod($requestType);

            if ($params != null) {
                $jsonData = array('fields' => array());
                foreach ($params as $key => $value) {
                    $jsonData['fields'][] = array(
                        'name' => $key,
                        'value' => $value
                    );
                }
                $postData = json_encode($jsonData);
                $client->setRawBody($postData);
            }
            $this->debug("patronURL data sent: $postData");
            $this->debug("patronURL method: " . $client->getMethod());
            $response = $client->send();
            $body = $response->getBody();

            //if all goes well for DELETE, the code will be 204 
            //and response is empty.
            if ($requestType == "DELETE") {
                if ($response->getStatusCode() == 204) {
                    $this->debug("DELETE Patron call appears to have worked.");
                    return true;
                } else {
                    $this->error(
                        "DELETE Patron call failed. HTTP return code: " .
                        $response->getStatusCode()
                    );
                    return false;
                }
            }

            $returnVal = json_decode($body);
            $this->debug("response from call: " . print_r($returnVal, true));
            if ($returnVal != null) {
                if (!isset($returnVal->message)
                    || $returnVal->message != 'An unexpected error has occurred.'
                ) {
                    return $returnVal;
                }
            } else {
                $this->error("Overdrive Error: Nothing returned from API call.");
                return false;
            }
        } else {
            $this->error("Overdrive Error: Not connected to the Patron API.");
        }
        return false;
    }

    /**
     * Connect to Patron API
     *
     * @param  string $patronBarcode      Patrons barcode
     * @param  string $patronPin          Patrons password
     * @param  bool   $forceNewConnection force a new connection (get a new token)
     *
     * @return string token for the session
     */
    protected function connectToPatronAPI(
        $patronBarcode,
        $patronPin = '1234',
        $forceNewConnection = false
    )
    {
        $patronTokenData = $this->sessionContainer->patronTokenData;
        $config = $this->getConfig();
        if ($forceNewConnection || $patronTokenData == null
            || time() >= $patronTokenData->expirationTime
        ) {

            $this->debug("connecting to patron API for new token.");

            //$ch = curl_init("https://oauth-patron.overdrive.com/patrontoken");
            $url = $config->patronTokenURL;
            $websiteId = $config->websiteID;
            $ilsname = $config->ILSname;
            $authHeader = base64_encode(
                $config->clientKey . ":" . $config->clientSecret
            );
            $headers = array(
                "Content-Type: application/x-www-form-urlencoded;charset=UTF-8",
                "Authorization: Basic $authHeader",
                "User-Agent: VuFind"
            );
            try {
                $client = $this->getHttpClient($url);
            } catch (\Exception $e) {
                $this->error(
                    "error while setting up the client: " . $e->getMessage()
                );
                return false;
            }
            $client->setHeaders($headers);
            $client->setMethod("POST");
            if ($patronPin == null) {
                $postFields = "grant_type=password&username={$patronBarcode}";
                $postFields .= "&password=ignore&password_required=false";
                $postFields .= "&scope=websiteId:{$websiteId}%20";
                $postFields .= "authorizationname:{$ilsname}";
            } else {
                $postFields = "grant_type=password&username={$patronBarcode}";
                $postFields .= "&password={$patronPin}&scope=websiteId";
                $postFields .= ":{$websiteId}%20authorizationname:{$ilsname}";
            }
            $this->debug("patron API token data: $postFields");
            $client->setRawBody($postFields);
            $response = $client->setUri($url)->send();
            $body = $response->getBody();
            $patronTokenData = json_decode($body);
            $this->debug(
                "return from OD patron API token Call: " . print_r(
                    $patronTokenData, true
                )
            );
            if (isset($patronTokenData->expires_in)) {
                $patronTokenData->expirationTime = time()
                    + $patronTokenData->expires_in;
            } else {
                $patronTokenData = null;
            }
            $this->sessionContainer->patronTokenData = $patronTokenData;
        }
        return $patronTokenData;
    }

    /*
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
                if ($curlInfo['http_code'] == 204) {
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
                    || $returnVal->message != 'An unexpected error has occurred.'
                ) {
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
    */

    /**
     * Get an HTTP client
     *
     * @param string $url URL for client to use
     *
     * @return \Zend\Http\Client
     * @throws \Exception
     */
    protected function getHttpClient($url = null)
    {
        if (null === $this->httpService) {
            throw new \Exception('HTTP service missing.');
        }
        if (!$this->client) {
            $this->client = $this->httpService->createClient($url);
            //set keep alive to true since we are sending to the same server
            $this->client->setOptions(array('keepalive', true));
        }
        $this->client->resetParameters();
        return $this->client;
    }


    /**
     *
     */
    protected function getResultObject()
    {
        $result = new \stdClass();
        $result->status = false;
        $result->msg = "";
        $result->data = false;
    }

}