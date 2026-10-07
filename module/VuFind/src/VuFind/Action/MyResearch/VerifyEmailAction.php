<?php

/**
 * Verify email action.
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

use DateTime;
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
use VuFind\Db\Service\UserServiceInterface;
use VuFind\Db\Type\AuditEventSubtype;
use VuFind\Db\Type\AuditEventType;
use VuFind\Http\ServerUrlHelper;
use VuFind\ILS\Connection;
use VuFind\Mailer\Mailer;
use VuFind\ServiceManager\Factory\Autowire;
use VuFind\Session\Helper\FollowupHelper;

/**
 * Verify email action.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class VerifyEmailAction extends AbstractMyResearchAction
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
     * @param UserServiceInterface            $userService        User database service
     * @param CaptchaService                  $captchaService     Captcha service
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
        #[Autowire(container: DbServicePluginManager::class)]
        protected UserServiceInterface $userService,
        protected CaptchaService $captchaService,
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
     * Verify user's email address.
     *
     * @param ServerRequestInterface $request  Server request
     * @param ResponseInterface      $response Response
     *
     * @return ResponseInterface
     *
     * @see VerifyRecoveryOtpAction
     */
    public function action(
        ServerRequestInterface $request,
        ResponseInterface $response,
    ): ResponseInterface {
        $flashMessagesHelper = $this->getHelper(FlashMessagesHelper::class);
        $redirectHelper = $this->getHelper(RedirectHelper::class);
        if (!($authData = $this->userSessionService->getEmailVerificationData())) {
            $flashMessagesHelper->addErrorMessage('recovery_invalid_hash');
            return $redirectHelper->redirectToRoute($response, 'myresearch-home');
        }

        // If we have a submitted form
        if ($this->getHelper(FormHelper::class)->formWasSubmitted($request)) {
            $verificationCode = $this->getPostParam('verification_code', '');
            if (
                ($authId = $authData['authId'] ?? null)
                && ($verificationData = $this->emailAuthenticator->verifyAuthenticationCode($authId, $verificationCode))
                && ($verificationData['email'] === $authData['email'] ?? null)
                && ($userId = $verificationData['userId'] ?? null)
            ) {
                // Apply pending email address change, if applicable:
                if ($user = $this->userService->getUserById($userId)) {
                    if ($pending = $user->getPendingEmail()) {
                        $this->userService->updateUserEmail($user, $pending, true);
                        $user->setPendingEmail('');
                    }
                    $user->setEmailVerified(new DateTime());
                    $this->userService->persistEntity($user);

                    $flashMessagesHelper->addInfoMessage('verification_done');

                    $this->auditEventService->addEvent(
                        AuditEventType::User,
                        AuditEventSubtype::VerifyEmail,
                        $user,
                    );

                    return $verificationData['change'] ?? false
                        ? $redirectHelper->redirectToRoute($response, 'myresearch-profile')
                        : $redirectHelper->getNonRedirectingMyResearchHomeRedirect($response);
                }
                throw new \Exception('An error has occurred');
            } else {
                $flashMessagesHelper->addErrorMessage('authentication_error_invalid');
            }
        }

        return $this->renderTemplate($request, $response, compact('authData'));
    }
}
