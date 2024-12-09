<?php

/**
 * Interface for classes using OauthServiceTrait.
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
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */

namespace VuFind\Connection;

/**
 * Interface for classes using OauthServiceTrait.
 *
 * Classes which use this interface should also implement LoggerAwareInterface.
 *
 * @category VuFind
 * @package  Connection
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
interface OauthServiceInterface
{
    /**
     * Authenticate via the OAuth Client Credentials grant type.
     *
     * @param string $oauthUrl     URL of thee OAuth service
     * @param string $clientId     client_id for a client_credentials grant
     * @param string $clientSecret client_secret for a client_credentials grant
     *
     * @return string token for the session or false
     *     if the token request failed
     *
     * @link https://www.oauth.com/oauth2-servers/access-tokens/client-credentials/
     */
    public function authenticateWithClientCredentials(
        $oauthUrl,
        $clientId,
        $clientSecret
    );
}
