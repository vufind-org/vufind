<?php

/**
 * OpenID Connect authentication module.
 *
 * PHP version 8
 *
 * Copyright (C) R-Bit Technology 2018-2024.
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
 * @package  Authentication
 * @author   Josef Moravec <josef.moravec@gmail.com>
 * @author   Radek Šiman <rbit@rbit.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Auth;

use Laminas\Session\Container as SessionContainer;
use VuFind\Exception\Auth as AuthException;

use function in_array;
use function is_int;

/**
 * OpenID Connect authentication module.
 *
 * @category VuFind
 * @package  Authentication
 * @author   Josef Moravec <josef.moravec@gmail.com>
 * @author   Radek Šiman <rbit@rbit.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class OpenIDConnect extends AbstractBase implements \VuFindHttp\HttpServiceAwareInterface
{
    use \VuFindHttp\HttpServiceAwareTrait;

    /**
     * Current login target
     *
     * @var string
     */
    protected string $target;

    /**
     * Request token
     *
     * @var object
     */
    protected object $requestToken;

    /**
     * OpenID Connect provider settings
     *
     * @var object
     */
    protected object $provider;

    /**
     * Constructor
     *
     * @param SessionContainer       $session    Session container for persisting state information.
     * @param \Laminas\Config\Config $oidcConfig Configuration
     */
    public function __construct(protected SessionContainer $session, protected \Laminas\Config\Config $oidcConfig)
    {
        if (empty($this->session->oidc_state)) {
            $this->session->oidc_state = hash('sha256', random_bytes(32));
        }
        if (empty($this->session->oidc_nonce)) {
            $this->session->oidc_nonce = hash('sha256', random_bytes(32));
        }
    }

    /**
     * Get configuration. Throw an exception if the configuration is invalid.
     *
     * @throws AuthException
     * @return \Laminas\Config\Config
     */
    public function getConfig(): \Laminas\Config\Config
    {
        // Validate configuration if not already validated:
        if (!$this->configValidated) {
            $this->validateConfig();
            $this->configValidated = true;
        }
        return $this->oidcConfig;
    }

    /**
     * Get provider configuration
     *
     * @return object
     * @throws AuthException
     */
    protected function getProvider(): object
    {
        if (!isset($this->provider)) {
            $url = $this->getConfig()->OpenIDConnect->url;
            $url .= str_ends_with($url, '/') ? '' : '/';
            $url .= '.well-known/openid-configuration';
            try {
                $this->provider = json_decode($this->httpService->get($url)->getBody());
            } catch (\Exception $e) {
                throw new AuthException(
                    'Cannot fetch provider configuration: ' . $e->getMessage()
                );
            }
        }
        return $this->provider;
    }

    /**
     * Validate configuration parameters. This is a support method for getConfig(), so the configuration MUST be
     * accessed using $this->oidcConfig; do not call $this->getConfig() from within this method!
     *
     * @throws AuthException
     * @return void
     */
    protected function validateConfig(): void
    {
        $requiredParams = ['url', 'client_id', 'client_secret'];
        foreach ($requiredParams as $param) {
            if (empty($this->oidcConfig?->OpenIDConnect?->$param ?? null)) {
                throw new AuthException(
                    'One or more OpenID Connect parameters are missing. Check your OpenIDConnect.ini!'
                );
            }
        }
    }

    /**
     * Attempt to authenticate the current user. Throws exception if login fails.
     *
     * @param \Laminas\Http\PhpEnvironment\Request $request Request object containing account credentials.
     *
     * @throws AuthException
     * @return \VuFind\Db\Row\User Object representing logged-in user.
     */
    public function authenticate($request): \VuFind\Db\Row\User
    {
        $code = $request->getQuery()->get('code');

        if (empty($code)) {
            throw new AuthException('authentication_error_admin');
        }
        $request_token = $this->getRequestToken($code);
        $state = $request->getQuery()->get('state');
        $stateIsValid = $state == $this->session->oidc_state;
        unset($this->session->oidc_state);
        if (!$stateIsValid) {
            throw new AuthException('authentication_error_admin: bad state');
        }

        $claims = $this->decodeJWT($request_token->id_token, 1);

        if (!$this->validateIssuer($claims->iss)) {
            throw new AuthException('authentication_error_admin: wrong issuer');
        }
        $claimsValid = $this->verifyJwtClaims($claims);
        unset($this->session->oidc_nonce);
        if (!$claimsValid) {
            throw new AuthException('authentication_error: not valid claims');
        }

        $access_token = $request_token->access_token;

        $user_info = $this->getUserInfo($access_token);
        $user = $this->getUserTable()->getByUsername($user_info->sub);
        if (isset($user_info->given_name)) {
            $user->firstname = $user_info->given_name;
        }
        if (isset($user_info->family_name)) {
            $user->lastname = $user_info->family_name;
        }
        if (isset($user_info->email)) {
            $user->updateEmail($user_info->email);
        }
        $user->save();
        return $user;
    }

    /**
     * Get the URL to establish a session (needed when the internal VuFind login form is inadequate). Returns false
     * when no session initiator is needed.
     *
     * @param string $target Full URL where external authentication method should send user after login (some drivers
     * may override this).
     *
     * @return bool|string
     */
    public function getSessionInitiator($target): bool|string
    {
        $provider = $this->getProvider();
        if (empty($this->target)) {
            // Adding the auth_method setting makes it possible to handle logins when
            // using an auth method that proxies others (e.g. ChoiceAuth)
            $this->target = $target . (str_contains($target, '?') ? '&' : '?') . 'auth_method=oidc';
        }
        $this->session->oidcLastUri = $this->target;
        $params = [
            'response_type' => 'code',
            'redirect_uri' => $target,
            'client_id' => $this->getConfig()->OpenIDConnect->client_id,
            'nonce' => $this->session->oidc_nonce,
            'state' => $this->session->oidc_state,
            'scope' => 'openid profile email',
        ];
        return $provider->authorization_endpoint . '?' . http_build_query($params);
    }

    /**
     * Obtain an access token from a code.
     *
     * @param string $code Code to look up.
     *
     * @return object
     */
    protected function getRequestToken(string $code): object
    {
        if (isset($this->requestToken)) {
            return $this->requestToken;
        }
        $url = $this->getProvider()->token_endpoint;
        $params = [
           'grant_type' => 'authorization_code',
           'code' => $code,
           'redirect_uri' => $this->session->oidcLastUri,
           'client_id' => $this->getConfig()->OpenIDConnect->client_id,
           'client_secret' => $this->getConfig()->OpenIDConnect->client_secret,
        ];
        $authMethods = $this->getProvider()->token_endpoint_auth_methods_supported;
        $headers = [];
        if (in_array('client_secret_basic', $authMethods)) {
            $headers = [
                'Authorization: Basic ' . base64_encode(
                    urlencode($this->getConfig()->OpenIDConnect->client_id) . ':'
                    . urlencode($this->getConfig()->OpenIDConnect->client_secret)
                ),
            ];
            unset($params['client_secret']);
        }

        $response = $this->httpService->post(
            $url,
            http_build_query($params),
            'application/x-www-form-urlencoded',
            null,
            $headers
        );
        $json = json_decode($response->getBody());
        if (isset($json->error)) {
            throw new AuthException('authentication_error' . ': ' . $json->error_description ?? $json->error);
        }
        $this->requestToken = $json;
        return $this->requestToken;
    }

    /**
     * Given an access token, look up user details.
     *
     * @param string $access_token Access token
     *
     * @return object
     */
    protected function getUserInfo(string $access_token): object
    {
        $url = $this->getProvider()->userinfo_endpoint;
        $params = [
            'schema' => 'openid',
        ];
        $headers = ['Authorization: Bearer ' . $access_token];
        return json_decode(
            $this->httpService->get($url, $params, null, $headers)->getBody()
        );
    }

    /**
     * Decode JSON Web Token
     *
     * @param string $jwt     JWT string
     * @param int    $section number of part to decode
     *
     * @return object
     */
    protected function decodeJWT(string $jwt, int $section = 0): object
    {
        $parts = explode('.', $jwt);
        $base64 = strtr($parts[$section], '-_', '+/');
        return json_decode(base64_decode($base64));
    }

    /**
     * Validate issuer
     *
     * @param string $iss Issuer
     *
     * @return bool
     */
    protected function validateIssuer(string $iss): bool
    {
        return $iss === ($this->getProvider()?->issuer ?? '');
    }

    /**
     * Verify the claims are valid
     *
     * @param object $claims Claims from authentication response
     *
     * @return bool
     */
    protected function verifyJwtClaims(object $claims): bool
    {
        return (!isset($claims->nonce) || $claims->nonce === $this->session->oidc_nonce)
            && ($claims->aud === $this->getConfig()->OpenIDConnect->client_id)
            && (!isset($claims->exp) || (is_int($claims->exp) && ($claims->exp > time())));
    }
}
