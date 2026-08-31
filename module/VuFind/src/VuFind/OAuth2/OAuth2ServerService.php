<?php

/**
 * OAuth2 server service.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2022-2026.
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
 * @package  OAuth2
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\OAuth2;

use Closure;
use Exception;
use Laminas\Session\Container as SessionContainer;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Entities\UserEntityInterface as OAuth2UserEntityInterface;
use League\OAuth2\Server\Exception\OAuthServerException;
use OpenIDConnectServer\ClaimExtractor;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\Auth\ILSAuthenticator;
use VuFind\Config\PathResolver;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Service\AccessTokenServiceInterface;
use VuFind\ILS\Connection;
use VuFind\OAuth2\Entity\UserEntity;
use VuFind\OAuth2\Repository\IdentityRepository;
use VuFind\Validator\CsrfInterface;

use function in_array;

/**
 * OAuth2 server service.
 *
 * @category VuFind
 * @package  OAuth2
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class OAuth2ServerService
{
    /**
     * Session container name.
     *
     * @var string
     */
    public const SESSION_NAME = 'OAuth2Server';

    /**
     * Constructor.
     *
     * @param Closure                     $authorizationServerFactory OAuth2 authorization server factory
     * @param Closure                     $resourceServerFactory      OAuth2 resource server factory
     * @param CsrfInterface               $csrf                       CSRF validator
     * @param SessionContainer            $sessionContainer           Session container
     * @param IdentityRepository          $identityRepository         Identity repository
     * @param AccessTokenServiceInterface $accessTokenService         Access token service
     * @param ClaimExtractor              $claimExtractor             Claim extractor
     * @param PathResolver                $pathResolver               Config file path resolver
     * @param ILSAuthenticator            $ilsAuthenticator           ILS authenticator
     * @param Connection                  $ilsConnection              ILS connection
     * @param array                       $oauth2Config               OAuth2 configuration
     * @param string                      $baseUrl                    VuFind base URL
     */
    public function __construct(
        protected Closure $authorizationServerFactory,
        protected Closure $resourceServerFactory,
        protected CsrfInterface $csrf,
        protected \Laminas\Session\Container $sessionContainer,
        protected IdentityRepository $identityRepository,
        protected AccessTokenServiceInterface $accessTokenService,
        protected ClaimExtractor $claimExtractor,
        protected PathResolver $pathResolver,
        protected ILSAuthenticator $ilsAuthenticator,
        protected Connection $ilsConnection,
        protected array $oauth2Config,
        protected string $baseUrl
    ) {
    }

    /**
     * Get authorization server for the specified client.
     *
     * @param ?string $clientId Client ID, or null for generic server without client-specific configuration
     *
     * @return AuthorizationServer
     */
    public function getAuthorizationServer(?string $clientId): AuthorizationServer
    {
        return ($this->authorizationServerFactory)($clientId);
    }

    /**
     * Get OAuth2 user entity from database user entity.
     *
     * @param UserEntityInterface $user Database user entity
     *
     * @return OAuth2UserEntityInterface
     */
    public function getOAuth2UserEntity(UserEntityInterface $user): OAuth2UserEntityInterface
    {
        return new UserEntity(
            $user,
            $this->ilsConnection,
            $this->oauth2Config,
            $this->accessTokenService,
            $this->ilsAuthenticator
        );
    }

    /**
     * Check if the OAuth2 server configuration is valid.
     *
     * @return bool
     */
    public function configValid(): bool
    {
        try {
            // Verify that config is good by creating the authorization service:
            ($this->authorizationServerFactory)(null);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Get client configuration.
     *
     * @param string $clientId Client ID
     *
     * @return array
     */
    public function getClientConfig(string $clientId): array
    {
        return $this->oauth2Config['Clients'][$clientId] ?? [];
    }

    /**
     * Get the configured user identifier field.
     *
     * @return string
     */
    public function getUserIdentifierField(): string
    {
        return $this->oauth2Config['Server']['userIdentifierField'] ?? 'id';
    }

    /**
     * Get JWKS as an array.
     *
     * @return array
     */
    public function getJwks(): array
    {
        if (!$this->configValid()) {
            return [];
        }
        $result = [];
        $keyPath = $this->oauth2Config['Server']['publicKeyPath'] ?? '';
        if (!str_starts_with($keyPath, '/')) {
            $keyPath = $this->pathResolver->getConfigPath($keyPath);
        }
        if (file_exists($keyPath)) {
            $keyDetails = openssl_pkey_get_details(openssl_pkey_get_public(file_get_contents($keyPath)));

            $encodeKeyData = function ($s) {
                return rtrim(
                    str_replace(
                        ['+', '/'],
                        ['-', '_'],
                        base64_encode($s)
                    ),
                    '='
                );
            };

            $result = [
                'keys' => [
                    [
                        'kty' => 'RSA',
                        'n' => $encodeKeyData($keyDetails['rsa']['n']),
                        'e' => $encodeKeyData($keyDetails['rsa']['e']),
                    ],
                ],
            ];
        }

        return $result;
    }

    /**
     * Get user information.
     *
     * @param ServerRequestInterface $request User info request
     *
     * @throws OAuthServerException
     *
     * @return array
     */
    public function getUserInfo(ServerRequestInterface $request): array
    {
        $request = ($this->resourceServerFactory)()->validateAuthenticatedRequest($request);
        $scopes = $request->getAttribute('oauth_scopes');
        if (!in_array('openid', $scopes)) {
            throw OAuthServerException::invalidRequest('token', 'Not an OpenID request');
        }
        $userId = $request->getAttribute('oauth_user_id');
        $userEntity = $this->identityRepository->getUserEntityByIdentifier($userId);
        if (!$userEntity) {
            throw OAuthServerException::accessDenied('User does not exist anymore');
        }
        $result = $this->claimExtractor->extract($scopes, $userEntity->getClaims());
        // The sub claim must always be returned:
        $result['sub'] = $userId;
        return $result;
    }

    /**
     * Get the OpenID Connect well-known configuration information.
     *
     * @return array
     *
     * @see https://openid.net/specs/openid-connect-discovery-1_0.html#ProviderConfigurationRequest
     */
    public function getWellKnownConfiguration(): array
    {
        $baseUrl = rtrim($this->baseUrl, '/');
        $configuration = [
            'issuer' => 'https://' . $_SERVER['HTTP_HOST'], // Same as OpenIDConnectServer\IdTokenResponse
            'authorization_endpoint' => "$baseUrl/OAuth2/Authorize",
            'token_endpoint' => "$baseUrl/OAuth2/Token",
            'userinfo_endpoint' => "$baseUrl/OAuth2/UserInfo",
            'jwks_uri' => "$baseUrl/OAuth2/jwks",
            'response_types_supported' => ['code'],
            'grant_types_supported' => ['authorization_code'],
            'subject_types_supported' => ['public'],
            'id_token_signing_alg_values_supported' => ['RS256'],
            'token_endpoint_auth_methods_supported' => [
                'client_secret_post',
                'client_secret_basic',
            ],
        ];
        if ($url = $this->oauth2Config['Server']['documentationUrl'] ?? null) {
            $configuration['service_documentation'] = $url;
        }
        if ($scopes = $this->oauth2Config['Scopes'] ?? []) {
            $configuration['scopes_supported'] = array_keys($scopes);
        }
        return $configuration;
    }
}
