<?php

/**
 * New password action.
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
use VuFind\ActionHelper\RedirectHelper;
use VuFind\Auth\EmailAuthenticator;
use VuFind\Auth\Manager as AuthManager;
use VuFind\Auth\UserSessionPersistenceInterface;
use VuFind\Captcha\Service\CaptchaService;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Service\AuditEventServiceInterface;
use VuFind\Db\Service\PluginManager as DbServicePluginManager;
use VuFind\Db\Service\UserServiceInterface;
use VuFind\Db\Type\AuditEventSubtype;
use VuFind\Db\Type\AuditEventType;
use VuFind\Exception\Auth as AuthException;
use VuFind\Http\ServerUrlHelper;
use VuFind\ILS\Connection;
use VuFind\Mailer\Mailer;
use VuFind\ServiceManager\Factory\Autowire;
use VuFind\Session\Helper\FollowupHelper;

/**
 * New password action.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class NewPasswordAction extends AbstractMyResearchAction
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
     * @param UserServiceInterface            $userService        User service
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
        #[Autowire(container: DbServicePluginManager::class)]
        protected UserServiceInterface $userService,
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
     * Set new password.
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
        // Have we submitted the form?
        $formHelper = $this->getHelper(FormHelper::class);
        if (!$formHelper->formWasSubmitted($request)) {
            return $this->getHelper(RedirectHelper::class)->redirectToRoute($response, 'home');
        }
        // Set up authentication so that we can retrieve the correct password policy:
        $this->setUpAuthenticationFromRequest();

        // Retrieve user by hash:
        $hash = $this->getPostParam('hash');
        $userFromHash = $hash
            ? $this->userService->getUserByVerifyHash($hash)
            : null;

        $templateParams = $request->getParsedBody();
        $templateParams['passwordPolicy'] = $this->authManager->getPasswordPolicy();
        $templateParams['useCaptcha'] = $this->captchaService->active('changePassword');

        // Check captcha:
        if (!$formHelper->formWasSubmitted($request, useCaptcha: $templateParams['useCaptcha'])) {
            $templateParams = $this->resetNewPasswordForm($userFromHash, $templateParams);
            return $this->renderTemplate($request, $response, $templateParams);
        }

        $flashMessagesHelper = $this->getHelper(FlashMessagesHelper::class);
        // Verify user:
        if (!$userFromHash) {
            $flashMessagesHelper->addErrorMessage('recovery_user_not_found');
            return $this->getHelper(RedirectHelper::class)->redirectToRoute($response, 'myresearch-recover');
        } elseif ($userFromHash->getUsername() !== $this->getPostParam('username')) {
            $flashMessagesHelper->addErrorMessage('authentication_error_invalid');
            $templateParams = $this->resetNewPasswordForm($userFromHash, $templateParams);
            return $this->renderTemplate($request, $response, $templateParams);
        }

        // Verify old password if we're logged in:
        if ($this->authManager->getIdentity()) {
            if ($oldPassword = $this->getPostParam('oldpwd')) {
                // Check old password:
                $oldPasswordPost = $request->getParsedBody();
                $oldPasswordPost['password'] = $oldPassword;
                $valid = $this->authManager
                    ->validateCredentials(Psr7ServerRequest::toLaminas($request->withParsedBody($oldPasswordPost)));
            } else {
                $valid = false;
            }
            if (!$valid) {
                $flashMessagesHelper->addErrorMessage('authentication_error_invalid');
                $templateParams['verifyold'] = true;
                return $this->renderTemplate($request, $response, $templateParams);
            }
        }
        // Update password:
        try {
            $user = $this->authManager->updatePassword(Psr7ServerRequest::toLaminas($request));
        } catch (AuthException $e) {
            $flashMessagesHelper->addErrorMessage($e->getMessage());
            return $this->renderTemplate($request, $response, $templateParams);
        }
        // Update hash to prevent reusing hash:
        $this->authManager->updateUserVerifyHash($user);
        $this->authManager->login(Psr7ServerRequest::toLaminas($request));
        // Return to account home:
        $flashMessagesHelper->addSuccessMessage('new_password_success');

        $this->auditEventService->addEvent(
            AuditEventType::User,
            AuditEventSubtype::PasswordChanged,
            $user,
        );

        return $this->getHelper(RedirectHelper::class)->redirectToRoute($response, 'myresearch-home');
    }

    /**
     * Reset the new password form and return the modified template params. When a user has already been loaded from an
     * existing hash, this resets the hash and updates the form so that the user can try again.
     *
     * @param ?UserEntityInterface $userFromHash   User loaded from database, or null if none.
     * @param array                $templateParams Template params
     *
     * @return array
     */
    protected function resetNewPasswordForm(?UserEntityInterface $userFromHash, array $templateParams)
    {
        if ($userFromHash) {
            $this->authManager->updateUserVerifyHash($userFromHash);
            $templateParams['username'] = $userFromHash->getUsername();
            $templateParams['hash'] = $userFromHash->getVerifyHash();
        }
        return $templateParams;
    }
}
