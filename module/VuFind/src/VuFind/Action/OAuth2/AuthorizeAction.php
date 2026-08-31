<?php

/**
 * OAuth2 authorization action.
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
 * @package  Action
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Action\OAuth2;

use Exception;
use League\OAuth2\Server\Exception\OAuthServerException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\ActionHelper\FormHelper;
use VuFind\ActionHelper\LoginHelper;
use VuFind\Auth\Manager as AuthManager;
use VuFind\Db\Service\AccessTokenServiceInterface;
use VuFind\Db\Service\PluginManager as DbServicePluginManager;
use VuFind\Exception\BadRequest as BadRequestException;
use VuFind\ILS\Connection;
use VuFind\OAuth2\Entity\ScopeEntity;
use VuFind\OAuth2\OAuth2ServerService;
use VuFind\ServiceManager\Factory\Autowire;
use VuFind\Validator\CsrfInterface;

use function in_array;

/**
 * OAuth2 authorization action.
 *
 * @category VuFind
 * @package  Action
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class AuthorizeAction extends AbstractOAuth2Action
{
    /**
     * Constructor.
     *
     * @param OAuth2ServerService         $oauth2Service      OAuth2 server service
     * @param AuthManager                 $authManager        Authentication manager
     * @param CsrfInterface               $csrf               CSRF validator
     * @param AccessTokenServiceInterface $accessTokenService Access token database service
     * @param Connection                  $ilsConnection      ILS connection
     */
    public function __construct(
        OAuth2ServerService $oauth2Service,
        protected AuthManager $authManager,
        protected CsrfInterface $csrf,
        #[Autowire(container: DbServicePluginManager::class)]
        protected AccessTokenServiceInterface $accessTokenService,
        protected Connection $ilsConnection
    ) {
        parent::__construct($oauth2Service);
    }

    /**
     * Handle an authorization request.
     *
     * @param ServerRequestInterface $request  Server request
     * @param ResponseInterface      $response Response
     *
     * @return ResponseInterface
     */
    public function action(
        ServerRequestInterface $request,
        ResponseInterface $response,
    ): ResponseInterface {
        // Validate the authorization request:
        $clientId = $this->getQueryParam('client_id', '');
        if (
            '' === $clientId
            || !($clientConfig = $this->oauth2Service->getClientConfig($clientId))
        ) {
            throw new BadRequestException("Invalid OAuth2 client $clientId");
        }

        if (!($user = $this->authManager->getUserObject())) {
            return $this->getHelper(LoginHelper::class)
                ->forceLogin($request, $response, 'external_auth_access_login_message');
        }

        $authServer = $this->oauth2Service->getAuthorizationServer($clientId);
        try {
            $authRequest = $authServer->validateAuthorizationRequest($request);
        } catch (OAuthServerException $e) {
            return $this->handleOAuth2Exception($response, 'Authorization request', $e);
        } catch (\Exception $e) {
            return $this->handleOAuth2ServerException($response, 'Authorization request', $e);
        }

        // Hide any scopes not allowed by a client-specific filter (see also ScopeRepository for the actual filtering):
        if ($allowedScopes = $clientConfig['allowedScopes'] ?? null) {
            $scopes = $authRequest->getScopes();
            array_map(
                function ($scope) use ($allowedScopes): void {
                    if (!in_array($scope->getIdentifier(), $allowedScopes)) {
                        if (!($scope instanceof ScopeEntity)) {
                            throw new Exception('Scope must be an instance of ScopeEntity');
                        }
                        $scope->setHidden(true);
                    }
                },
                $scopes
            );
            $authRequest->setScopes($scopes);
        }

        $formHelper = $this->getHelper(FormHelper::class);
        if ($formHelper->formWasSubmitted($request, ['allow', 'deny'])) {
            // Check CSRF and session:
            if (!$this->csrf->isValid($this->getPostParam('csrf'))) {
                throw new \VuFind\Exception\BadRequest('error_inconsistent_parameters');
            }

            // Store OpenID nonce (or null if not present to clear any existing one)
            // in the access token table so that it can be retrieved for token or
            // user info action:
            $this->accessTokenService->storeNonce($user->getId(), $this->getQueryParam('nonce'));

            $authRequest->setUser($this->oauth2Service->getOAuth2UserEntity($user));
            $authRequest->setAuthorizationApproved($formHelper->formWasSubmitted($request, 'allow'));

            try {
                return $authServer->completeAuthorizationRequest($authRequest, $response);
            } catch (OAuthServerException $e) {
                return $this->handleOAuth2Exception($response, 'Authorization request', $e);
            } catch (\Exception $e) {
                return $this->handleOAuth2ServerException($response, 'Authorization request', $e);
            }
        }

        $userIdentifierField = $this->oauth2Service->getUserIdentifierField();
        $patron = $this->getHelper(LoginHelper::class)->catalogLogin($request, $response, false);
        if ($patron instanceof ResponseInterface) {
            return $patron;
        }
        $showCatalogLoginForm = !$patron;
        return $this->renderTemplate(
            $request,
            $response,
            compact('authRequest', 'user', 'patron', 'showCatalogLoginForm', 'userIdentifierField')
        );
    }
}
