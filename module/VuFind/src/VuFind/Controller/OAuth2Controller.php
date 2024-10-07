<?php

/**
 * OAuth2 Controller
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2022-2024.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.    See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Controller
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Controller;

use Laminas\Http\Exception\InvalidArgumentException;
use Laminas\Http\Response;
use Laminas\Log\LoggerAwareInterface;
use Laminas\Mvc\Exception\DomainException;
use Laminas\Psr7Bridge\Psr7Response;
use Laminas\Psr7Bridge\Psr7ServerRequest;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\Session\Container as SessionContainer;
use League\OAuth2\Server\Exception\OAuthServerException;
use OpenIDConnectServer\ClaimExtractor;
use VuFind\Config\PathResolver;
use VuFind\Db\Service\AccessTokenServiceInterface;
use VuFind\Exception\BadRequest as BadRequestException;
use VuFind\OAuth2\Entity\UserEntity;
use VuFind\OAuth2\Repository\IdentityRepository;
use VuFind\Validator\CsrfInterface;

use function in_array;
use function is_array;

/**
 * OAuth2 Controller
 *
 * Provides authorization support for external systems
 *
 * @category VuFind
 * @package  Controller
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class OAuth2Controller extends AbstractBase implements LoggerAwareInterface
{
    use \VuFind\Log\LoggerAwareTrait;
    use Feature\ResponseFormatterTrait;

    // Session container name
    public const SESSION_NAME = 'OAuth2Server';

    /**
     * OAuth2 authorization server factory
     *
     * @var callable
     */
    protected $oauth2ServerFactory;

    /**
     * OAuth2 resource server factory
     *
     * @var callable
     */
    protected $resourceServerFactory;

    /**
     * Constructor
     *
     * @param ServiceLocatorInterface     $sm                 Service locator
     * @param array                       $oauth2Config       OAuth2 configuration
     * @param callable                    $asf                OAuth2 authorization server factory
     * @param callable                    $rsf                OAuth2 resource server factory
     * @param CsrfInterface               $csrf               CSRF validator
     * @param SessionContainer            $session            Session container
     * @param IdentityRepository          $identityRepository Identity repository
     * @param AccessTokenServiceInterface $accessTokenService Access token service
     * @param ClaimExtractor              $claimExtractor     Claim extractor
     * @param PathResolver                $pathResolver       Config file path resolver
     * path
     */
    public function __construct(
        ServiceLocatorInterface $sm,
        protected array $oauth2Config,
        callable $asf,
        callable $rsf,
        protected CsrfInterface $csrf,
        protected \Laminas\Session\Container $session,
        protected IdentityRepository $identityRepository,
        protected AccessTokenServiceInterface $accessTokenService,
        protected ClaimExtractor $claimExtractor,
        protected PathResolver $pathResolver
    ) {
        parent::__construct($sm);
        $this->oauth2ServerFactory = $asf;
        $this->resourceServerFactory = $rsf;
    }

    /**
     * Execute the request
     *
     * @param \Laminas\Mvc\MvcEvent $e Event
     *
     * @return mixed
     * @throws DomainException
     * @throws InvalidArgumentException
     */
    public function onDispatch(\Laminas\Mvc\MvcEvent $e)
    {
        // Add CORS headers and handle OPTIONS requests. This is a simplistic
        // approach since we allow any origin. For more complete CORS handling
        // a module like zfr-cors could be used.
        $request = $this->getRequest();
        if ($request->getMethod() == 'OPTIONS') {
            // Disable session writes
            $this->disableSessionWrites();
            $response = $this->getResponse();
            $response->setStatusCode(204);
            $this->addCorsHeaders($response);
            return $response;
        }
        return parent::onDispatch($e);
    }

    /**
     * OAuth2 authorization request action
     *
     * @return mixed
     */
    public function authorizeAction()
    {
        // Validate the authorization request:
        $laminasRequest = $this->getRequest();
        $clientId = $laminasRequest->getQuery('client_id');
        if (
            empty($clientId)
            || !($clientConfig = $this->oauth2Config['Clients'][$clientId] ?? [])
        ) {
            throw new BadRequestException("Invalid OAuth2 client $clientId");
        }

        if (!($user = $this->getUser())) {
            return $this->forceLogin('external_auth_access_login_message');
        }

        $server = ($this->oauth2ServerFactory)($clientId);
        try {
            $authRequest = $server->validateAuthorizationRequest(
                Psr7ServerRequest::fromLaminas($this->getRequest())
            );
        } catch (OAuthServerException $e) {
            return $this->handleOAuth2Exception('Authorization request', $e);
        } catch (\Exception $e) {
            return $this->handleException('Authorization request', $e);
        }

        // Hide any scopes not allowed by a client-specific filter (see also ScopeRepository for the actual filtering):
        if ($allowedScopes = $clientConfig['allowedScopes'] ?? null) {
            $scopes = $authRequest->getScopes();
            array_map(
                function ($scope) use ($allowedScopes) {
                    if (!in_array($scope->getIdentifier(), $allowedScopes)) {
                        $scope->setHidden(true);
                    }
                },
                $scopes
            );
            $authRequest->setScopes($scopes);
        }

        if ($this->formWasSubmitted('allow') || $this->formWasSubmitted('deny')) {
            // Check CSRF and session:
            if (!$this->csrf->isValid($this->getRequest()->getPost()->get('csrf'))) {
                throw new \VuFind\Exception\BadRequest(
                    'error_inconsistent_parameters'
                );
            }

            // Store OpenID nonce (or null if not present to clear any existing one)
            // in the access token table so that it can be retrieved for token or
            // user info action:
            $this->accessTokenService
                ->storeNonce($user->getId(), $laminasRequest->getQuery('nonce'));

            $authRequest->setUser(
                new UserEntity(
                    $user,
                    $this->getILS(),
                    $this->oauth2Config,
                    $this->accessTokenService,
                    $this->getILSAuthenticator()
                )
            );
            $authRequest->setAuthorizationApproved($this->formWasSubmitted('allow'));

            try {
                $response = $server->completeAuthorizationRequest(
                    $authRequest,
                    new \Laminas\Diactoros\Response()
                );
                return Psr7Response::toLaminas($response);
            } catch (OAuthServerException $e) {
                return $this->handleOAuth2Exception('Authorization request', $e);
            } catch (\Exception $e) {
                return $this->handleException('Authorization request', $e);
            }
        }

        $userIdentifierField = $this->oauth2Config['Server']['userIdentifierField'] ?? 'id';
        $patron = $this->catalogLogin();
        $patronLoginView = is_array($patron) ? null : $patron;
        if ($patronLoginView instanceof \Laminas\View\Model\ViewModel) {
            $patronLoginView->showMenu = false;
        }
        return $this->createViewModel(
            compact('authRequest', 'user', 'patron', 'patronLoginView', 'userIdentifierField')
        );
    }

    /**
     * OAuth2 token request action
     *
     * @return mixed
     */
    public function tokenAction()
    {
        $this->disableSessionWrites();
        $server = ($this->oauth2ServerFactory)(null);
        try {
            $response = $server->respondToAccessTokenRequest(
                Psr7ServerRequest::fromLaminas($this->getRequest()),
                new \Laminas\Diactoros\Response()
            );
            $response = Psr7Response::toLaminas($response);
            $this->addCorsHeaders($response);
            return $response;
        } catch (OAuthServerException $e) {
            return $this->handleOAuth2Exception('Access token request', $e);
        } catch (\Exception $e) {
            return $this->handleException('Access token request', $e);
        }
    }

    /**
     * OpenID Connect user info request action
     *
     * @return mixed
     */
    public function userInfoAction()
    {
        $this->disableSessionWrites();
        try {
            $laminasRequest = $this->getRequest();
            $request = ($this->resourceServerFactory)()
                ->validateAuthenticatedRequest(
                    Psr7ServerRequest::fromLaminas($laminasRequest)
                );
            $scopes = $request->getAttribute('oauth_scopes');
            if (!in_array('openid', $scopes)) {
                return $this->handleOAuth2Exception(
                    'User info request',
                    OAuthServerException::invalidRequest(
                        'token',
                        'Not an OpenID request'
                    )
                );
            }
            $userId = $request->getAttribute('oauth_user_id');
            $userEntity = $this->identityRepository
                ->getUserEntityByIdentifier($userId);
            if (!$userEntity) {
                return $this->handleOAuth2Exception(
                    'User info request',
                    OAuthServerException::accessDenied('User does not exist anymore')
                );
            }
            $result = $this->claimExtractor->extract($scopes, $userEntity->getClaims());
            // The sub claim must always be returned:
            $result['sub'] = $userId;
            return $this->getJsonResponse($result);
        } catch (OAuthServerException $e) {
            return $this->handleOAuth2Exception('User info request', $e);
        } catch (\Exception $e) {
            return $this->handleException('User info request', $e);
        }
    }

    /**
     * Action to retrieve JSON Web Keys
     *
     * @see https://www.tuxed.net/fkooman/blog/json_web_key_set.html
     *
     * @return mixed
     */
    public function jwksAction()
    {
        // Check that authorization server can be created (means that config is good):
        try {
            ($this->oauth2ServerFactory)(null);
        } catch (\Exception $e) {
            return $this->createHttpNotFoundModel($this->getResponse());
        }
        $result = [];
        $keyPath = $this->oauth2Config['Server']['publicKeyPath'] ?? '';
        if (strncmp($keyPath, '/', 1) !== 0) {
            $keyPath = $this->pathResolver->getConfigPath($keyPath);
        }
        if (file_exists($keyPath)) {
            $keyDetails = openssl_pkey_get_details(
                openssl_pkey_get_public(file_get_contents($keyPath))
            );

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

        return $this->getJsonResponse($result);
    }

    /**
     * Action to retrieve the OIDC configuration
     *
     * @return mixed
     */
    public function wellKnownConfigurationAction()
    {
        // Check that authorization server can be created (means that config is good):
        try {
            ($this->oauth2ServerFactory)(null);
        } catch (\Exception $e) {
            return $this->createHttpNotFoundModel($this->getResponse());
        }
        $baseUrl = rtrim($this->getServerUrl('home'), '/');
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

        return $this->getJsonResponse($configuration);
    }

    /**
     * Convert an instance of OAuthServerException to a Laminas response.
     *
     * @param OAuthServerException $exception Exception
     *
     * @return Response
     */
    protected function convertOAuthServerExceptionToResponse(
        OAuthServerException $exception
    ): Response {
        $psr7Response = $exception->generateHttpResponse(
            new \Laminas\Diactoros\Response()
        );
        $response = Psr7Response::toLaminas($psr7Response);
        $this->addCorsHeaders($response);
        return $response;
    }

    /**
     * Create a server error response.
     *
     * @param string     $function Function description
     * @param \Exception $e        Exception
     *
     * @return Response
     */
    protected function handleException(string $function, \Exception $e): Response
    {
        $this->logError("$function failed: " . (string)$e);

        return $this->convertOAuthServerExceptionToResponse(
            OAuthServerException::serverError('Server side issue')
        );
    }

    /**
     * Create a server error response from a returnable exception.
     *
     * @param string     $function Function description
     * @param \Exception $e        Exception
     *
     * @return Response
     */
    protected function handleOAuth2Exception(string $function, \Exception $e): Response
    {
        $this->debug("$function exception: " . (string)$e);

        return $this->convertOAuthServerExceptionToResponse($e);
    }
}
