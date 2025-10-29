<?php

/**
 * OpenID Connect authentication module.
 *
 * PHP version 8
 *
 * Copyright (C) R-Bit Technology 2018-2024.
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
 * @package  Authentication
 * @author   Josef Moravec <josef.moravec@gmail.com>
 * @author   Radek Šiman <rbit@rbit.cz>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Auth;

use DomainException;
use Firebase\JWT\BeforeValidException;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Firebase\JWT\SignatureInvalidException;
use InvalidArgumentException;
use Laminas\Session\Container as SessionContainer;
use UnexpectedValueException;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Exception\Auth as AuthException;
use VuFind\Exception\PasswordSecurity as PasswordSecurityException;

use function in_array;
use function is_int;

/**
 * OpenID Connect authentication module.
 *
 * @category VuFind
 * @package  Authentication
 * @author   Josef Moravec <josef.moravec@gmail.com>
 * @author   Radek Šiman <rbit@rbit.cz>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class OpenIDConnect extends AbstractBase implements \VuFindHttp\HttpServiceAwareInterface
{
    use \VuFindHttp\HttpServiceAwareTrait;

    /**
     * Request token
     *
     * @var object
     */
    protected array $requestTokens = [];

    /**
     * OpenID Connect provider settings
     *
     * @var object
     */
    protected object $provider;

    /**
     * Open Id connect JWKs
     *
     * @var array
     */
    protected array $jwks = [];

    /**
     * Default attributes mappings
     *
     * @var array
     */
    protected array $defaultAttributesMappings = [
        'firstname' => 'given_name',
        'lastname' => 'family_name',
        'email' => 'email',
    ];

    /**
     * Constructor
     *
     * @param SessionContainer $session          Session container for persisting state information.
     * @param array            $oidcConfig       Configuration
     * @param ILSAuthenticator $ilsAuthenticator ILS Authenticator
     *
     * @throws \Exception
     */
    public function __construct(
        protected SessionContainer $session,
        protected array $oidcConfig,
        protected ILSAuthenticator $ilsAuthenticator
    ) {
        $this->initState();
    }

    /**
     * Get configuration. Throw an exception if the configuration is invalid.
     *
     * @param string $key Configuration key
     *
     * @throws AuthException
     * @return null|string|array
     */
    public function getConfig(string $key = ''): null|string|array
    {
        $this->validateConfig();
        if (!empty($key)) {
            return $this->oidcConfig['Default'][$key] ?? null;
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
            $url = $this->getConfig('url');
            $url .= str_ends_with($url, '/') ? '' : '/';
            $url .= '.well-known/openid-configuration';
            try {
                $response = $this->httpService->get($url);
                if ($response->getStatusCode() !== 200) {
                    $this->logError(
                        'Failed to get provider metadata: Unexpected status ' . $response->getStatusCode()
                        . ': ' . $response->getBody()
                    );
                    throw new AuthException('authentication_error_technical');
                }
                $provider = json_decode($response->getBody());
            } catch (\Exception) {
                $provider = $this->getProviderFromConfig();
            }
            $this->validateProviderMetadata($provider);
            $this->provider = $provider;
        }
        return $this->provider;
    }

    /**
     * Get provider configuration from config
     *
     * @return object
     */
    protected function getProviderFromConfig(): object
    {
        $configKeys = [
            'authorization_endpoint',
            'token_endpoint',
            'token_endpoint_auth_methods_supported',
            'userinfo_endpoint',
            'issuer',
            'jwks_uri',
        ];
        $provider = new \stdClass();
        foreach ($configKeys as $key) {
            if (!empty($this->getConfig($key))) {
                $provider->$key = $this->getConfig($key);
            }
        }
        return $provider;
    }

    /**
     * Validate provider metadata
     *
     * @param object $provider Provider metadata
     *
     * @return bool
     * @throws AuthException
     */
    protected function validateProviderMetadata(object $provider): bool
    {
        $required = ['authorization_endpoint', 'token_endpoint', 'userinfo_endpoint', 'issuer', 'jwks_uri'];
        $missing = [];
        foreach ($required as $item) {
            if (!isset($provider->$item)) {
                $missing[] = $item;
            }
        }
        if (!empty($missing)) {
            $this->logError('Missing required provider metadata: ' . implode(', ', $missing));
            throw new AuthException('authentication_error_admin');
        }
        return true;
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
        if ($this->configValidated) {
            return;
        }
        $requiredParams = ['url', 'client_id', 'client_secret'];
        foreach ($requiredParams as $param) {
            if (empty($this->oidcConfig['Default'][$param] ?? null)) {
                $this->logError(
                    'One or more OpenID Connect parameters are missing. Check your OpenIDConnectClient.ini!'
                );
                throw new AuthException('authentication_error_admin');
            }
        }
        $this->configValidated = true;
    }

    /**
     * Attempt to authenticate the current user. Throws exception if login fails.
     *
     * @param \Laminas\Http\PhpEnvironment\Request $request Request object containing account credentials.
     *
     * @throws AuthException
     * @return UserEntityInterface Object representing logged-in user.
     */
    public function authenticate($request): UserEntityInterface
    {
        $code = $request->getQuery()->get('code');

        if (empty($code)) {
            throw new AuthException('authentication_error_admin');
        }
        $requestToken = $this->getRequestToken($code);
        $state = $request->getQuery()->get('state');
        $currentState = $this->session->oidc_state;
        $stateIsValid = $state === $currentState;
        $this->initState(true);
        if (!$stateIsValid) {
            $this->logError("Bad state: $currentState");
            throw new AuthException('authentication_error_technical');
        }

        $claims = $this->decodeJWT($requestToken->id_token);

        if (!$this->validateIssuer($claims->iss)) {
            $this->logError('Wrong issuer: ' . $claims->iss);
            throw new AuthException('authentication_error_admin');
        }
        $claimsValid = $this->verifyJwtClaims($claims);
        unset($this->session->oidc_nonce);
        if (!$claimsValid) {
            $this->logError('Claims not valid');
            throw new AuthException('authentication_error_technical');
        }

        $accessToken = $requestToken->access_token;
        $userInfo = $this->getUserInfo($accessToken);

        // Store id_token in session for logout
        $this->session->oidc_id_token = $requestToken->id_token;

        return $this->setUserAttributes($userInfo);
    }

    /**
     * Set user attributes from user info claim
     *
     * @param object $userInfo User info claim object
     *
     * @return UserEntityInterface
     * @throws AuthException
     * @throws PasswordSecurityException
     * @throws \Exception
     */
    protected function setUserAttributes(object $userInfo): UserEntityInterface
    {
        $availableAttributes = [
            'firstname',
            'lastname',
            'email',
            'cat_id',
            'cat_username',
            'cat_password',
            'college',
            'major',
            'home_library',
        ];
        $userService = $this->getUserService();
        $user = $this->getOrCreateUserByUsername(($this->getConfig('username_prefix') ?? '') . $userInfo->sub);
        $attrMappings = array_filter(
            $this->getAttributesMappings(),
            function ($key) use ($availableAttributes) {
                return in_array($key, $availableAttributes);
            },
            ARRAY_FILTER_USE_KEY
        );
        $catPassword = null;
        foreach ($attrMappings as $userAttr => $infoAttr) {
            $attrValue = $this->getAttributeValue($userInfo, $infoAttr);
            if (!empty($attrValue)) {
                if ($userAttr === 'email') {
                    $userService->updateUserEmail($user, $attrValue);
                    continue;
                }
                if ($userAttr === 'cat_password') {
                    $catPassword = $attrValue;
                    continue;
                }
                $this->setUserValueByField($user, $userAttr, $attrValue);
            }
        }
        $catUsername = $user->getCatUsername();
        if (!empty($catUsername)) {
            $finalCatPassword = $catPassword ?? $this->ilsAuthenticator->getCatPasswordForUser($user);
            $this->ilsAuthenticator->setUserCatalogCredentials($user, $catUsername, $finalCatPassword);
        }
        $userService->persistEntity($user);
        return $user;
    }

    /**
     * Get the URL to establish a session (needed when the internal VuFind login form is inadequate). Returns false
     * when no session initiator is needed.
     *
     * @param string $target Full URL where external authentication method should send user after login (some drivers
     * may override this).
     *
     * @return ?string
     * @throws AuthException
     */
    public function getSessionInitiator(string $target): ?string
    {
        // Adding the auth_method setting makes it possible to handle logins when
        // using an auth method that proxies others (e.g. ChoiceAuth)
        $targetUri = $target . (str_contains($target, '?') ? '&' : '?') . 'auth_method=OpenIDConnect';
        if (empty($this->session->oidcLastUri) && !empty($target)) {
            $this->session->oidcLastUri = $targetUri;
        }
        $params = [
            'response_type' => 'code',
            'redirect_uri' => $targetUri,
            'client_id' => $this->getConfig('client_id'),
            'nonce' => $this->session->oidc_nonce,
            'state' => $this->session->oidc_state,
            'scope' => $this->getConfig('scope') ?? 'openid profile email',
        ];
        return $this->getProvider()->authorization_endpoint . '?' . http_build_query($params);
    }

    /**
     * Perform cleanup at logout time.
     *
     * @param string $url URL to redirect user to after logging out.
     *
     * @return string Redirect URL (modified for OpenIDConnect logout).
     */
    public function logout($url)
    {
        $redirectUrl = $url;
        $endSessionEndpoint = false;

        $logout = $this->getConfig('logout');

        if (!$logout) {
            // No logout configured, so don't logout from service provider.
            $this->debug('no logout URL given');
        } elseif (filter_var($logout, FILTER_VALIDATE_URL)) {
            // A valid URL was configured, use it.
            $endSessionEndpoint = $logout;
        } else {
            // Get end_session_endpoint from provider.
            $provider = $this->getProvider();
            $endSessionEndpoint = $provider->end_session_endpoint ?? null;
        }

        if ($endSessionEndpoint) {
            // Retrieve id_token from session.
            $idToken = $this->session->oidc_id_token ?? null;
            if ($idToken === null) {
                $this->logWarning('No id_token found in session data');
            } else {
                $params = [
                    'id_token_hint' => $idToken,
                    'post_logout_redirect_uri' => $url,
                ];
                $append = (str_contains($endSessionEndpoint, '?') ? '&' : '?');
                $redirectUrl = $endSessionEndpoint . $append . http_build_query($params);
            }
        }

        // Send back the redirect URL (possibly modified).
        return $redirectUrl;
    }

    /**
     * Obtain an access token from a code.
     *
     * @param string $code Code to look up.
     *
     * @return object
     * @throws AuthException
     */
    protected function getRequestToken(string $code): object
    {
        if ($this->requestTokens[$code] ?? false) {
            return $this->requestTokens[$code];
        }
        $provider = $this->getProvider();
        $url = $provider->token_endpoint;
        $params = [
           'grant_type' => 'authorization_code',
           'code' => $code,
           'redirect_uri' => $this->session->oidcLastUri,
           'client_id' => $this->getConfig('client_id'),
           'client_secret' => $this->getConfig('client_secret'),
        ];
        $authMethods = $provider->token_endpoint_auth_methods_supported ?? null;
        $headers = [];
        if (in_array('client_secret_basic', $authMethods) || null === $authMethods) {
            $headers = [
                'Authorization: Basic ' . base64_encode(
                    $this->getConfig('client_id') . ':'
                    . $this->getConfig('client_secret')
                ),
            ];
            unset($params['client_secret']);
        }

        try {
            $response = $this->httpService->post(
                $url,
                http_build_query($params),
                'application/x-www-form-urlencoded',
                null,
                $headers
            );
        } catch (\Exception $e) {
            $this->logError('Unexpected ' . $e::class . ': ' . $e->getMessage());
            throw new AuthException('Cannot get request token: HTTP connection error.');
        }
        if ($response->getStatusCode() !== 200) {
            $this->logError(
                'Failed to get request token: Unexpected status ' . $response->getStatusCode()
                . ': ' . $response->getBody()
            );
            throw new AuthException('authentication_error_technical');
        }

        $json = json_decode($response->getBody());
        if (isset($json->error)) {
            $this->logError('Failed to get request token: ' . ($json->error_description ?? $json->error));
            throw new AuthException('authentication_error_technical');
        }
        $this->requestTokens[$code] = $json;
        return $json;
    }

    /**
     * Given an access token, look up user details.
     *
     * @param string $accessToken Access token
     *
     * @return object
     * @throws AuthException
     */
    protected function getUserInfo(string $accessToken): object
    {
        $url = $this->getProvider()->userinfo_endpoint;
        $params = [
            'schema' => 'openid',
        ];
        $headers = ['Authorization: Bearer ' . $accessToken];
        try {
            $response = $this->httpService->get($url, $params, null, $headers);
        } catch (\Exception $e) {
            $this->logError('Failed to get user info: Request failed: ' . (string)$e);
            throw new AuthException('authentication_error_technical');
        }
        if ($response->getStatusCode() !== 200) {
            $this->logError('Failed to get user info: Unexpected status code ' . $response->getStatusCode());
            throw new AuthException('authentication_error_technical');
        }
        if (null === ($json = json_decode($response->getBody()))) {
            $this->logError('Failed to get user info: Unable to decode JSON from response: ' . $response->getBody());
            throw new AuthException('authentication_error_technical');
        }
        return $json;
    }

    /**
     * Decode JSON Web Token
     *
     * @param string $jwt JWT string
     *
     * @return object
     * @throws AuthException
     * @throws InvalidArgumentException
     * @throws UnexpectedValueException
     * @throws DomainException
     * @throws SignatureInvalidException
     * @throws BeforeValidException
     * @throws ExpiredException
     */
    protected function decodeJWT(string $jwt): object
    {
        [$headerEncoded] = explode('.', $jwt);
        $header = json_decode(base64_decode(strtr($headerEncoded, '-_', '+/')));
        $key = JWK::parseKey($this->getSignatureJwk($header->kid ?? null), $header->alg);
        return JWT::decode($jwt, $key);
    }

    /**
     * Validate issuer
     *
     * @param string $iss Issuer
     *
     * @return bool
     * @throws AuthException
     */
    protected function validateIssuer(string $iss): bool
    {
        return $iss === $this->getProvider()->issuer;
    }

    /**
     * Verify the claims are valid
     *
     * @param object $claims Claims from authentication response
     *
     * @return bool
     * @throws AuthException
     */
    protected function verifyJwtClaims(object $claims): bool
    {
        return (!isset($claims->nonce) || $claims->nonce === $this->session->oidc_nonce)
            && ($claims->aud === $this->getConfig('client_id'))
            && (!isset($claims->exp) || (is_int($claims->exp) && ($claims->exp > time())));
    }

    /**
     * Get attributes mappings
     *
     * @return array
     * @throws AuthException
     */
    protected function getAttributesMappings(): array
    {
        $configMappings = $this->getConfig('attributes') ?? [];
        return array_merge($this->defaultAttributesMappings, $configMappings);
    }

    /**
     * Get attribute value from user info
     *
     * @param object $userInfo  User info claim from OIDC server
     * @param string $attribute Attribute to get value for
     *
     * @return string
     */
    protected function getAttributeValue(object $userInfo, string $attribute): string
    {
        $attributeName = $this->oidcConfig->attributes[$attribute] ?? $attribute;
        return $userInfo->$attributeName ?? '';
    }

    /**
     * Get signature JWKs from provider
     *
     * @return array
     * @throws AuthException
     */
    protected function getSignatureJwks(): array
    {
        if (empty($this->jwks)) {
            try {
                $response = $this->httpService->get($this->getProvider()->jwks_uri);
            } catch (\Exception $e) {
                $this->logError('Unexpected ' . $e::class . ': ' . $e->getMessage());
                throw new AuthException('Cannot get JWKs: HTTP connection error.');
            }
            if ($response->getStatusCode() !== 200) {
                $this->logError('Failed to get JWKs: Unexpected status code ' . $response->getStatusCode());
                throw new AuthException('Failed to get JWKs');
            }
            $jwks = json_decode($response->getBody(), true);
            foreach ($jwks['keys'] as $i => $jwk) {
                // Filter for signature keys only.
                if (($jwk['use'] ?? '') === 'sig') {
                    $this->jwks[$jwk['kid'] ?? $i] = $jwk;
                }
            }
        }
        return $this->jwks;
    }

    /**
     * Get signature JWK data
     *
     * @param ?string $kid Key id or null for first (default)
     *
     * @return array
     * @throws AuthException
     */
    protected function getSignatureJwk(?string $kid): array
    {
        $jwks = $this->getSignatureJwks();
        if (null !== $kid) {
            if (!isset($jwks[$kid])) {
                $this->logError("JWK '$kid' not found");
                throw new AuthException('authentication_error_technical');
            }
            return $jwks[$kid];
        }
        return reset($jwks);
    }

    /**
     * Initialize OIDC state and nonce
     *
     * @param bool $resetState Reset existing state?
     *
     * @return void
     */
    protected function initState(bool $resetState = false): void
    {
        if ($resetState || empty($this->session->oidc_state)) {
            $this->session->oidc_state = hash('sha256', random_bytes(32));
        }
        if (empty($this->session->oidc_nonce)) {
            $this->session->oidc_nonce = hash('sha256', random_bytes(32));
        }
    }
}
