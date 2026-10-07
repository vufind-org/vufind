<?php

/**
 * Recover account action.
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
use Laminas\Session\SessionManager;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\ActionHelper\FlashMessagesHelper;
use VuFind\ActionHelper\FormHelper;
use VuFind\ActionHelper\RedirectHelper;
use VuFind\Auth\EmailAuthenticator;
use VuFind\Auth\Manager as AuthManager;
use VuFind\Auth\UserSessionPersistenceInterface;
use VuFind\Captcha\Service\CaptchaService;
use VuFind\Db\Service\AuditEventServiceInterface;
use VuFind\Db\Service\PluginManager as DbServicePluginManager;
use VuFind\Db\Type\AuditEventSubtype;
use VuFind\Db\Type\AuditEventType;
use VuFind\Exception\Auth as AuthException;
use VuFind\Exception\ILS as ILSException;
use VuFind\Exception\Mail as MailException;
use VuFind\Http\ServerUrlHelper;
use VuFind\ILS\Connection;
use VuFind\Mailer\Mailer;
use VuFind\ServiceManager\Factory\Autowire;
use VuFind\Session\Helper\FollowupHelper;
use VuFind\Validator\CsrfInterface;
use VuFind\View\Helper\Root\Auth;

/**
 * Recover account action.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class RecoverAction extends AbstractMyResearchAction
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
     * @param CaptchaService                  $captchaService     Captcha service
     * @param CsrfInterface                   $csrf               CSRF validator
     * @param Auth                            $authViewHelper     Auth view helper
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
        protected CaptchaService $captchaService,
        protected CsrfInterface $csrf,
        #[Autowire(container: 'ViewHelperManager')]
        protected Auth $authViewHelper,
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
     * Recover an account.
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
        // Make sure we're configured to do this
        $this->setUpAuthenticationFromRequest();
        $target = $this->getPostOrQueryParam('target', '', true);
        if (!$this->authManager->supportsRecovery(target: $target)) {
            $this->getHelper(FlashMessagesHelper::class)->addErrorMessage('recovery_disabled');
            return $this->getHelper(RedirectHelper::class)->redirectToRoute($response, 'myresearch-home');
        }
        // Already logged in?
        if ($this->authManager->getIdentity()) {
            return $this->getHelper(RedirectHelper::class)->redirectToRoute($response, 'myresearch-home');
        }
        $templateParams = [
            'target' => $target,
            'useCaptcha' => $this->captchaService->active('passwordRecovery'),
        ];
        // If we have a submitted form
        if (
            $this->getHelper(FormHelper::class)->formWasSubmitted($request, useCaptcha: $templateParams['useCaptcha'])
        ) {
            if (!$this->csrf->isValid($this->getPostParam('csrf'))) {
                throw new \VuFind\Exception\BadRequest('error_inconsistent_parameters');
            }
            // After successful token verification, clear list to shrink session:
            $this->csrf->trimTokenList(0);

            $flashMessagesHelper = $this->getHelper(FlashMessagesHelper::class);
            try {
                $authData = [
                    'authId' => null,
                ];
                if ($recoveryData = $this->authManager->getPasswordRecoveryData($request->getParsedBody())) {
                    if ($authData['authId'] = $this->sendRecoveryEmail($recoveryData)) {
                        $this->userSessionService->setAccountRecoveryData($authData);
                        $flashMessagesHelper->addInfoMessage('recovery_email_sent');
                        return $this->getHelper(RedirectHelper::class)
                            ->redirectToRoute($response, 'myresearch-verifyrecoveryotp');
                    }
                } else {
                    if (!empty($this->config['Authentication']['recover_be_honest'])) {
                        $flashMessagesHelper->addErrorMessage('recovery_user_not_found');
                    } else {
                        $flashMessagesHelper->addInfoMessage('recovery_email_sent');
                        $this->userSessionService->setAccountRecoveryData($authData);
                        return $this->getHelper(RedirectHelper::class)
                            ->redirectToRoute($response, 'myresearch-verifyrecoveryotp');
                    }
                }
            } catch (AuthException $e) {
                $flashMessagesHelper->addErrorMessage($e->getMessage());
            } catch (ILSException $e) {
                $flashMessagesHelper->addErrorMessage('ils_connection_failed');
            }
        }

        return $this->renderTemplate($request, $response, $templateParams);
    }

    /**
     * Send a recovery email.
     *
     * @param array $recoveryData Recovery information required by the authentication to reset the password
     *
     * @return ?int Authentication code id
     */
    protected function sendRecoveryEmail(array $recoveryData): ?int
    {
        if (empty($recoveryData['email'])) {
            throw new AuthException('no_email_address');
        }
        $target = $this->getPostParam('target');
        try {
            $authId = $this->emailAuthenticator->sendAuthenticationCode(
                $recoveryData['email'],
                $recoveryData,
                'recovery_email_subject',
                $this->authViewHelper->getPasswordRecoveryCodeEmailTemplate(),
                compact('target'),
            );

            $this->auditEventService->addEvent(
                AuditEventType::User,
                AuditEventSubtype::SendEmailRecoveryCode,
                $this->authManager->getUserObject(),
                data: ['email' => $recoveryData['email']]
            );

            return $authId;
        } catch (MailException $e) {
            $this->getHelper(FlashMessagesHelper::class)->addErrorMessage($e->getDisplayMessage());
            return null;
        }
    }
}
