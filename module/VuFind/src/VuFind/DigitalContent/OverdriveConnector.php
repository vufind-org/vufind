<?php

/**
 * PHP version 8
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301
 * USA
 *
 * @category VuFind
 * @package  DigitalContent
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Brent Palmer <brent-palmer@icpl.org>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public
 *           License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\DigitalContent;

use Exception;
use Laminas\Cache\Storage\StorageInterface;
use Laminas\Config\Config;
use Laminas\Http\Client;
use Laminas\Log\LoggerAwareInterface;
use Laminas\Session\Container;
use LmcRbacMvc\Service\AuthorizationServiceAwareInterface;
use LmcRbacMvc\Service\AuthorizationServiceAwareTrait;
use VuFind\Auth\ILSAuthenticator;
use VuFind\Cache\KeyGeneratorTrait;
use VuFind\Exception\ILS as ILSException;

/**
 * OverdriveConnector
 *
 * Class responsible for connecting to the Overdrive API
 *
 * @category VuFind
 * @package  DigitalContent
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Brent Palmer <brent-palmer@icpl.org>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public
 *           License
 * @link     https://vufind.org/wiki/development Wiki
 * @todo     provide option for autocheckout by default in config
 *       allow override for cover display using other covers
 *       provide option for not requiring email for holds
 *       provide option for giving users option for every hold
 *       provide option for asking about autocheckout for every hold
 *       provide config options for how to handle patrons with no access to OD
 */
