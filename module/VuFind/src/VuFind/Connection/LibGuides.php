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
     * User agent to send in header
     *
     * @var string
     */
    protected $userAgent = 'VuFind';

    /**
     * Constructor
     *
     * @param Config               $config LibGuides API configuration object
     * @param \Laminas\Http\Client $client HTTP client
     *
     * @link https://ask.springshare.com/libguides/faq/873#api-auth
     */
    public function __construct(
        $config,
        $client
    ) {
        $this->client = $client;
        $this->baseUrl = $config->General->api_base_url;
        $this->clientId = $config->General->client_id;
        $this->clientSecret = $config->General->client_secret;
    }

    /**
     * Load all LibGuides accounts.
     *
     * @return object|null A JSON object of all LibGuides accounts, or null
     * if an error occurs
     */
    public function getAccounts()
    {
        if (!$this->authenticateAndSetHeaders()) {
            return null;
        }

        $result = $this->doGet(
            $this->baseUrl . '/accounts?expand=profile,subjects'
        );

        if (isset($result->errorCode)) {
            return null;
        }
        return $result;
    }

    /**
     * Load all LibGuides AZ databases.
     *
     * @return object|null A JSON object of all LibGuides databases, or null
     * if an error occurs
     */
    public function getAZ()
    {
        if (!$this->authenticateAndSetHeaders()) {
            return null;
        }

        $result = $this->doGet(
            $this->baseUrl . '/az?expand=az_props'
        );

        if (isset($result->errorCode)) {
            return null;
        }
        return $result;
    }

    /**
     * Authenticate to the LibGuides API and set authentication headers.
     *
     * @return bool Indicates if authentication succeeded.
     */
    protected function authenticateAndSetHeaders()
    {
        $tokenData = $this->authenticateWithClientCredentials(
            $this->baseUrl . '/oauth/token',
            $this->clientId,
            $this->clientSecret
        );
        if (!$tokenData) {
            return false;
        }

        $headers = [];
        if (
            isset($tokenData->token_type)
            && isset($tokenData->access_token)
        ) {
            $headers[] = "Authorization: {$tokenData->token_type} "
                . $tokenData->access_token;
        }
        $headers[] = 'User-Agent: ' . $this->userAgent;

        $this->client->setHeaders($headers);

        return true;
    }

    /**
     * Perform a GET request to the LibGuides API.
     *
     * @param string $url Full request url
     *
     * @return object|null A JSON object of the response data, or null if an error occurs
     */
    protected function doGet($url)
    {
        $this->client->setMethod('GET');
        $this->client->setUri($url);
        try {
            $response = $this->client->send();
        } catch (Exception $ex) {
            $this->error(
                'Exception during request: ' .
                $ex->getMessage()
            );
            return null;
        }

        if ($response->isServerError()) {
            $this->error(
                'LibGuides API HTTP Error: ' .
                $response->getStatusCode()
            );
            $this->debug('Request: ' . $this->client->getRequest());
            $this->debug('Response: ' . $this->client->getResponse());
            return null;
        }
        $body = $response->getBody();
        $returnVal = json_decode($body);
        $this->debug(
            'Return from LibGuides API Call: ' . $this->varDump($returnVal)
        );
        if ($returnVal != null) {
            if (isset($returnVal->errorCode)) {
                // In some cases, this should be returned perhaps...
                $this->error('LibGuides Error: ' . $returnVal->errorCode);
            }
            return $returnVal;
        } else {
            $this->error(
                'LibGuides API Error: Nothing returned from API call.'
            );
            $this->debug(
                'Body return from LibGuides API Call: ' . $this->varDump($body)
            );
        }
        return null;
    }
}
