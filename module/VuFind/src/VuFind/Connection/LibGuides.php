<?php

/**
 * LibGuides API connection class.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2023.
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
 * @package  Connection
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Brent Palmer <brent-palmer@icpl.org>
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */

namespace VuFind\Connection;

use Exception;
use Laminas\Log\LoggerAwareInterface;

/**
 * LibGuides API connection class.
 *
 * Note: This is for the LibGuides API used by the LibGuidesProfile recommendation service,
 * this is *not* for the LibGuides search widget "API" used by the LibGuides and LibGuidesAZ
 * data sources.
 *
 * Closely adapted from VuFind\DigitalContent\OverdriveConnector.
 *
 * @category VuFind
 * @package  Connection
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Brent Palmer <brent-palmer@icpl.org>
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class LibGuides implements
    OauthServiceInterface,
    \VuFindHttp\HttpServiceAwareInterface,
    LoggerAwareInterface
{
    use OauthServiceTrait;
    use \VuFindHttp\HttpServiceAwareTrait;
    use \VuFind\Log\LoggerAwareTrait {
        logError as error;
    }

    /**
     * HTTP Client
     *
     * @var \Laminas\Http\HttpClient
     */
    protected $client;

    /**
     * Base URL of the LibGuides API
     *
     * @var string
     */
    protected $baseUrl;

    /**
     * Client ID for a client_credentials grant
     *
     * @var string
     */
    protected $clientId;

    /**
     * Client Secret for a client_credentials grant
     *
     * @var string
     */
    protected $clientSecret;

    /**
     * Constructor
     *
     * @param \Laminas\Http\Client $client             HTTP client
     * @param string               $baseUrl            Base URL of the LibGuides API
     * @param string               $clientId           Client Id provided by LibGuides API configuration
     * @param string               $clientSecret       Client Secret provided by LibGuides API configuration
     * @param bool                 $forceNewConnection Force a new connection (get a new token)
     *
     * @link https://ask.springshare.com/libguides/faq/873#api-auth
     */
    public function __construct(
        $client,
        $baseUrl,
        $clientId,
        $clientSecret,
        $forceNewConnection = false
    ) {
        $this->client = $client;
        $this->baseUrl = $baseUrl;
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
    }

    /**
     * Load all LibGuides accounts.
     *
     * @return array An array of all LibGuides accounts, or an empty array
     * if an error occurs
     */
    public function getAccounts()
    {
        $tokenData = $this->authenticateWithClientCredentials(
            $this->baseUrl . "/oauth/token",
            $this->clientId,
            $this->clientSecret
        );
        if (!$tokenData) {
            return [];
        }

        $headers = [];
        if (
            isset($tokenData->token_type)
            && isset($tokenData->access_token)
        ) {
            $headers[] = "Authorization: {$tokenData->token_type} "
                . $tokenData->access_token;
        }
        $headers[] = "User-Agent: VuFind";

        $this->client->setHeaders($headers);
        $this->client->setMethod("GET");
        $this->client->setUri(
            $this->baseUrl . "/accounts?expand=profile,subjects"
        );
        try {
            // throw new Exception('testException');
            $response = $this->client->send();
        } catch (Exception $ex) {
            $this->error(
                "Exception during request: " .
                $ex->getMessage()
            );
            return [];
        }

        if ($response->isServerError()) {
            $this->error(
                "LibGuides API HTTP Error: " .
                $response->getStatusCode()
            );
            $this->debug("Request: " . $this->client->getRequest());
            $this->debug("Response: " . $this->client->getResponse());
            return [];
        }
        $body = $response->getBody();
        $returnVal = json_decode($body);
        $this->debug(
            "Return from LibGuides API Call: " . print_r($returnVal, true)
        );
        if ($returnVal != null) {
            if (isset($returnVal->errorCode)) {
                // In some cases, this should be returned perhaps...
                $this->error("LibGuides Error: " . $returnVal->errorCode);
                return $returnVal;
            } else {
                return $returnVal;
            }
        } else {
            $this->error(
                "LibGuides API Error: Nothing returned from API call."
            );
            $this->debug(
                "Body return from LibGuides API Call: " . print_r($body, true)
            );
        }
        return [];
    }
}
