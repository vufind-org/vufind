<?php

/**
 * MyResearch "Create account" action.
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

namespace VuFind\Action\MyResearch;

use Laminas\Http\Response;
use Laminas\Psr7Bridge\Psr7ServerRequest;
use Laminas\Session\SessionManager;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\ActionHelper\FlashMessagesHelper;
use VuFind\ActionHelper\FormHelper;
use VuFind\ActionHelper\ForwardHelper;
use VuFind\ActionHelper\LoginHelper;
use VuFind\ActionHelper\RedirectHelper;
use VuFind\Auth\EmailAuthenticator;
use VuFind\Auth\Manager as AuthManager;
use VuFind\Auth\UserSessionPersistenceInterface;
use VuFind\Captcha\Service\CaptchaService;
use VuFind\Db\Service\AuditEventServiceInterface;
use VuFind\Db\Service\PluginManager as DbServicePluginManager;
use VuFind\Exception\Auth as AuthException;
use VuFind\Exception\AuthEmailNotVerified as AuthEmailNotVerifiedException;
use VuFind\Http\ServerUrlHelper;
use VuFind\ILS\Connection;
use VuFind\Mailer\Mailer;
use VuFind\ServiceManager\Factory\Autowire;
use VuFind\Session\Helper\FollowupHelper;

/**
 * MyResearch "Create account" action.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class AccountAction extends AbstractMyResearchAction
{
    /**
     * Constructor.
     *
     * @param AuthManager                     $authManager        Authentication manager
     * @param FollowupHelper                  $followupHelper     Followup helper
     * @param EmailAuthenticator              $emailAuthenticator Email authenticator
     * @param UserSessionPersistenceInterface $userSessionService User session database service
     * @param AuditEventServiceInterface      $auditEventService  Audit event service
     * @param ServerUrlHelper                 $serverUrlHelper    Server URL helper
     * @param Mailer                          $mailer             Mailer
     * @param SessionManager                  $sessionManager     Session manager
     * @param Connection                      $ilsConnection      ILS connection
     * @param array                           $config             VuFind configuration
     * @param CaptchaService                  $captcha            Captcha service
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
        SessionManager $sessionManager,
        Connection $ilsConnection,
        #[Autowire(config: 'config')]
        array $config,
        protected CaptchaService $captcha,
    ) {
        parent::__construct(
            $authManager,
            $followupHelper,
            $emailAuthenticator,
            $userSessionService,
            $auditEventService,
            $serverUrlHelper,
            $mailer,
            $sessionManager,
            $ilsConnection,
            $config
        );
    }

    /**
     * Initialize the action.
     *
     * @return void
     */
    protected function init(): void
    {
        // Default to false rather than null because we don't want a default setting to override the action's
        // accessibility and break the login process!
        $this->accessPermission = false;
    }

    /**
     * Create a user account.
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
        // Don't let the user create an account if already logged in or not supported by the authentication mechanism:
        $method = trim($this->getQueryParam('auth_method'));
        if (
            $this->authManager->getIdentity()
            || !$this->authManager->supportsCreation($method)
        ) {
            return $this->getHelper(RedirectHelper::class)->redirectToRoute($response, 'myresearch-home');
        }

        // If there's already a followup url, keep it; otherwise set one.
        if (!$this->hasFollowupUrl()) {
            $this->getHelper(LoginHelper::class)->setFollowupUrlToReferrer($request);
        }

        $templateParams = [
            'usernamePolicy' => $this->authManager->getUsernamePolicy($method),
            'passwordPolicy' => $this->authManager->getPasswordPolicy($method),
            'useCaptcha' => $this->captcha->active('newAccount'),
            // Pass request to view so we can repopulate user parameters in form:
            'request' => $request->getParsedBody(),
        ];
        // Process request, if necessary:
        if (
            $this->getHelper(FormHelper::class)->formWasSubmitted($request, useCaptcha: $templateParams['useCaptcha'])
        ) {
            try {
                $this->authManager->create(Psr7ServerRequest::toLaminas($request));
                return $this->getHelper(ForwardHelper::class)->forwardTo($request, $response, 'myresearch/home');
            } catch (AuthEmailNotVerifiedException $e) {
                $this->sendFirstVerificationEmail($e->getUser());
                return $this->getHelper(RedirectHelper::class)
                    ->redirectToRoute($response, 'myresearch-emailnotverified');
            } catch (AuthException $e) {
                $this->getHelper(FlashMessagesHelper::class)->addErrorMessage($e->getMessage());
            }
        } else {
            // If we are not processing a submission, we need to simply display
            // an empty form. In case ChoiceAuth is being used, we may need to
            // override the active authentication method based on request
            // parameters to ensure display of the appropriate template.
            $this->setUpAuthenticationFromRequest();
        }

        return $this->renderTemplate($request, $response, $templateParams);
    }
}
