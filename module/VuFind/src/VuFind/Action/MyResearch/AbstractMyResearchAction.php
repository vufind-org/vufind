<?php

/**
 * Abstract base class for MyResearch actions.
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

use Laminas\Session\Container;
use Laminas\Session\SessionManager;
use VuFind\Action\AbstractTemplateRenderingAction;
use VuFind\ActionHelper\FlashMessagesHelper;
use VuFind\Auth\EmailAuthenticator;
use VuFind\Auth\Manager as AuthManager;
use VuFind\Auth\UserSessionPersistenceInterface;
use VuFind\Config\Feature\EmailSettingsTrait;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Service\AuditEventServiceInterface;
use VuFind\Db\Service\PluginManager as DbServicePluginManager;
use VuFind\Db\Type\AuditEventSubtype;
use VuFind\Db\Type\AuditEventType;
use VuFind\Exception\Auth as AuthException;
use VuFind\Exception\Mail as MailException;
use VuFind\Http\ServerUrlHelper;
use VuFind\I18n\Translator\TranslatorAwareInterface;
use VuFind\I18n\Translator\TranslatorAwareTrait;
use VuFind\ILS\Connection;
use VuFind\Mailer\Mailer;
use VuFind\ServiceManager\Factory\Autowire;
use VuFind\Session\Helper\FollowupHelper;

/**
 * Abstract base class for MyResearch actions.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
abstract class AbstractMyResearchAction extends AbstractTemplateRenderingAction implements TranslatorAwareInterface
{
    use EmailSettingsTrait;
    use TranslatorAwareTrait;

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
     */
    public function __construct(
        protected AuthManager $authManager,
        protected FollowupHelper $followupHelper,
        protected EmailAuthenticator $emailAuthenticator,
        #[Autowire(container: DbServicePluginManager::class)]
        protected UserSessionPersistenceInterface $userSessionService,
        #[Autowire(container: DbServicePluginManager::class)]
        protected AuditEventServiceInterface $auditEventService,
        protected ServerUrlHelper $serverUrlHelper,
        protected Mailer $mailer,
        protected SessionManager $sessionManager,
        protected Connection $ilsConnection,
        #[Autowire(config: 'config')]
        protected array $config,
    ) {
        parent::__construct();
    }

    /**
     * Send a verify email message for the first time (only if the user does not already have a hash).
     *
     * @param UserEntityInterface $user User object we're recovering
     *
     * @return void (sends email or adds error message)
     */
    protected function sendFirstVerificationEmail(UserEntityInterface $user): void
    {
        if (!$user->getVerifyHash()) {
            $this->sendVerificationEmail($user);
        }
    }

    /**
     * Send a verify email message.
     *
     * @param UserEntityInterface $user   User needing email verification
     * @param bool                $change Is the user changing their email (true) or setting up a new account (false).
     *
     * @return void (sends email or adds error message)
     */
    protected function sendVerificationEmail(UserEntityInterface $user, bool $change = false): void
    {
        // If we can't find a user
        $flashMessagesHelper = $this->getHelper(FlashMessagesHelper::class);
        if (null === $user) {
            $flashMessagesHelper->addErrorMessage('verification_user_not_found');
            return;
        }

        // Attempt to send the email
        try {
            // If the user is setting up a new account, use the main email
            // address; if they have a pending address change, use that.
            $to = ($pending = $user->getPendingEmail()) ? $pending : $user->getEmail();
            $authId = $this->emailAuthenticator->sendAuthenticationCode(
                $to,
                [
                    'userId' => $user->getId(),
                    'email' => $to,
                    'change' => $change,
                ],
                'verification_email_subject',
                'Email/verify-email.phtml',
                [
                    'library' => $this->config['Site']['title'] ?? '',
                ]
            );
            $authData = [
                'authId' => $authId,
                'email' => $to,
            ];
            $this->userSessionService->setEmailVerificationData($authData);
            $flashMessage = $change
                ? 'verification_email_change_sent'
                : 'verification_email_sent';
            $flashMessagesHelper->addInfoMessage($flashMessage);
            // If this is an email change, send a notification to the old
            // email address as well.
            if ($change) {
                $this->sendChangeNotificationEmail($user, $to);
            }

            $this->auditEventService->addEvent(
                AuditEventType::User,
                AuditEventSubtype::SendAddressVerificationEmail,
                $user,
                data: [
                    'email' => $user->getEmail(),
                    'pending_email' => $user->getPendingEmail(),
                    'change' => $change,
                ]
            );
        } catch (MailException $e) {
            $flashMessagesHelper->addErrorMessage($e->getDisplayMessage());
        } catch (AuthException $e) {
            if ($e->getMessage() === 'authentication_error_in_progress') {
                // A verification message has already been sent, so just add a message about resending it:
                $flashMessagesHelper->addErrorMessage('verification_too_soon');
            } else {
                throw $e;
            }
        }
    }

    /**
     * When a request to change a user's email address has been received, we should
     * send a notification to the old email address for the user's information.
     *
     * @param UserEntityInterface $user     User whose email address is being changed
     * @param string              $newEmail New email address
     *
     * @return void (sends email or adds error message)
     */
    protected function sendChangeNotificationEmail($user, $newEmail)
    {
        // Don't send the notification if the existing email is not valid:
        $validator = new \Laminas\Validator\EmailAddress();
        if (!$validator->isValid($user->getEmail())) {
            return;
        }

        // Custom template for emails (text-only)
        $message = $this->getTemplateRenderer()->renderTemplateAsString(
            template: 'Email/notify-email-change.phtml',
            params: [
                'library' => $this->config['Site']['title'] ?? '',
                'url' => $this->serverUrlHelper->getUrlForPath($this->routeHelper->getUrlFromRoute('home')),
                'email' => $this->config['Site']['email'] ?? '',
                'newEmail' => $newEmail,
            ]
        );
        // If the user is setting up a new account, use the main email
        // address; if they have a pending address change, use that.
        $this->mailer->send(
            $user->getEmail(),
            $this->getEmailSenderAddress($this->config),
            $this->translate('change_notification_email_subject'),
            $message
        );
    }

    /**
     * Return a session container for use in user email verification.
     *
     * @return Container
     */
    protected function getUserVerificationContainer(): Container
    {
        return new \Laminas\Session\Container('user_verification', $this->sessionManager);
    }

    /**
     * Checks if a followup url is set.
     *
     * @return bool
     */
    protected function hasFollowupUrl(): bool
    {
        return null !== $this->followupHelper->retrieve('url');
    }

    /**
     * Configure the authentication manager to use a user-specified method.
     *
     * @return void
     */
    protected function setUpAuthenticationFromRequest(): void
    {
        if ($method = trim($this->getPostOrQueryParam('auth_method', preferQuery: true))) {
            $this->authManager->setAuthMethod($method);
        }
    }

    /**
     * Add a message about any pending email change to the flash messenger.
     *
     * @param UserEntityInterface $user User
     *
     * @return void
     */
    protected function addPendingEmailChangeMessage(UserEntityInterface $user): void
    {
        if ($pending = $user->getPendingEmail()) {
            $url = $this->getRouteHelper()->getUrlFromRoute(
                'myresearch-emailnotverified',
                queryParams: ['reverify' => 'true']
            );
            $pendingEmailEsc = htmlspecialchars($pending, ENT_COMPAT, 'UTF-8');
            $this->getHelper(FlashMessagesHelper::class)->addInfoMessage(
                [
                    'html' => true,
                    'msg' => 'email_change_pending_html',
                    'tokens' => [
                        '%%pending%%' => $pendingEmailEsc,
                        '%%url%%' => $url,
                    ],
                ]
            );
        }
    }

    /**
     * Return a session container for password recovery data.
     *
     * @return Container
     */
    protected function getPasswordRecoveryDataContainer(): Container
    {
        return new \Laminas\Session\Container('PasswordRecovery', $this->sessionManager);
    }
}
