<?php

/**
 * Zotero OAuth Connector
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2025.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Zotero
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

declare(strict_types=1);

namespace VuFind\Export\Zotero;

use League\OAuth1\Client\Credentials\ClientCredentialsInterface;
use League\OAuth1\Client\Credentials\CredentialsException;
use League\OAuth1\Client\Credentials\TokenCredentials;
use League\OAuth1\Client\Server\User;
use League\OAuth1\Client\Signature\SignatureInterface;
use VuFind\Http\GuzzleService;

use function is_array;

/**
 * Zotero OAuth Connector
 *
 * Class for authenticating with Zotero using OAuth.
 *
 * @category VuFind
 * @package  Zotero
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class ZoteroOAuth extends \League\OAuth1\Client\Server\Server
{
    /**
     * Zotero OAuth base URL including the trailing slash.
     *
     * @var string
     */
    protected string $baseUrl = 'https://www.zotero.org/oauth/';

    /**
     * Constructor.
     *
     * @param GuzzleService                    $guzzleService     Guzzle service
     * @param string                           $serviceName       VuFind title
     * @param ClientCredentialsInterface|array $clientCredentials Client credentials
     * @param SignatureInterface               $signature         Signature
     */
    public function __construct(
        protected GuzzleService $guzzleService,
        protected string $serviceName,
        $clientCredentials,
        ?SignatureInterface $signature = null
    ) {
        parent::__construct($clientCredentials, $signature);
    }

    /**
     * Get the OAuth URL for retrieving temporary credentials.
     *
     * @return string
     */
    public function urlTemporaryCredentials()
    {
        return $this->baseUrl . 'request';
    }

    /**
     * Get the OAuth URL for redirecting the resource owner to authorize the client.
     *
     * @return string
     */
    public function urlAuthorization()
    {
        $url = $this->baseUrl . 'authorize?library_access=1&write_access=1';
        if ($this->serviceName) {
            $url .= '&name=' . urlencode($this->serviceName);
        }
        return $url;
    }

    /**
     * Get the OAuth URL for retrieving token credentials.
     *
     * @return string
     */
    public function urlTokenCredentials()
    {
        return $this->baseUrl . 'access';
    }

    /**
     * Get the URL for retrieving user details.
     *
     * Stub, not used with Zotero.
     *
     * @return string
     */
    public function urlUserDetails()
    {
        return '';
    }

    /**
     * Take the decoded data from the user details URL and convert
     * it to a User object.
     *
     * Stub, not used with Zotero.
     *
     * @param mixed            $data             User data
     * @param TokenCredentials $tokenCredentials Token credentials
     *
     * @return User
     */
    public function userDetails($data, TokenCredentials $tokenCredentials)
    {
        return new User();
    }

    /**
     * Take the decoded data from the user details URL and extract
     * the user's UID.
     *
     * Stub, not used with Zotero.
     *
     * @param mixed            $data             User data
     * @param TokenCredentials $tokenCredentials Token credentials
     *
     * @return string|int
     */
    public function userUid($data, TokenCredentials $tokenCredentials)
    {
        return '';
    }

    /**
     * Take the decoded data from the user details URL and extract
     * the user's email.
     *
     * Stub, not used with Zotero.
     *
     * @param mixed            $data             User data
     * @param TokenCredentials $tokenCredentials Token credentials
     *
     * @return string|null
     */
    public function userEmail($data, TokenCredentials $tokenCredentials)
    {
        return null;
    }

    /**
     * Take the decoded data from the user details URL and extract
     * the user's screen name.
     *
     * Stub, not used with Zotero.
     *
     * @param mixed            $data             User data
     * @param TokenCredentials $tokenCredentials Token credentials
     *
     * @return string|null
     */
    public function userScreenName($data, TokenCredentials $tokenCredentials)
    {
        return null;
    }

    /**
     * Creates a Guzzle HTTP client for the given URL.
     *
     * @return GuzzleHttpClient
     */
    public function createHttpClient()
    {
        return $this->guzzleService->createClient();
    }

    /**
     * Create token credentials from the response body.
     *
     * @param string $body Response body
     *
     * @return ZoteroTokenCredentials
     */
    protected function createTokenCredentials($body)
    {
        parse_str($body, $data);

        if (!$data || !is_array($data)) {
            throw new CredentialsException('Unable to parse token credentials response.');
        }

        if ($error = $data['error'] ?? null) {
            throw new CredentialsException("Error [$error] retrieving token credentials.");
        }

        $tokenCredentials = new ZoteroTokenCredentials();
        $tokenCredentials->setIdentifier($data['oauth_token']);
        $tokenCredentials->setSecret($data['oauth_token_secret']);
        $tokenCredentials->setUserId($data['userID']);

        return $tokenCredentials;
    }
}
