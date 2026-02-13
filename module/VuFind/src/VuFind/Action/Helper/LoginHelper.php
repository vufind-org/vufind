<?php

/**
 * Action helper for login-related functionality.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2026.
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
 * @package  Action_Helper
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 */

namespace VuFind\Action\Helper;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\Action\Helper\PluginManager as HelperPluginManager;
use VuFind\Auth\ILSAuthenticator;
use VuFind\Auth\Manager as AuthManager;
use VuFind\Exception\Auth as AuthException;
use VuFind\Exception\ILS as ILSException;
use VuFind\Http\RouteHelper;
use VuFind\ILS\Connection;
use VuFind\ServiceManager\Factory\Autowire;
use VuFind\Session\Helper\FollowupHelper;
use VuFind\View\FlashMessenger\FlashMessengerInterface;

/**
 * Action helper for login-related functionality.
 *
 * @category VuFind
 * @package  Action_Helper
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 */
class LoginHelper extends AbstractHelper
{
    /**
     * Constructor
     *
     * @param RouteHelper             $routeHelper      Route helper
     * @param FollowupHelper          $followupHelper   Follow-up helper
     * @param FlashMessengerInterface $flashMessenger   Flash messenger
     * @param ForwardHelper           $forwardHelper    Forward helper
     * @param RedirectHelper          $redirectHelper   Redirect helper
     * @param AuthManager             $authManager      Authentication manager
     * @param ILSAuthenticator        $ilsAuthenticator ILS authenticator
     * @param Connection              $ils              ILS connection
     */
    #[Autowire()]
    public function __construct(
        protected RouteHelper $routeHelper,
        protected FollowupHelper $followupHelper,
        protected FlashMessengerInterface $flashMessenger,
        #[Autowire(container: HelperPluginManager::class)] protected ForwardHelper $forwardHelper,
        #[Autowire(container: HelperPluginManager::class)] protected RedirectHelper $redirectHelper,
        protected AuthManager $authManager,
        protected ILSAuthenticator $ilsAuthenticator,
        protected Connection $ils,
    ) {
    }

    /**
     * Redirect the user to the login screen.
     *
     * @param ServerRequestInterface $request  Request
     * @param ResponseInterface      $response Response
     * @param ?string                $msg      Flash message to display on login screen
     * @param array                  $extras   Associative array of extra fields to store
     * @param bool                   $forward  True to forward, false to redirect
     *
     * @return ResponseInterface
     */
    public function forceLogin(
        ServerRequestInterface $request,
        ResponseInterface $response,
        ?string $msg = null,
        array $extras = [],
        bool $forward = true
    ): ResponseInterface {
        // Set default message if necessary.
        $msg ??= 'You must be logged in first';

        // store parent url of lightboxes
        $extras['lightboxParent'] = $request->getQueryParams()['lightboxParent'] ?? null;

        // Store the current URL as a login followup action
        $this->followupHelper->store($extras);
        if (!empty($msg)) {
            $this->flashMessenger->addMessage($msg, 'error');
        }

        // Set a flag indicating that we are forcing login:
        $body = $request->getParsedBody();
        $body['forcingLogin'] = true;
        $request = $request->withParsedBody($body);

        if ($forward) {
            return $this->forwardHelper->forwardTo($request, $response, 'myresearch/login');
        }

        return $this->redirectHelper->redirectToRoute($response, 'myresearch-home');
    }

    /**
     * Does the user have catalog credentials available?  Returns associative array
     * of patron data if so, otherwise forwards to appropriate login prompt and
     * returns false. If there is an ILS exception, a flash message is added and
     * null is returned.
     *
     * @param ServerRequestInterface $request  Request
     * @param ResponseInterface      $response Response
     *
     * @return bool|array|ResponseInterface|null
     */
    protected function catalogLogin(
        ServerRequestInterface $request,
        ResponseInterface $response,
    ): bool|array|ResponseInterface|null {
        // First make sure user is logged in to VuFind:
        if (!($user = $this->authManager->getUserObject())) {
            return $this->forceLogin($request, $response);
        }

        // Now check if the user has provided credentials with which to log in:
        $patron = null;
        $postParams = $request->getParsedBody();
        $queryParams = $request->getQueryParams();
        if (
            ($username = $postParams['cat_username'] ?? null)
            && ($password = $postParams['cat_password'] ?? null)
        ) {
            // If somebody is POSTing credentials but that logic is disabled, we
            // should throw an exception!
            if (!$this->authManager->allowsUserIlsLogin()) {
                throw new \Exception('Unexpected ILS credential submission.');
            }
            // Check for multiple ILS target selection
            $target = $postParams['target'] ?? null;
            if ($target) {
                $username = "$target.$username";
            }
            try {
                if ('email' === $this->getILSLoginMethod($target)) {
                    $routeMatch = $request->getAttribute('route-match');
                    $routeName = $routeMatch ? $routeMatch->getMatchedRouteName()
                        : 'myresearch-profile';
                    $routeParams = $routeMatch ? $routeMatch->getParams() : [];
                    $this->ilsAuthenticator
                        ->sendEmailLoginLink($username, $routeName, $routeParams, ['catalogLogin' => 'true'], $user);
                    $this->flashMessenger->addSuccessMessage('email_login_link_sent');
                } else {
                    $patron = $this->ilsAuthenticator->newCatalogLogin($username, $password, $user);

                    // If login failed, store a warning message:
                    if (!$patron) {
                        $this->flashMessenger->addErrorMessage('Invalid Patron Login');
                    }
                }
            } catch (ILSException $e) {
                $this->flashMessenger->addErrorMessage('ils_connection_failed');
            }
        } elseif (
            ('ILS' === $queryParams['auth_method'] ?? null)
            && ($hash = $queryParams['hash'] ?? null)
        ) {
            try {
                $patron = $this->ilsAuthenticator->processEmailLoginHash($hash);
            } catch (AuthException $e) {
                $this->flashMessenger->addErrorMessage($e->getMessage());
            }
        } else {
            try {
                // If no credentials were provided, try the stored values:
                $patron = $this->ilsAuthenticator->storedCatalogLogin();
            } catch (ILSException $e) {
                $this->flashMessenger->addErrorMessage('ils_connection_failed');
                return null;
            }
        }

        // If catalog login failed, send the user to the right page:
        if (!$patron) {
            return $this->forwardHelper->forwardTo($request, $response, 'myresearch/cataloglogin');
        }

        // Send value (either false or patron array) back to caller:
        return $patron;
    }

    /**
     * What login method does the ILS use (password, email, vufind)
     *
     * @param string $target Login target (MultiILS only)
     *
     * @return string
     */
    public function getILSLoginMethod(string $target = ''): string
    {
        $config = $this->ils->checkFunction(
            'patronLogin',
            ['patron' => ['cat_username' => "$target.login"]]
        );
        return $config['loginMethod'] ?? 'password';
    }
}
