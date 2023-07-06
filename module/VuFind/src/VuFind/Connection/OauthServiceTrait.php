<?php

/**
 * Helper trait for OAuth 2.0 connections.
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

/**
 * Helper trait for OAuth 2.0 connections.
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
trait OauthServiceTrait
{
    /**
     * Current OAuth token
     *
     * @var stdClass
     */
    protected $tokenData = null;

    /**
     * Authentiate via the OAuth Client Credentials grant type.
     *
     * @param string $oauthUrl     URL of thee OAuth service
     * @param string $clientId     client_id for a client_credentials grant
     * @param string $clientSecret client_secret for a client_credentials grant
     *
     * @return stdClass|bool token for the session or false
     *     if the token request failed
     *
     * @link https://www.oauth.com/oauth2-servers/access-tokens/client-credentials/
     */
    public function authenticateWithClientCredentials(
        $oauthUrl,
        $clientId,
        $clientSecret
    ) {
        $this->debug("connecting to API");
        $tokenData = $this->tokenData;
        $this->debug("Last API Token: " . print_r($tokenData, true));
        if (
            $tokenData == null
            || !isset($tokenData->access_token)
            || time() >= $tokenData->expirationTime
        ) {
            $authHeader = base64_encode(
                $clientId . ":" . $clientSecret
            );
            $headers = [
                'Content-Type: application/x-www-form-urlencoded;charset=UTF-8',
                "Authorization: Basic $authHeader",
            ];

            $this->client->setHeaders($headers);
            $this->client->setMethod("POST");
            $this->client->setRawBody("grant_type=client_credentials");
            $response = $this->client
                ->setUri($oauthUrl)
                ->send();

            if ($response->isServerError()) {
                $this->error(
                    "API HTTP Error: " .
                    $response->getStatusCode()
                );
                $this->debug("Request: " . $this->client->getRequest());
                return false;
            }

            $body = $response->getBody();
            $tokenData = json_decode($body);
            $this->debug(
                "TokenData returned from API Call: " . print_r(
                    $tokenData,
                    true
                )
            );
            if ($tokenData != null) {
                if (isset($tokenData->errorCode)) {
                    // In some cases, this should be returned perhaps...
                    $this->error("API Error: " . $tokenData->errorCode);
                    return false;
                } else {
                    $tokenData->expirationTime = time()
                        + ($tokenData->expires_in ?? 0);
                    $this->tokenData = $tokenData;
                    return $tokenData;
                }
            } else {
                $this->error(
                    "Error: Nothing returned from API call."
                );
                $this->debug(
                    "Body return from API Call: " . print_r($body, true)
                );
            }
        }
        return $tokenData;
    }
}
