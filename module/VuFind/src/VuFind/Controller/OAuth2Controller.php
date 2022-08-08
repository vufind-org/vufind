<?php
/**
 * OAuth2 Controller
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2022.
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

use Laminas\Http\Response;
use Laminas\Log\LoggerAwareInterface;
use Laminas\Psr7Bridge\Psr7Response;
use Laminas\Psr7Bridge\Psr7ServerRequest;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\Session\Container as SessionContainer;
use League\OAuth2\Server\Exception\OAuthServerException;
use LmcRbacMvc\Service\AuthorizationService as LmAuthorizationService;
use VuFind\Db\Table\AccessToken;
use VuFind\OAuth2\Entity\UserEntity;
use VuFind\OAuth2\Repository\IdentityRepository;
use VuFind\Validator\CsrfInterface;

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

    // Session container name
    public const SESSION_NAME = 'OAuth2Server';

    /**
     * OAuth2 configuration
     *
     * @var array
     */
    protected $oauth2Config;

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
     * Laminas authorization service
     *
     * @var LmAuthorizationService
     */
    protected $authService;

    /**
     * CSRF validator
     *
     * @var CsrfInterface
     */
    protected $csrf;

    /**
     * Session container
     *
     * @var SessionContainer
     */
    protected $session;

    /**
     * Identity repository
     *
     * @var IdentityRepository
     */
    protected $identityRepository;

    /**
     * Access token table
     *
     * @var AccessToken
     */
    protected $accessTokenTable;

    /**
     * Constructor
     *
     * @param ServiceLocatorInterface $sm      Service locator
     * @param array                   $config  OAuth2 configuration
     * @param callable                $asf     OAuth2 authorization server factory
     * @param callable                $rsf     OAuth2 resource server factory
     * @param LmAuthorizationService  $authSrv Laminas authorization service
     * @param CsrfInterface           $csrf    CSRF validator
     * @param SessionContainer        $session Session container
     * @param IdentityRepository      $ir      Identity repository
     * @param AccessToken             $at      Access token table
     */
    public function __construct(
        ServiceLocatorInterface $sm,
        array $config,
        callable $asf,
        callable $rsf,
        LmAuthorizationService $authSrv,
        CsrfInterface $csrf,
        \Laminas\Session\Container $session,
        IdentityRepository $ir,
        AccessToken $at
    ) {
        parent::__construct($sm);
        $this->oauth2Config = $config;
        $this->oauth2ServerFactory = $asf;
        $this->resourceServerFactory = $rsf;
        $this->authService = $authSrv;
        $this->csrf = $csrf;
        $this->session = $session;
        $this->identityRepository = $ir;
        $this->accessTokenTable = $at;
    }

    /**
     * Execute the request
     *
     * @param \Laminas\Mvc\MvcEvent $e Event
     *
     * @return mixed
     * @throws Exception\DomainException
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
        if (!($user = $this->getUser())) {
            return $this->forceLogin('external_auth_access_login_message');
        }

        // Validate the authorization request:
        $laminasRequest = $this->getRequest();
        $clientId = $laminasRequest->getQuery('client_id');
        if (empty($clientId)
            || !($clientConfig = $this->oauth2Config['Clients'][$clientId] ?? [])
        ) {
            throw new \Exception("Invalid OAuth2 client $clientId");
        }
        $server = ($this->oauth2ServerFactory)($clientId);
        try {
            $authRequest = $server->validateAuthorizationRequest(
                Psr7ServerRequest::fromLaminas($this->getRequest())
            );
        } catch (OAuthServerException $exception) {
            return $this->convertOAuthServerExceptionToResponse($exception);
        } catch (\Exception $e) {
            return $this->handleException('Authorization request', $e);
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
            $this->accessTokenTable
                ->storeNonce($user->id, $laminasRequest->getQuery('nonce'));

            $authRequest->setUser(
                new UserEntity(
                    $user,
                    $this->getILS(),
                    $this->oauth2Config,
                    $this->accessTokenTable
                )
            );
            $authRequest->setAuthorizationApproved($this->formWasSubmitted('allow'));

            try {
                $response = $server->completeAuthorizationRequest(
                    $authRequest,
                    new \Laminas\Diactoros\Response()
                );
                return Psr7Response::toLaminas($response);
            } catch (OAuthServerException $exception) {
                return $this->convertOAuthServerExceptionToResponse($exception);
            } catch (\Exception $e) {
                return $this->handleException('Authorize request', $e);
            }
        }

        $patron = $this->catalogLogin();
        $patronLoginView = is_array($patron) ? null : $patron;
        return $this->createViewModel(
            compact('authRequest', 'user', 'patron', 'patronLoginView')
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
        } catch (OAuthServerException $exception) {
            return $this->convertOAuthServerExceptionToResponse($exception);
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
                throw OAuthServerException::invalidRequest(
                    'token',
                    'Not an OpenID request'
                );
            }
            $userId = $request->getAttribute('oauth_user_id');
            $userEntity = $this->identityRepository
                ->getUserEntityByIdentifier($userId);
            if (!$userEntity) {
                return $this->convertOAuthServerExceptionToResponse(
                    OAuthServerException::accessDenied('User does not exist anymore')
                );
            }
            $userClaims = $userEntity->getClaims();
            $result = [
                'sub' => $userId
            ];
            foreach ($scopes as $scope) {
                foreach ($this->oauth2Config['Scopes'][$scope]['claims'] ?? []
                    as $claim
                ) {
                    if (isset($userClaims[$claim])) {
                        $result[$claim] = $userClaims[$claim];
                    }
                }
            }
            return $this->getJsonResponse($result);
        } catch (OAuthServerException $exception) {
            return $this->convertOAuthServerExceptionToResponse($exception);
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
        $result = [];
        $keyPath = $this->oauth2Config['Server']['publicKeyPath'] ?? '';
        if (strncmp($keyPath, '/', 1) !== 0) {
            $keyPath = \VuFind\Config\Locator::getConfigPath($keyPath);
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
     * Get a JSON response from an array of data
     *
     * @param array $data Data to encode
     *
     * @return Response
     */
    protected function getJsonResponse(array $data): Response
    {
        $response = new Response();
        $response->setStatusCode(200);
        $response->getHeaders()->addHeaderLine('Content-type', 'application/json');
        $response->setContent(json_encode($data));
        return $response;
    }

    /**
     * Add CORS headers to a response.
     *
     * @param \Laminas\Http\Response $response Response
     *
     * @return void
     */
    protected function addCorsHeaders(\Laminas\Http\Response $response): void
    {
        $headers = $response->getHeaders();
        $headers->addHeaderLine(
            'Access-Control-Allow-Methods',
            'GET, POST, OPTIONS'
        );
        $headers->addHeaderLine('Access-Control-Max-Age', '86400');
        $headers->addHeaderLine('Access-Control-Allow-Origin: *');
    }
}
