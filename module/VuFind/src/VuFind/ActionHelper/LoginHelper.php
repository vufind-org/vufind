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

namespace VuFind\ActionHelper;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\ActionHelper\PluginManager as HelperPluginManager;
use VuFind\Auth\EmailAuthenticator;
use VuFind\Auth\ILSAuthenticator;
use VuFind\Auth\Manager as AuthManager;
use VuFind\Auth\UserSessionPersistenceInterface;
use VuFind\Db\Service\AuditEventService;
use VuFind\Db\Service\PluginManager as DbServicePluginManager;
use VuFind\Db\Type\AuditEventSubtype;
use VuFind\Db\Type\AuditEventType;
use VuFind\Exception\ILS as ILSException;
use VuFind\Http\RouteHelper;
use VuFind\Http\ServerUrlHelper;
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
class LoginHelper implements HelperInterface
{
    /**
     * Constructor.
     *
     * @param RouteHelper                     $routeHelper        Route helper
     * @param FollowupHelper                  $followupHelper     Follow-up helper
     * @param FlashMessengerInterface         $flashMessenger     Flash messenger
     * @param ForwardHelper                   $forwardHelper      Forward helper
     * @param RedirectHelper                  $redirectHelper     Redirect helper
     * @param AuthManager                     $authManager        Authentication manager
     * @param ILSAuthenticator                $ilsAuthenticator   ILS authenticator
     * @param Connection                      $ils                ILS connection
     * @param EmailAuthenticator              $emailAuthenticator Email authenticator
     * @param AuditEventService               $auditEventService  Audit event service
     * @param UserSessionPersistenceInterface $userSession        User session persistence service
     * @param ServerUrlHelper                 $serverUrlHelper    Server URL helper
     * @param UrlHelper                       $urlHelper          URL helper
     */
    #[Autowire()]
    public function __construct(
        protected RouteHelper $routeHelper,
        protected FollowupHelper $followupHelper,
        protected FlashMessengerInterface $flashMessenger,
        #[Autowire(container: HelperPluginManager::class)]
        protected ForwardHelper $forwardHelper,
        #[Autowire(container: HelperPluginManager::class)]
        protected RedirectHelper $redirectHelper,
        protected AuthManager $authManager,
        protected ILSAuthenticator $ilsAuthenticator,
        protected Connection $ils,
        protected EmailAuthenticator $emailAuthenticator,
        #[Autowire(container: DbServicePluginManager::class)]
        protected AuditEventService $auditEventService,
        #[Autowire(container: DbServicePluginManager::class)]
        protected UserSessionPersistenceInterface $userSession,
        protected ServerUrlHelper $serverUrlHelper,
        #[Autowire(container: HelperPluginManager::class)]
        protected UrlHelper $urlHelper,
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
            $this->flashMessenger->addErrorMessage($msg);
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
     * Does the user have catalog credentials available? Returns associative array of patron data if so, or a response
     * if redirect is needed. Otherwise returns null (only when forwardToCatalogLogin is false).
     * If there is an ILS exception, a flash message is added.
     *
     * @param ServerRequestInterface $request               Request
     * @param ResponseInterface      $response              Response
     * @param bool                   $forwardToCatalogLogin Forward to catalog login if not logged in?
     *
     * @return array|ResponseInterface|null
     */
    public function catalogLogin(
        ServerRequestInterface $request,
        ResponseInterface $response,
        bool $forwardToCatalogLogin = true
    ): array|ResponseInterface|null {
        // First make sure user is logged in to VuFind:
        if (!($user = $this->authManager->getUserObject())) {
            return $this->forceLogin($request, $response);
        }

        // Now check if the user has provided credentials with which to log in:
        $patron = null;
        $postParams = $request->getParsedBody();
        if (
            ($username = $postParams['cat_username'] ?? null)
            && ($password = $postParams['cat_password'] ?? null)
        ) {
            // If somebody is POSTing credentials but that logic is disabled, we
            // should throw an exception!
            if (!$this->authManager->allowsUserIlsLogin()) {
                throw new \Exception('Unexpected ILS credential submission.');
            }
            $rawUsername = $username;
            // Check for multiple ILS target selection
            $target = $postParams['target'] ?? '';
            if ($target) {
                $username = "$target.$username";
            }
            try {
                if ('email' === $this->getILSLoginMethod($target)) {
                    // Use raw (non-prefixed) username as email to display so that we don't accidentally reveal if a
                    // patron was found:
                    $authData = [
                        'email' => $rawUsername,
                        'authId' => null,
                    ];
                    // Since we're using the email login method, no password is required here.
                    if ($patron = $this->ils->patronLogin($username, '')) {
                        $data = compact('username', 'patron');
                        $authData['authId']
                            = $this->emailAuthenticator->sendAuthenticationCode($patron['email'], $data);
                        $this->auditEventService->addEvent(
                            AuditEventType::User,
                            AuditEventSubtype::SendCardAuthEmail,
                            $user,
                            data: $data
                        );
                    }
                    // Don't reveal the result
                    $this->userSession->setLibraryCardAuthenticationData($authData);
                    $this->setFollowupUrlToReferer($request);
                    return $this->redirectHelper->redirectToRoute($response, 'myresearch-verifyotp');
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
        } else {
            try {
                // If no credentials were provided, try the stored values:
                $patron = $this->ilsAuthenticator->storedCatalogLogin();
            } catch (ILSException $e) {
                $this->flashMessenger->addErrorMessage('ils_connection_failed');
            }
        }

        // If catalog login failed, send the user to the right page:
        if (!$patron && $forwardToCatalogLogin) {
            return $this->forwardHelper->forwardTo($request, $response, 'myresearch/cataloglogin');
        }

        // Send either null or patron array back to caller:
        return $patron ?? null;
    }

    /**
     * What login method does the ILS use (password, email, vufind).
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

    /**
     * Get settings required for displaying the catalog login form.
     *
     * @return array
     */
    public function getILSLoginSettings(): array
    {
        $targets = null;
        $defaultTarget = null;
        $loginMethod = null;
        $loginMethods = [];
        // Connect to the ILS and check if multiple target support is available:
        if ($this->ils->checkCapability('getLoginDrivers')) {
            $targets = $this->ils->getLoginDrivers();
            $defaultTarget = $this->ils->getDefaultLoginDriver();
            foreach ($targets as $t) {
                $loginMethods[$t] = $this->getILSLoginMethod($t);
            }
        } else {
            $loginMethod = $this->getILSLoginMethod();
        }
        return compact('targets', 'defaultTarget', 'loginMethod', 'loginMethods');
    }

    /**
     * Store a referer (if appropriate) to keep post-login redirect pointing
     * to an appropriate location. This is used when the user clicks the
     * log in link from an arbitrary page or when a password is mistyped;
     * separate logic is used for storing followup information when VuFind
     * forces the user to log in from another context.
     *
     * @param ServerRequestInterface $request         Request
     * @param bool                   $allowCurrentUrl Whether the current URL is valid for followup
     * @param array                  $extras          Extra data for the followup
     *
     * @return void
     */
    public function setFollowupUrlToReferer(
        ServerRequestInterface $request,
        bool $allowCurrentUrl = true,
        array $extras = []
    ): void {
        // lbreferer is the stored current url of the lightbox
        // which overrides the url from the server request when present
        $referer = $request->getQueryParams()['lbreferer'] ?? $request->getHeader('Referer')[0] ?? null;
        // Get the referer -- if it's empty, there's nothing to store! Also,
        // if the referer lives outside of VuFind, don't store it! We only
        // want internal post-login redirects.
        if (empty($referer) || !$this->urlHelper->isLocalUrl($referer)) {
            return;
        }
        // If the referer is the MyResearch/Home action, it probably means
        // that the user is repeatedly mistyping their password. We should
        // ignore this and instead rely on any previously stored referer.
        $refererNorm = $this->urlHelper->normalizeUrlForComparison($referer);
        $myResearchHomeUrl = $this->serverUrlHelper->getUrlForPath(
            $this->routeHelper->getUrlFromRoute('myresearch-home')
        );
        $mrhuNorm = $this->urlHelper->normalizeUrlForComparison($myResearchHomeUrl);
        if ($mrhuNorm === $refererNorm) {
            return;
        }

        // If the referer is the MyResearch/UserLogin action, it probably means
        // that the user is repeatedly mistyping their password. We should
        // ignore this and instead rely on any previously stored referer.
        $myUserLogin = $this->serverUrlHelper->getUrlForPath(
            $this->routeHelper->getUrlFromRoute('myresearch-userlogin')
        );
        $mulNorm = $this->urlHelper->normalizeUrlForComparison($myUserLogin);
        if (str_starts_with($refererNorm, $mulNorm)) {
            return;
        }

        // Check that the referer is not current URL if not allowed:
        if (!$allowCurrentUrl && (string)$request->getUri() === $referer) {
            return;
        }

        // Clear previously stored lightboxParent.
        $this->followupHelper->clear('lightboxParent');

        // If we got this far, we want to store the referer:
        $this->followupHelper->store($extras, $referer);
    }

    /**
     * Unset the followup to trigger default behaviors.
     *
     * @return void
     */
    public function clearFollowupUrl(): void
    {
        $this->followupHelper->clear('isReferrer');
        $this->followupHelper->clear('lightboxParent');
        $this->followupHelper->clear('url');
    }
}
