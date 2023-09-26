<?php

/**
 * Trait OAuth2TokenTraitTest
 *
 * PHP version 8
 *
 * Copyright (C) Moravian Library 2021.
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
 * @package  VuFind\ILS
 * @author   Josef Moravec <moravec@mzk.cz>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://knihovny.cz Main Page
 */

declare(strict_types=1);

namespace VuFind\ILS\Driver;

use VuFind\Auth\AuthToken;
use VuFind\Exception\AuthToken as AuthTokenException;

/**
 * Trait OAuth2TokenTraitTest
 *
 * @category VuFind
 * @package  VuFind\ILS
 * @author   Josef Moravec <moravec@mzk.cz>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://knihovny.cz Main Page
 */
trait OAuth2TokenTrait
{
    use \VuFindHttp\HttpServiceAwareTrait;
    use \VuFind\Log\LoggerAwareTrait;

    /**
     * Get new authorization token from API using given credentials
     *
     * @param string $tokenEndpoint URL of token endpoint
     * @param string $clientId      Client id
     * @param string $clientSecret  Client secret
     * @param string $grantType     Grant type (usually 'client_credentials')
     * @param bool   $useHttpBasic  Use HTTP Basic authorization for getting token
     *
     * @return AuthToken
     * @throws AuthTokenException
     */
    public function getNewOAuth2Token(
        string $tokenEndpoint,
        string $clientId,
        string $clientSecret,
        string $grantType = 'client_credentials',
        bool $useHttpBasic = false
    ): \VuFind\Auth\AuthToken {
        $client = $this->httpService->createClient($tokenEndpoint);
        $client->setMethod('POST');
        $client->getRequest()->getHeaders()->addHeaderLine(
            'Content-Type',
            'application/x-www-form-urlencoded'
        );

        $postFields = ['grant_type' => $grantType];
        if ($useHttpBasic) {
            $client->setAuth($clientId, $clientSecret);
        } else {
            $postFields['client_id'] = $clientId;
            $postFields['client_secret'] = $clientSecret;
        }

        $client->setParameterPost($postFields);

        try {
            $response = $client->send();
        } catch (\Exception $e) {
            $this->logError(
                "POST request for '$tokenEndpoint' failed: " . $e->getMessage()
            );
            throw new AuthTokenException(
                'Problem getting authorization token: Request failed'
            );
        }

        if ($response->getStatusCode() != 200) {
            $errorMessage = "Error while getting OAuth2 access token from '$tokenEndpoint' (status code "
                . $response->getStatusCode() . '): ' . $response->getBody();
            $this->logError($errorMessage);
            throw new AuthTokenException(
                'Problem getting authorization token: Bad status code returned'
            );
        }
        $tokenData = json_decode($response->getBody(), true);

        if (
            empty($tokenData['token_type'])
            || empty($tokenData['access_token'])
        ) {
            $this->logError(
                "Did not receive OAuth2 token from '$tokenEndpoint', response: "
                . $response->getBody()
            );
            throw new AuthTokenException(
                'Problem getting authorization token: Empty data'
            );
        }
        return new AuthToken(
            $tokenData['access_token'],
            $tokenData['expires_in'] ?? null,
            $tokenData['token_type']
        );
    }
}