class OverdriveConnector implements
    LoggerAwareInterface,
    AuthorizationServiceAwareInterface,
    \VuFindHttp\HttpServiceAwareInterface
{
    use \VuFind\Log\LoggerAwareTrait {
        logError as error;
    }
    use AuthorizationServiceAwareTrait;
    use \VuFindHttp\HttpServiceAwareTrait;
    use KeyGeneratorTrait;

    /**
     * Session Container
     *
     * @var Container
     */
    protected $sessionContainer;

    /**
     * Overdrive-specific configuration
     *
     * @var Config
     */
    protected $recordConfig;

    /**
     * Main VuFind configuration
     *
     * @var Config
     */
    protected $mainConfig;

    /**
     * ILS Authorization
     *
     * @var ILSAuthenticator
     */
    protected $ilsAuth;

    /**
     * HTTP Client
     *
     * Client for making calls to the API
     *
     * @var Client
     */
    protected $client;

    /**
     * Cache for storing ILS data temporarily (e.g. patron blocks)
     *
     * @var StorageInterface
     */
    protected $cache = null;

    /**
     * Constructor
     *
     * @param Config           $mainConfig       VuFind main conf
     * @param Config           $recordConfig     Record-specific conf file
     * @param ILSAuthenticator $ilsAuth          ILS Authenticator
     * @param Container        $sessionContainer container
     */
    public function __construct(
        Config $mainConfig,
        Config $recordConfig,
        ILSAuthenticator $ilsAuth,
        Container $sessionContainer = null
    ) {
        $this->mainConfig = $mainConfig;
        $this->recordConfig = $recordConfig;
        $this->ilsAuth = $ilsAuth;
        $this->sessionContainer = $sessionContainer;
    }

    /**
     * Loads the session container
     *
     * @return \Laminas\Session\Container
     */
    protected function getSessionContainer()
    {
        if (null === $this->sessionContainer || !$this->sessionContainer) {
            error_log("NO SESSION CONTAINER");
        }
        return $this->sessionContainer;
    }

    /**
     * Get (Logged-in) User
     *
     * Returns the currently logged in user or false if the user is not
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
     * Whether the patron has access to overdrive actions (hold,
     * checkout etc.).
     * This is stored and retrieved from the session.
     *
     * @param bool $refresh Force a check instead of checking cache
     *
     * @return object
     */
    public function getAccess($refresh = false)
    {
        $this->debug("checking patron access to Overdrive.");
        if (!$user = $this->getUser()) {
            return $this->getResultObject(false, "User not logged in.");
        }

        $odAccess = $this->getSessionContainer()->odAccess;
        if ($refresh || empty($odAccess)) {
            if (
                $this->connectToPatronAPI(
                    $user["cat_username"],
                    $user["cat_password"],
                    true
                )
            ) {
                $result = $this->getSessionContainer()->odAccess
                    = $this->getResultObject(true);
            } else {
                $result = $this->getResultObject();
                // there is some problem with the account
                $result->code = "od_account_problem";
                $conf = $this->getConfig();

                if ($conf->noAccessString) {
                    if (
                        str_contains(
                            $this->getSessionContainer()->odAccessMessage,
                            $conf->noAccessString
                        )
                    ) {
                        // this user should not have access to OD
                        $result->code = "od_account_noaccess";
                    }
                }
                // odAccessMessage is set in the session by the API call above
                // maybe it should be saved to a class property instead
                $result->msg = $this->getSessionContainer()->odAccessMessage;
                $this->getSessionContainer()->odAccess = $result;
            }
        } else {
            $result = $this->getSessionContainer()->odAccess;
            $this->debug("OD access found in the cache: " . print_r($result, true));
        }

        return $result;
    }

    /**
     * Get Availability
     *
     * Retrieves the availability for a single resource from Overdrive API
     * with information like copiesOwned, copiesAvailable, numberOfHolds et.
     *
     * @param string $overDriveId The Overdrive ID (reserve ID) of the eResource
     *
     * @return object  Standard object with availability info
     *
     * @link https://developer.overdrive.com/apis/library-availability-new
     */
    public function getAvailability($overDriveId)
    {
        $result = $this->getResultObject();
        if (!$overDriveId) {
            $this->logWarning("no overdrive content ID was passed in.");
            return $result;
        }

        if ($conf = $this->getConfig()) {
            $collectionToken = $this->getCollectionToken();
            // hmm. no token.  if user is logged in let's check access
            if (!$collectionToken && $user = $this->getUser()) {
                $accessResult = $this->getAccess();
                if (!$accessResult->status) {
                    return $accessResult;
                }
            }
            $baseUrl = $conf->discURL;
            $availabilityUrl
                = "$baseUrl/v2/collections/$collectionToken/products/";
            $availabilityUrl .= "$overDriveId/availability";
            $res = $this->callUrl($availabilityUrl);

            if (isset($res->errorCode) and $res->errorCode == "NotFound") {
                if ($conf->consortiumSupport && !$this->getUser()) {
                    // consortium support is turned on but user is not logged in;
                    // if the title is not found it probably means that it's only
                    // available to some users.
                    $result->status = true;
                    $result->code = 'od_code_login_for_avail';
                } else {
                    $result->status = false;
                    $this->logWarning("resource not found: $overDriveId");
                }
            } else {
                $result->status = true;
                $res->copiesAvailable = $res->copiesAvailable ?? 0;
                $res->copiesOwned = $res->copiesOwned ?? 0;
                $res->numberOfHolds = $res->numberOfHolds ?? 0;
                $result->data = $res;
            }
        }

        return $result;
    }

    /**
     * Get Availability (in) Bulk
     *
     * Gets availability for up to 25 titles at once.  This is used by the
     * the ajax availability system
     *
     * @param array $overDriveIds The Overdrive ID (reserve IDs) of the
     *                            eResources
     *
     * @return object|bool see getAvailability
     *
     * @todo if more tan 25 passed in, make multiple calls
     */
    public function getAvailabilityBulk($overDriveIds = [])
    {
        $result = $this->getResultObject();
        $loginRequired = false;
        if (count($overDriveIds) < 1) {
            $this->logWarning("no overdrive content ID was passed in.");
            return false;
        }

        if ($conf = $this->getConfig()) {
            if ($conf->consortiumSupport && !$this->getUser()) {
                $loginRequired = true;
            }
            $collectionToken = $this->getCollectionToken();
            // hmm. no token.  if user is logged in let's check access
            if (!$collectionToken && $user = $this->getUser()) {
                $accessResult = $this->getAccess();
                if (!$accessResult->status) {
                    return $accessResult;
                }
            }
            $baseUrl = $conf->discURL;
            $availabilityPath = "/v2/collections/";
            $availabilityPath .= "$collectionToken/availability?products=";
            $availabilityUrl = $baseUrl . $availabilityPath .
                implode(",", $overDriveIds);
            $res = $this->callUrl($availabilityUrl);
            if (!$res) {
                $result->code = 'od_code_connection_failed';
            } else {
                if ((isset($res->errorCode) and $res->errorCode == "NotFound") or $res->totalItems == 0) {
                    if ($loginRequired) {
                        // consortium support is turned on but user is
                        // not logged in
                        // if the title is not found it could mean that it's only
                        // available to some users.
                        $result->status = true;
                        $result->code = 'od_code_login_for_avail';
                    } else {
                        $result->status = false;
                        $this->logWarning("resources not found");
                    }
                } else {
                    $result->status = true;
                    foreach ($res->availability as $item) {
                        $this->debug("item:" . print_r($item, true));
                        $item->copiesAvailable = $item->copiesAvailable ?? 0;
                        $item->copiesOwned = $item->copiesOwned ?? 0;
                        $item->numberOfHolds = $item->numberOfHolds ?? 0;
                        $result->data[strtolower($item->reserveId)] = $item;
                    }
                    // now look for items not returned
                    foreach ($overDriveIds as $id) {
                        if (!isset($result->data[$id])) {
                            if ($loginRequired) {
                                $result->data[$id]->code
                                    = 'od_code_login_for_avail';
                            } else {
                                $result->data[$id]->code
                                    = 'od_code_resource_not_found';
                            }
                        }
                    }
                }
            }
        }
        return $result;
    }

    /**
     * Get Collection Token
     *
     * Gets the collection token for the Overdrive collection. The collection
     * token doesn't change much but according to
     * the OD API docs it could change and should be retrieved each session.
     * Also, the collection token depends on the user if the user is in a
     * consortium.  If consortium support is turned on then the user collection
     * token will override the library collection token.
     * The token itself is returned but it's also saved in the session and
     * automatically returned.
     *
     * @return object|bool A collection token for the library's collection.
     */
    public function getCollectionToken()
    {
        $collectionToken = $this->getCachedData("collectionToken");
        $userCollectionToken = $this->getSessionContainer(
        )->userCollectionToken;
        $this->debug("collectionToken from cache: $collectionToken");
        $this->debug("userCollectionToken from session: $userCollectionToken");
        $conf = $this->getConfig();
        if ($conf->consortiumSupport && $user = $this->getUser()) {
            if (empty($userCollectionToken)) {
                $this->debug("getting new user collectionToken");
                $baseUrl = $conf->circURL;
                $patronURL = "$baseUrl/v1/patrons/me";
                $res = $this->callPatronUrl(
                    $user["cat_username"],
                    $user["cat_password"],
                    $patronURL
                );
                if ($res) {
                    $userCollectionToken = $res->collectionToken;
                    $this->getSessionContainer()->userCollectionToken
                        = $userCollectionToken;
                } else {
                    return false;
                }
            }
            return $userCollectionToken;
        }
        if (empty($collectionToken)) {
            $this->debug("getting new collectionToken");
            $baseUrl = $conf->discURL;
            $libraryID = $conf->libraryID;
            $libraryURL = "$baseUrl/v1/libraries/$libraryID";
            $res = $this->callUrl($libraryURL);
            if ($res) {
                $collectionToken = $res->collectionToken;
                $this->putCachedData("collectionToken", $collectionToken);
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
     * @param string $overDriveId The overdrive id for the title
     *
     * @return object $result Results of the call.
     */
    public function doOverdriveCheckout($overDriveId)
    {
        $result = $this->getResultObject();

        $this->debug("doOverdriveCheckout: overdriveID: " . $overDriveId);
        if (!$user = $this->getUser()) {
            $this->error("Checkout - user is not logged in", [], true);
            return $result;
        }
        if ($config = $this->getConfig()) {
            $url = $config->circURL . '/v1/patrons/me/checkouts';
            $params = [
                'reserveId' => $overDriveId,
            ];

            $response = $this->callPatronUrl(
                $user["cat_username"],
                $user["cat_password"],
                $url,
                $params,
                "POST"
            );

            if (!empty($response)) {
                if (isset($response->reserveId)) {
                    $expires = "";
                    if ($dt = new \DateTime($response->expires)) {
                        $expires = $dt->format(
                            (string) $config->displayDateFormat
                        );
                    }
                    $result->status = true;
                    $result->data = (object) [];
                    $result->data->expires = $expires;
                    $result->data->formats = $response->formats ?? false;
                    // add the checkout to the session cache
                    $this->getSessionContainer()->checkouts[] = $response;
                } else {
                    $result->msg = $response->message;
                }
            } else {
                $result->code = 'od_code_connection_failed';
            }
        }
        return $result;
    }

    /**
     * Places a hold on an item within OverDrive
     *
     * @param string $overDriveId The overdrive id for the title
     * @param string $email       The email overdrive should use for notif
     *
     * @return \stdClass Object with result
     */
    public function placeOverDriveHold($overDriveId, $email)
    {
        $overDriveId = strtoupper($overDriveId);
        $this->debug("placeOverdriveHold");
        $holdResult = $this->getResultObject();

        if (!$user = $this->getUser()) {
            $this->error("user is not logged in (hold)", [], true);
            return $holdResult;
        }

        if ($config = $this->getConfig()) {
            $ignoreHoldEmail = false;
            $url = $config->circURL . '/v1/patrons/me/holds';
            $action = "POST";
            $params = [
                'reserveId' => $overDriveId,
                'emailAddress' => $email,
                'ignoreHoldEmail' => $ignoreHoldEmail,
            ];

            $response = $this->callPatronUrl(
                $user["cat_username"],
                $user["cat_password"],
                $url,
                $params,
                $action
            );

            if (!empty($response)) {
                if (isset($response->holdListPosition)) {
                    $holdResult->status = true;
                    $holdResult->data = (object) [];
                    $holdResult->data->holdListPosition
                        = $response->holdListPosition;
                } else {
                    $holdResult->msg = $response->message;
                }
            } else {
                $holdResult->code = 'od_code_connection_failed';
            }
        }
        return $holdResult;
    }

    /**
     * Updates the email address for a hold on an item within OverDrive
     *
     * @param string $overDriveId The overdrive id for the title
     * @param string $email       The email overdrive should use for notif
     *
     * @return \stdClass Object with result
     */
    public function updateOverDriveHold($overDriveId, $email)
    {
        $overDriveId = strtoupper($overDriveId);
        $this->debug("updateOverdriveHold");
        $holdResult = $this->getResultObject();

        if (!$user = $this->getUser()) {
            $this->error("user is not logged in (upd hold)", [], true);
            return $holdResult;
        }

        if ($config = $this->getConfig()) {
            $autoCheckout = true;
            $ignoreHoldEmail = false;
            $url = $config->circURL . '/v1/patrons/me/holds/' . $overDriveId;
            $action = "PUT";
            $params = [
                'reserveId' => $overDriveId,
                'emailAddress' => $email,
            ];

            $response = $this->callPatronUrl(
                $user["cat_username"],
                $user["cat_password"],
                $url,
                $params,
                $action
            );

            // because this is a PUT Call, we are just looking for a boolean
            if ($response) {
                $holdResult->status = true;
            } else {
                $holdResult->msg = $response->message;
            }
        }
        return $holdResult;
    }

    /**
     * Suspend Hold
     * Suspend an existing Overdrive Hold
     *
     * @param string $overDriveId    The overdrive id for the title
     * @param string $email          The email overdrive should use for notif
     * @param string $suspensionType indefinite or limited
     * @param int    $numberOfDays   number of days to suspend the hold
     *
     * @return \stdClass Object with result
     */
    public function suspendHold($overDriveId, $email, $suspensionType = "indefinite", $numberOfDays = 7)
    {
        $holdResult = $this->getResultObject();
        $this->debug("OverdriveConnector: suspendHold");

        if (!$user = $this->getUser()) {
            $this->error("user is not logged in (susp hold)", [], true);
            return $holdResult;
        }
        if ($config = $this->getConfig()) {
            $url = $config->circURL . "/v1/patrons/me/holds/$overDriveId/suspension";
            $action = "POST";
            $params = [
                'emailAddress' => $email,
                "suspensionType" => $suspensionType,
            ];
            if ($suspensionType == "limited") {
                $params["numberOfDays"] = $numberOfDays;
            }

            $response = $this->callPatronUrl(
                $user["cat_username"],
                $user["cat_password"],
                $url,
                $params,
                $action
            );

            if (!empty($response)) {
                if (isset($response->holdListPosition)) {
                    $holdResult->status = true;
                    $holdResult->data = $response;
                } else {
                    $holdResult->msg = $response->message;
                }
            } else {
                $holdResult->code = 'od_code_connection_failed';
            }
        }
        return $holdResult;
    }

    /**
     * Edit Suspended Hold
     * Change the redelivery date on an already suspended hold
     *
     * @param string $overDriveId    The overdrive id for the title
     * @param string $email          The email overdrive should use for notif
     * @param string $suspensionType indefinite or limited
     * @param int    $numberOfDays   number of days to suspend the hold
     *
     * @return \stdClass Object with result
     */
    public function editSuspendedHold($overDriveId, $email, $suspensionType = "indefinite", $numberOfDays = 7)
    {
        $holdResult = $this->getResultObject();
        $this->debug("OverdriveConnector: suspendHold");

        if (!$user = $this->getUser()) {
            $this->error("user is not logged in (edit susp hold)", [], true);
            return $holdResult;
        }
        if ($config = $this->getConfig()) {
            $url = $config->circURL . "/v1/patrons/me/holds/$overDriveId/suspension";
            $action = "PUT";
            $params = [
                'emailAddress' => $email,
                "suspensionType" => $suspensionType,
            ];
            if ($suspensionType == "limited") {
                $params["numberOfDays"] = $numberOfDays;
            }

            $response = $this->callPatronUrl(
                $user["cat_username"],
                $user["cat_password"],
                $url,
                $params,
                $action
            );

            // because this is a PUT Call, we are just looking for a boolean
            if ($response) {
                $holdResult->status = true;
            } else {
                $holdResult->msg = $response->message;
            }
        }
        return $holdResult;
    }

    /**
     * Delete Suspended Hold
     * Removes the suspension from a hold
     *
     * @param string $overDriveId The overdrive id for the title
     *
     * @return \stdClass Object with result
     */
    public function deleteHoldSuspension($overDriveId)
    {
        $holdResult = $this->getResultObject();
        $this->debug("OverdriveConnector: deleteHoldSuspension");

        if (!$user = $this->getUser()) {
            $this->error("user is not logged in (del hold susp)", [], true);
            return $holdResult;
        }
        if ($config = $this->getConfig()) {
            $url = $config->circURL . "/v1/patrons/me/holds/$overDriveId/suspension";
            $action = "DELETE";
            $response = $this->callPatronUrl(
                $user["cat_username"],
                $user["cat_password"],
                $url,
                null,
                $action
            );

            // because this is a DELETE Call, we are just looking for a boolean
            if ($response) {
                $holdResult->status = true;
            } else {
                $holdResult->msg = $response->message;
            }
        }
        return $holdResult;
    }
    /**
     * Cancel Hold
     * Cancel and existing Overdrive Hold
     *
     * @param string $overDriveId The overdrive id for the title
     *
     * @return \stdClass Object with result
     */
    public function cancelHold($overDriveId)
    {
        $holdResult = $this->getResultObject();
        $this->debug("OverdriveConnector: cancelHold");
        // $this->debug(print_r($user,true));
        if (!$user = $this->getUser()) {
            $this->error("user is not logged in (cancel hold)", [], true);
            return $holdResult;
        }
        if ($config = $this->getConfig()) {
            $url = $config->circURL . "/v1/patrons/me/holds/$overDriveId";
            $action = "DELETE";
            $response = $this->callPatronUrl(
                $user["cat_username"],
                $user["cat_password"],
                $url,
                null,
                $action
            );

            // because this is a DELETE Call, we are just looking for a boolean
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
     * Return a title early.
     *
     * @param string $resourceID Overdrive ID of the resource
     *
     * @return object|bool Object with result
     */
    public function returnResource($resourceID)
    {
        $result = $this->getResultObject();
        // $this->debug(print_r($user,true));
        if (!$user = $this->getUser()) {
            $this->error("user is not logged in (return)", [], true);
            return $result;
        }
        if ($config = $this->getConfig()) {
            $url = $config->circURL . "/v1/patrons/me/checkouts/$resourceID";
            $action = "DELETE";
            $response = $this->callPatronUrl(
                $user["cat_username"],
                $user["cat_password"],
                $url,
                null,
                $action
            );

            // because this is a DELETE Call, we are just looking for a boolean
            if ($response) {
                $result->status = true;
            } else {
                $result->msg = $response->message;
            }
        }
        return $result;
    }


    /**
     * Retrieves the auth header needed to get the Download HTML snippet
     *
     * @return object Object containing the auth header in the data property
     */
    public function getAuthHeader()
    {
        $this->debug("getAuthHeader");
        $result = $this->getResultObject();
        if (!$user = $this->getUser()) {
            $this->error("user is not logged in (getauth header)", [], true);
            return $result;
        }
        // todo: check result
        $patronTokenData = $this->connectToPatronAPI(
            $user["cat_username"],
            $user["cat_password"],
            $forceNewConnection = false
        );
        $authorizationData = $patronTokenData->token_type .
            ' ' . $patronTokenData->access_token;
        $header = "Authorization: $authorizationData";
        $result->data->authheader = $header;
        $result->status = true;
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
            $conf->productionMode = false;
            $conf->discURL = $this->recordConfig->API->integrationDiscoveryURL;
            $conf->circURL = $this->recordConfig->API->integrationCircURL;
            $conf->libraryID = $this->recordConfig->API->integrationLibraryID;
            $conf->websiteID = $this->recordConfig->API->integrationWebsiteID;
        } else {
            $conf->productionMode = true;
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
        $conf->idSubfield
            = $this->recordConfig->Overdrive->overdriveIdMarcSubfield;
        $conf->ILSname = $this->recordConfig->API->ILSname;
        $conf->isMarc = $this->recordConfig->Overdrive->isMarc;
        $conf->displayDateFormat = $this->mainConfig->Site->displayDateFormat;
        $conf->consortiumSupport
            = $this->recordConfig->Overdrive->consortiumSupport;
        $conf->showMyContent
            = strtolower($this->recordConfig->Overdrive->showMyContent);
        $conf->noAccessString = $this->recordConfig->Overdrive->noAccessString;
        $conf->tokenCacheLifetime
            = $this->recordConfig->API->tokenCacheLifetime;
        $conf->libraryURL = $this->recordConfig->Overdrive->overdriveLibraryURL;
        return $conf;
    }

    /**
     * Returns an array of Overdrive Formats and translation tokens
     *
     * @return array
     */
    public function getFormatNames()
    {
        return [
            'ebook-kindle' => "od_ebook-kindle",
            'ebook-overdrive' => "od_ebook-overdrive",
            'ebook-epub-adobe' => "od_ebook-epub-adobe",
            'ebook-epub-open' => "od_ebook-epub-open",
            'ebook-pdf-adobe' => "od_ebook-pdf-adobe",
            'ebook-pdf-open' => "od_ebook-pdf-open",
            'ebook-mediado' => "od_ebook-mediado",
            'audiobook-overdrive' => "od_audiobook-overdrive",
            'audiobook-mp3' => "od_audiobook-mp3",
            'video-streaming' => "od_video-streaming",
            'magazine-overdrive' => "od_magazine-overdrive"
        ];
    }

    /**
     * Returns permanant links for Ovedrive resources
     *
     * @param array $overDriveIds An array of overdrive IDs we need links for
     *
     * @return array<string>
     */
    public function getPermanentLinks($overDriveIds = [])
    {
        $links = [];
        if (!$overDriveIds || count($overDriveIds) < 1) {
            $this->logWarning("no overdrive content IDs were passed in.");
            return [];
        }
        if ($conf = $this->getConfig()) {
            $libraryURL = $conf->libraryURL;
            $md = $this->getMetadata($overDriveIds);

            foreach ($md as $item) {
                $links[$item->id] = "$libraryURL/media/" . $item->crossRefId;
            }
        }
        return $links;
    }

    /**
     * Returns
     *
     * @param string $overDriveId Overdrive Identifier for magazine title
     * @param int    $limit       maximum number of issues to retrieve (default 100)
     * @param int    $offset      page of results (default 0)
     *
     * @return object results of metadata fetch
     */
    public function getMagazineIssues($overDriveId = false, $limit = 100, $offset = 0)
    {
        $this->debug("get Overdrive Magazine Issues: $overDriveId");
        $result = $this->getResultObject();
        if (!$overDriveId) {
            $this->logWarning("no overdrive content ID was passed in.");
            $result->msg = "no overdrive content ID was passed in.";
            return $result;
        }
        if ($conf = $this->getConfig()) {
            $libraryURL = $conf->libraryURL;
            $productsKey = $this->getCollectionToken();
            $baseUrl = $conf->discURL;
            $issuesURL = "$baseUrl/v1/collections/$productsKey/products/$overDriveId/issues";
            $issuesURL .= "?limit=$limit&offset=$offset";
            $response = $this->callUrl($issuesURL);
            if ($response) {
                $result->status = true;
                $result->data = (object) [];
                $result->data = $response;
            }
        }
        return $result;
    }

    /**
     * Returns a hash of metadata keyed on overdrive reserveID
     *
     * @param array $overDriveIds Set of Overdrive IDs
     *
     * @return array results of metadata fetch
     *
     * @todo if more tan 25 passed in, make multiple calls
     */
    public function getMetadata($overDriveIds = [])
    {
        $metadata = [];
        if (!$overDriveIds || count($overDriveIds) < 1) {
            $this->logWarning("no overdrive content IDs were passed in.");
            return [];
        }
        if ($conf = $this->getConfig()) {
            $libraryURL = $conf->libraryURL;
            $productsKey = $this->getCollectionToken();
            $baseUrl = $conf->discURL;
            $metadataUrl = "$baseUrl/v1/collections/$productsKey/";
            $metadataUrl .= "bulkmetadata?reserveIds=" . implode(
                ",",
                $overDriveIds
            );
            $res = $this->callUrl($metadataUrl);
            $md = $res->metadata;
            foreach ($md as $item) {
                $item->external_url = "$libraryURL/media/" . $item->crossRefId;
                $metadata[$item->id] = $item;
            }
        }
        return $metadata;
    }

    /**
     * For  array of titles passed in this will return the same array
     * with metadata attached to the records with the property name of 'metadata'
     *
     * @param array $overDriveTitles Assoc array of objects with OD IDs as keys (generally what
     * you get from getCheckouts and getHolds)
     *
     * @return array inital array with results of metadata attached as "metadata" property
     *
     * @todo if more tan 25 passed in, make multiple calls
     */
    public function getMetadataForTitles($overDriveTitles = [])
    {
        if (!$overDriveTitles || count($overDriveTitles) < 1) {
            $this->logWarning("getMetadataForTitles - no overdrive content was passed in.");
            return [];
        }
        $metadata = $this->getMetadata(array_column($overDriveTitles, 'reserveId', 'reserveId'));
        foreach ($overDriveTitles as &$title) {
            $id = $title->reserveId;
            if (!isset($metadata[strtolower($id)])) {
                $this->logWarning("no metadata found for " . strtolower($id));
            } else {
                $title->metadata = $metadata[strtolower($id)];
            }
        }
        return $overDriveTitles;
    }


    /**
     * Get Overdrive Checkout
     *
     * Get the overdrive checkout object for an overdrive title
     * for the current user
     *
     * @param string $overDriveId Overdrive resource id
     * @param bool   $refresh     Whether or not to ignore cache and get latest
     *
     * @return object|false PHP object that represents the checkout or false
     * the checkout is not in the current list of checkouts for the current
     * user.
     */
    public function getCheckout($overDriveId, $refresh = true)
    {
        $this->debug("get Overdrive checkout");
        $result = $this->getCheckouts($refresh);
        if ($result->status) {
            $checkouts = $result->data;
            foreach ($checkouts as $checkout) {
                if (
                    strtolower($checkout->reserveId) == strtolower(
                        $overDriveId
                    )
                ) {
                    return $checkout;
                }
            }
            return false;
        } else {
            return false;
        }
    }

    /**
     * Get Overdrive Hold
     *
     * Get the overdrive hold object for an overdrive title
     * for the current user
     *
     * @param string $overDriveId Overdrive resource id
     * @param bool   $refresh     Whether or not to ignore cache and get latest
     *
     * @return object|false PHP object that represents the checkout or false
     * the checkout is not in the current list of checkouts for the current
     * user.
     */
    public function getHold($overDriveId, $refresh = true)
    {
        $this->debug("get Overdrive hold");
        $result = $this->getHolds($refresh);
        if ($result->status) {
            $holds = $result->data;
            foreach ($holds as $hold) {
                if (strtolower($hold->reserveId) == strtolower($overDriveId)) {
                    $this->debug("hold found");
                    return $hold;
                }
            }
            return false;
        } else {
            return false;
        }
    }

    /**
     * Get Overdrive Checkouts (for a user)
     *
     * @param bool $refresh Whether or not to ignore cache and get latest
     *
     * @return object Results of the call
     */
    public function getCheckouts($refresh = true)
    {
        // the checkouts are cached in the session, but we can force a refresh
        $this->debug("get Overdrive Checkouts");
        $result = $this->getResultObject();

        if (!$user = $this->getUser()) {
            $this->error("user is not logged in (getcheckouts)");
            return $result;
        }

        $checkouts = $this->getSessionContainer()->checkouts;
        if (!$checkouts || $refresh) {
            if ($config = $this->getConfig()) {
                $url = $config->circURL . '/v1/patrons/me/checkouts';

                $response = $this->callPatronUrl(
                    $user["cat_username"],
                    $user["cat_password"],
                    $url,
                    null
                );
                if (!empty($response)) {
                    $result->status = true;
                    $result->message = '';
                    if (isset($response->checkouts)) {
                        // get the metadata for these so we can check for magazines.
                        $result->data = (object) [];
                        $result->data = $this->getMetadataForTitles($response->checkouts);

                        foreach ($response->checkouts as $key => $checkout) {
                            // Convert dates to desired format
                            $coExpires = new \DateTime($checkout->expires);
                            $result->data[$key]->expires = $coExpires->format(
                                $config->displayDateFormat
                            );
                            $result->data[$key]->isReturnable
                                = !$checkout->isFormatLockedIn;
                        }
                        $this->getSessionContainer()->checkouts
                            = $response->checkouts;
                    } else {
                        $result->data = [];
                        $this->getSessionContainer()->checkouts = false;
                    }
                } else {
                    $accessResult = $this->getAccess();
                    return $accessResult;
                }
            } // no config...
        } else {
            $this->debug("found Overdrive Checkouts in session");
            $result->status = true;
            $result->msg = [];
            $result->data = (object) [];
            $result->data = $this->getSessionContainer()->checkouts;
        }
        return $result;
    }

    /**
     * Get Overdrive Holds (for a user)
     *
     * @param bool $refresh Whether or not to ignore cache and get latest
     *
     * @return \stdClass Results of the call. the data property will be set
     *     to an empty array if there are no  holds.
     */
    public function getHolds($refresh = true)
    {
        $this->debug("get Overdrive Holds");
        $result = $this->getResultObject();
        if (!$user = $this->getUser()) {
            $this->error("user is not logged in (getholds)");
            return $result;
        }

        $holds = $this->getSessionContainer()->holds;
        if (!$holds || $refresh) {
            if ($config = $this->getConfig()) {
                $url = $config->circURL . '/v1/patrons/me/holds';

                $response = $this->callPatronUrl(
                    $user["cat_username"],
                    $user["cat_password"],
                    $url
                );

                $result->status = false;
                $result->message = '';

                if (!empty($response)) {
                    $result->status = true;
                    $result->message = 'hold_place_success_html';


                    if (isset($response->holds)) {
                        $result->data = [];
                        $result->data = $response->holds;
                        // Check for holds ready for chechout
                        foreach ($response->holds as $key => $hold) {
                            // check for hold suspension
                            if (isset($hold->holdSupension)) {
                                $result->data[$key]->holdSupension = $hold->holdSupension;
                            } else {
                                $result->data[$key]->holdSupension = false;
                            }
                            // check if ready for checkout
                            foreach ($hold->actions as $action => $value) {
                                if ($action == "checkout") {
                                    $result->data[$key]->holdReadyForCheckout = true;
                                    $this->debug("hold ready for checkout");
                                    // format the expires date.
                                    $holdExpires = new \DateTime($hold->holdExpires);
                                    $result->data[$key]->holdExpires
                                        = $holdExpires->format(
                                            (string) $config->displayDateFormat
                                        );
                                } else {
                                    $result->data[$key]->holdReadyForCheckout = false;
                                }
                            }

                            $holdPlacedDate = new \DateTime($hold->holdPlacedDate);
                            $result->data[$key]->holdPlacedDate
                                = $holdPlacedDate->format(
                                    (string) $config->displayDateFormat
                                );
                        } // end foreach
                        $this->getSessionContainer()->holds = $response->holds;
                    } else {
                        // no holds found for this patron
                        $result->data = [];
                        $this->getSessionContainer()->holds;
                    }
                } else {
                    $result->code = 'od_code_connection_failed';
                }
            } // no config
        } else {
            $this->debug("found Overdrive Holds in cache");
            $result->status = true;
            $result->message = [];
            $result->data = $this->getSessionContainer()->holds;
        }
        return $result;
    }


    /**
     * Call a URL on the API
     *
     * @param string $url         The url to call
     * @param array  $headers     Headers to set for the request.
     *                            if null, then the auth headers are used.
     * @param bool   $checkToken  Whether to check and get a new token
     * @param string $requestType The request type (GET, POST etc)
     *
     * @return object|bool The json response from the API call
     *  converted to an object.  If the call fails at the
     *  HTTP level then the error is logged and false is returned.
     */
    protected function callUrl(
        $url,
        $headers = null,
        $checkToken = true,
        $requestType = "GET"
    ) {
        $this->debug("chktoken: $checkToken");
        if (!$checkToken || $this->connectToAPI()) {
            $tokenData = $this->getSessionContainer()->tokenData;
            $this->debug("url for OD API Call: $url");
            try {
                $client = $this->getHttpClient($url);
            } catch (Exception $e) {
                $this->error(
                    "error while setting up the client: " . $e->getMessage()
                );
                return false;
            }
            if ($headers === null) {
                $headers = [];
                if (
                    isset($tokenData->token_type)
                    && isset($tokenData->access_token)
                ) {
                    $headers[] = "Authorization: {$tokenData->token_type} "
                        . $tokenData->access_token;
                }
                $headers[] = "User-Agent: VuFind";
            }
            $client->setHeaders($headers);
            $client->setMethod($requestType);
            $client->setUri($url);
            try {
                // throw new Exception('testException');
                $response = $client->send();
            } catch (Exception $ex) {
                $this->error(
                    "Exception during request: " .
                    $ex->getMessage()
                );
                return false;
            }

            if ($response->isServerError()) {
                $this->error(
                    "Overdrive HTTP Error: " .
                    $response->getStatusCode()
                );
                $this->debug("Request: " . $client->getRequest());
                $this->debug("Response: " . $client->getResponse());
                return false;
            }

            $body = $response->getBody();
            $returnVal = json_decode($body);
            $this->debug(
                "Return from OD API Call: " . print_r($returnVal, true)
            );
            if ($returnVal != null) {
                if (isset($returnVal->errorCode)) {
                    // In some cases, this should be returned perhaps...
                    $this->error("Overdrive Error: " . $returnVal->errorCode);
                    return $returnVal;
                } else {
                    return $returnVal;
                }
            } else {
                $this->error(
                    "Overdrive Error: Nothing returned from API call."
                );
                $this->debug(
                    "Body return from OD API Call: " . print_r($body, true)
                );
            }
        }
        return false;
    }

    /**
     * Connect to API
     *
     * @param bool $forceNewConnection Force a new connection (get a new token)
     *
     * @return string token for the session or false
     *     if the token request failed
     */
    protected function connectToAPI($forceNewConnection = false)
    {
        $this->debug("connecting to API");
        $conf = $this->getConfig();
        $tokenData = $this->getSessionContainer()->tokenData;
        $this->debug("API Token from session: " . print_r($tokenData, true));
        if (
            $forceNewConnection || $tokenData == null
            || !isset($tokenData->access_token)
            || time() >= $tokenData->expirationTime
        ) {
            $authHeader = base64_encode(
                $conf->clientKey . ":" . $conf->clientSecret
            );
            $headers = [
                'Content-Type: application/x-www-form-urlencoded;charset=UTF-8',
                "Authorization: Basic $authHeader"
            ];

            try {
                $client = $this->getHttpClient();
            } catch (Exception $e) {
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
                "TokenData returned from OD API Call: " . print_r(
                    $tokenData,
                    true
                )
            );
            if ($tokenData != null) {
                if (isset($tokenData->errorCode)) {
                    // In some cases, this should be returned perhaps...
                    $this->error("Overdrive Error: " . $tokenData->errorCode);
                    return false;
                } else {
                    $tokenData->expirationTime = time()
                        + ($tokenData->expires_in ?? 0);
                    $this->getSessionContainer()->tokenData = $tokenData;
                    return $tokenData;
                }
            } else {
                $this->error(
                    "Overdrive Error: Nothing returned from API call."
                );
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
     * @param string $returnType    options are json(def),body,redirect
     *
     * @return object|bool The json response from the API call
     *  converted to an object. If body is specified, the raw body is returned.
     *  If redirect, then it returns the URL specified in the redirect header.
     *  If the call fails at the HTTP level then the error is logged and false is returned.
     */
    protected function callPatronUrl(
        $patronBarcode,
        $patronPin,
        $url,
        $params = null,
        $requestType = "GET",
        $returnType = "json"
    ) {
        $this->debug("calling patronURL: $url");
        if ($this->connectToPatronAPI($patronBarcode, $patronPin, false)) {
            $patronTokenData = $this->getSessionContainer()->patronTokenData;
            $authorizationData = $patronTokenData->token_type .
                ' ' . $patronTokenData->access_token;
            $headers = [
                "Authorization: $authorizationData",
                "User-Agent: VuFind",
                "Content-Type: application/json"
            ];
            try {
                $client = $this->getHttpClient(null, $returnType != "redirect");
            } catch (Exception $e) {
                $this->error(
                    "error while setting up the client: " . $e->getMessage()
                );
                return false;
            }
            $client->setHeaders($headers);
            $client->setMethod($requestType);
            $this->debug("patronURL method: " . $client->getMethod());
            $client->setUri($url);
            if ($params != null) {
                $jsonData = ['fields' => []];
                foreach ($params as $key => $value) {
                    $jsonData['fields'][] = [
                        'name' => $key,
                        'value' => $value
                    ];
                }
                $postData = json_encode($jsonData);
                $client->setRawBody($postData);
                $this->debug("patron data sent: $postData");
            }

            $this->debug("client: " . $client->getRequest());
            try {
                $response = $client->send();
            } catch (Exception $ex) {
                $this->error(
                    "Exception during request: " .
                    $ex->getMessage()
                );
                return false;
            }
            $body = $response->getBody();

            // if all goes well for DELETE, the code will be 204
            // and response is empty.
            if ($requestType == "DELETE" || $requestType == "PUT") {
                if ($response->getStatusCode() == 204) {
                    $this->debug($requestType . " Patron call appears to have worked.");
                    return true;
                } else {
                    $this->error(
                        $requestType . " Patron call failed. HTTP return code: " .
                        $response->getStatusCode()
                    );
                    return false;
                }
            }

            if ($returnType == "body") {
                // probably need to check for return status
                return $body;
            } elseif ($returnType == "redirect") {
                $headers = $response->getHeaders();
                if ($headers->has('location')) {
                    $loc = $headers->get('location');
                    $uri = $loc->getUri();
                    return $uri;
                } else {
                    $this->error(
                        "Overdrive Error: returnType is redirect but no redirect found."
                    );
                    return false;
                }
            } else {
                $returnVal = json_decode($body);
                $this->debug("response from call: " . print_r($returnVal, true));

                if ($returnVal != null) {
                    if (
                        !isset($returnVal->message)
                        || $returnVal->message != 'An unexpected error has occurred.'
                    ) {
                        return $returnVal;
                    } else {
                        $this->debug(
                            "Overdrive API problem: " . $returnVal->message
                        );
                    }
                } else {
                    $this->error(
                        "Overdrive Error: Nothing returned from API call."
                    );
                    return false;
                }
            }
        } else {
            $this->error("Overdrive Error: Not connected to the Patron API.");
        }
        return false;
    }

    /**
     * Connect to Patron API
     *
     * @param string $patronBarcode      Patrons barcode
     * @param string $patronPin          Patrons password
     * @param bool   $forceNewConnection force a new connection (get a new
     *                                   token)
     *
     * @return object|bool token for the session
     */
    protected function connectToPatronAPI(
        $patronBarcode,
        $patronPin = '1234',
        $forceNewConnection = false
    ) {
        $patronTokenData = $this->getSessionContainer()->patronTokenData;
        $config = $this->getConfig();
        if (
            $forceNewConnection
            || $patronTokenData == null
            || (isset($patronTokenData->expirationTime)
                and time() >= $patronTokenData->expirationTime)
        ) {
            $this->debug("connecting to patron API for new token.");
            $url = $config->patronTokenURL;
            $websiteId = $config->websiteID;
            $ilsname = $config->ILSname;
            $authHeader = base64_encode(
                $config->clientKey . ":" . $config->clientSecret
            );
            $headers = [
                "Content-Type: application/x-www-form-urlencoded;charset=UTF-8",
                "Authorization: Basic $authHeader",
                "User-Agent: VuFind"
            ];
            try {
                $client = $this->getHttpClient($url);
            } catch (Exception $e) {
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

            if (isset($patronTokenData->expires_in)) {
                $patronTokenData->expirationTime = time()
                    + $patronTokenData->expires_in;
            } else {
                $this->debug(
                    "problem with OD patron API token Call: " .
                    print_r(
                        $patronTokenData,
                        true
                    )
                );
                // if we have an unauthorized error, then we are going
                // to cache that in the session so we don't keep making
                // unnecessary calls, otherwise, just don't store the tokenData
                // object so that it gets checked again next time
                if ($patronTokenData->error == 'unauthorized_client') {
                    $this->getSessionContainer()->odAccessMessage
                        = $patronTokenData->error_description;
                    $this->getSessionContainer()->patronTokenData
                        = $patronTokenData;
                } else {
                    $patronTokenData = null;
                }
                return false;
            }
            $this->getSessionContainer()->patronTokenData = $patronTokenData;
        }
        if (isset($patronTokenData->error)) {
            return false;
        }
        return $patronTokenData;
    }

    /**
     * Get an HTTP client
     *
     * @param string $url            URL for client to use
     * @param bool   $allowRedirects whether to allow client to follow redir
     *
     * @return \Zend\Http\Client
     * @throws Exception
     */
    protected function getHttpClient($url = null, $allowRedirects = true)
    {
        if (null === $this->httpService) {
            throw new Exception('HTTP service missing.');
        }
        if (!$this->client) {
            $this->client = $this->httpService->createClient($url);
        }
        $this->client->resetParameters();
        // set keep alive to true since we are sending to the same server
        $options = ['keepalive' => true];
        if (!$allowRedirects) {
            $options['maxredirects'] = 0;
        }
        $this->client->setOptions($options);
        return $this->client;
    }

    /**
     * Set a cache storage object.
     *
     * @param StorageInterface $cache Cache storage interface
     *
     * @return void
     */
    public function setCacheStorage(StorageInterface $cache = null)
    {
        $this->cache = $cache;
    }

    /**
     * Helper function for fetching cached data.
     * Data is cached for up to $this->cacheLifetime seconds so that it would
     * be
     * faster to process e.g. requests where multiple calls to the backend are
     * made.
     *
     * @param string $key Cache entry key
     *
     * @return mixed|null Cached entry or null if not cached or expired
     */
    protected function getCachedData($key)
    {
        // No cache object, no cached results!
        if (null === $this->cache) {
            return null;
        }
        $conf = $this->getConfig();
        $fullKey = $this->getCacheKey($key);
        $item = $this->cache->getItem($fullKey);
        if (null !== $item) {
            $this->debug(
                "pulling item from cache for key $key : " . $item['entry']
            );
            // Return value if still valid:
            if (time() - $item['time'] < $conf->tokenCacheLifetime) {
                return $item['entry'];
            }

            // Clear expired item from cache:
            $this->cache->removeItem($fullKey);
        }
        return null;
    }

    /**
     * Helper function for storing cached data.
     * Data is cached for up to $this->cacheLifetime seconds so that it would
     * be
     * faster to process e.g. requests where multiple calls to the backend are
     * made.
     *
     * @param string $key   Cache entry key
     * @param mixed  $entry Entry to be cached
     *
     * @return void
     */
    protected function putCachedData($key, $entry)
    {
        // Don't write to cache if we don't have a cache!
        if (null === $this->cache) {
            return;
        }
        $item = [
            'time' => time(),
            'entry' => $entry
        ];
        $this->debug("putting item from cache for key $key : $entry");
        $this->cache->setItem($this->getCacheKey($key), $item);
    }

    /**
     * Helper function for removing cached data.
     *
     * @param string $key Cache entry key
     *
     * @return void
     */
    protected function removeCachedData($key)
    {
        // Don't write to cache if we don't have a cache!
        if (null === $this->cache) {
            return;
        }
        $this->cache->removeItem($this->getCacheKey($key));
    }

    /**
     * Get Result Object
     *
     * @param bool   $status Whether it succeeded
     * @param string $msg    More information
     * @param string $code   code used for end user display/translation
     *
     * @return object
     */
    public function getResultObject($status = false, $msg = "", $code = "")
    {
        return (object) [
            'status' => $status,
            'msg' => $msg,
            'data' => false,
            'code' => $code
        ];
    }
}
