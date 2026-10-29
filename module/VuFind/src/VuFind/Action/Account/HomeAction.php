<?php

/**
 * Account home action.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2023-2026.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Action\Account;

use Laminas\Psr7Bridge\Psr7ServerRequest;
use Laminas\Session\Container;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\ActionHelper\ContextHelper;
use VuFind\ActionHelper\FlashMessagesHelper;
use VuFind\ActionHelper\ForwardHelper;
use VuFind\ActionHelper\LoginHelper;
use VuFind\ActionHelper\RedirectHelper;
use VuFind\ActionHelper\ResponseHelper;
use VuFind\ActionHelper\UserContentHelper;
use VuFind\Auth\EmailAuthenticator;
use VuFind\Auth\Manager as AuthManager;
use VuFind\Auth\UserSessionPersistenceInterface;
use VuFind\Db\Service\AuditEventServiceInterface;
use VuFind\Db\Service\PluginManager as DbServicePluginManager;
use VuFind\Exception\Auth as AuthException;
use VuFind\Exception\AuthEmailNotVerified as AuthEmailNotVerifiedException;
use VuFind\Exception\AuthInProgress as AuthInProgressException;
use VuFind\Http\ServerUrlHelper;
use VuFind\ILS\Connection;
use VuFind\Mailer\Mailer;
use VuFind\ServiceManager\Factory\Autowire;
use VuFind\Session\Helper\FollowupHelper;
use VuFind\Session\SessionContainerRegistry;

/**
 * Account home action.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class HomeAction extends AbstractAccountAction
{
    /**
     * Constructor.
     *
     * @param AuthManager                     $authManager               Authentication manager
     * @param FollowupHelper                  $followupHelper            Followup helper
     * @param EmailAuthenticator              $emailAuthenticator        Email authenticator
     * @param UserSessionPersistenceInterface $userSessionService        User session database service
     * @param AuditEventServiceInterface      $auditEventService         Audit event service
     * @param ServerUrlHelper                 $serverUrlHelper           Server URL helper
     * @param Mailer                          $mailer                    Mailer
     * @param Connection                      $ilsConnection             ILS connection
     * @param array                           $config                    VuFind configuration
     * @param Container                       $userVerificationContainer Session container for user verification data
     */
    public function __construct(
        AuthManager $authManager,
        FollowupHelper $followupHelper,
        EmailAuthenticator $emailAuthenticator,
        #[Autowire(container: DbServicePluginManager::class)]
        UserSessionPersistenceInterface $userSessionService,
        #[Autowire(container: DbServicePluginManager::class)]
        AuditEventServiceInterface $auditEventService,
        ServerUrlHelper $serverUrlHelper,
        Mailer $mailer,
        Connection $ilsConnection,
        #[Autowire(config: 'config')]
        array $config,
        #[Autowire(container: SessionContainerRegistry::class, service: 'user_verification')]
        protected Container $userVerificationContainer,
    ) {
        parent::__construct(
            $authManager,
            $followupHelper,
            $emailAuthenticator,
            $userSessionService,
            $auditEventService,
            $serverUrlHelper,
            $mailer,
            $ilsConnection,
            $config
        );
    }

    /**
     * Display default page for a logged-in user.
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
        // Process login request, if necessary (either because a form has been submitted or because we're using an
        // external login provider):
        if (
            $this->getPostParam('processLogin')
            || $this->authManager->hasSessionInitiator()
            || $this->getPostOrQueryParam('auth_method')
        ) {
            $loginHelper = $this->getHelper(LoginHelper::class);
            try {
                if (!$this->authManager->getIdentity()) {
                    if ($this->authManager->login(Psr7ServerRequest::toLaminas($request))) {
                        // Return early to avoid unnecessary processing if we are being called from login lightbox and
                        // don't have a followup action or followup is set to referrer.
                        if (
                            $this->getPostParam('processLogin')
                            && $this->getHelper(ContextHelper::class)->inLightbox($request)
                            && (null === $this->followupHelper->retrieve('url')
                            || $this->followupHelper->retrieve('isReferrer') === true)
                        ) {
                            $loginHelper->clearFollowupUrl();
                            return $this->getHelper(ResponseHelper::class)->getRefreshResponse($response);
                        }
                    }
                }
            } catch (AuthException $e) {
                $this->processAuthenticationException($e);
            }
        }

        // Pre-authenticated? Try to complete authentication:
        $forwardHelper = $this->getHelper(ForwardHelper::class);
        if ($this->authManager->getPreAuthenticationData()) {
            return $forwardHelper->forwardTo($request, $response, 'account/login');
        }

        // Not logged in?  Force user to log in:
        $loginHelper = $this->getHelper(LoginHelper::class);
        if (!$this->authManager->getIdentity()) {
            if (
                $this->followupHelper->retrieve('lightboxParent')
                && $url = $loginHelper->getAndClearFollowupUrl($request, true)
            ) {
                return $this->getHelper(RedirectHelper::class)->redirectToUrl($response, $url);
            }

            // Allow bypassing of post-login redirect
            if ($this->getQueryParam('redirect', '1')) {
                $loginHelper->setFollowupUrlToReferrer($request);
            }
            return $forwardHelper->forwardTo($request, $response, 'account/login');
        }
        // Logged in?  Forward user to followup action or default action (if no followup provided):
        if ($url = $loginHelper->getAndClearFollowupUrl($request, true)) {
            return $this->getHelper(RedirectHelper::class)->redirectToUrl($response, $url);
        }

        $page = $this->mapAccountPage($this->config['Site']['defaultAccountPage'] ?? 'lists/allitems');

        // Default to search history if favorites are disabled:
        if (str_starts_with($page, 'lists/') && !$this->getHelper(UserContentHelper::class)->listsEnabled()) {
            return $forwardHelper->forwardTo($request, $response, 'search/history');
        }
        return $forwardHelper->forwardTo($request, $response, $page);
    }

    /**
     * Map Account pages to correct actions.
     *
     * @param string $page Page
     *
     * @return string
     */
    protected function mapAccountPage(string $page): string
    {
        // Note: This is not an exhaustive list. It contains only pages that could be used in the defaultAccountPage
        // setting.
        return match ($page) {
            'CheckedOut' => 'checkouts/list',
            'Favorites' => 'lists/allitems',
            default => strtolower($page),
        };
    }

    /**
     * Process an authentication error.
     *
     * @param AuthException $e Exception to process.
     *
     * @return void
     */
    protected function processAuthenticationException(AuthException $e)
    {
        $flashMessagesHelper = $this->getHelper(FlashMessagesHelper::class);
        $msg = $e->getMessage();
        if ($e instanceof AuthInProgressException) {
            $flashMessagesHelper->addSuccessMessage($msg);
            return;
        }
        if ($e instanceof AuthEmailNotVerifiedException) {
            $this->sendFirstVerificationEmail($e->getUser());
            if ($msg == 'authentication_error_email_not_verified_html') {
                $this->userVerificationContainer->user = $e->getUser()->getUsername();
                $url = $this->routeHelper
                    ->getUrlFromRoute('account-emailnotverified', queryParams: ['reverify' => 'true']);
                $msg = [
                    'html' => true,
                    'msg' => $msg,
                    'tokens' => ['%%url%%' => $url],
                ];
            }
            return $this->getHelper(RedirectHelper::class)->redirectToRoute($this->response, 'account-verifyemail');
        }
        // If a Shibboleth-style login has failed and the user just logged out, we need to override the error message
        // with a more relevant one:
        if (
            $msg == 'authentication_error_admin'
            && $this->authManager->userHasLoggedOut()
            && $this->authManager->hasSessionInitiator()
        ) {
            $msg = 'authentication_error_loggedout';
        }
        $flashMessagesHelper->addErrorMessage($msg);
    }
}
