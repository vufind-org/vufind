<?php

/**
 * Verify account recovery hash action.
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
use Laminas\Mvc\Controller\Plugin\Forward;
use Laminas\Session\SessionManager;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\ActionHelper\FlashMessagesHelper;
use VuFind\ActionHelper\ForwardHelper;
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

use function intval;

/**
 * Verify account recovery hash action.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class VerifyAction extends AbstractMyResearchAction
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
     * Verify a recovery hash and display the password reset form.
     *
     * Used for Alma and legacy password reset links.
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
        if ($hash = $this->getQueryParam('hash')) {
            $hashtime = $this->getHashAge($hash);
            // Check if hash is expired
            $hashLifetime = $this->authManager->getRecoveryHashLifeTime();
            if (time() - $hashtime > $hashLifetime) {
                $this->getHelper(FlashMessagesHelper::class)->addErrorMessage('recovery_expired_hash');
                return $this->getHelper(ForwardHelper::class)->forwardTo($request, $response, 'myresearch/login');
            }

            // If the hash is valid, forward user to create new password. Also treat email address as verified.
            if ($user = $this->userService->getUserByVerifyHash($hash)) {
                $user->setEmailVerified(new DateTime());
                $this->userService->persistEntity($user);
                $this->setUpAuthenticationFromRequest();
                $templateParams = [
                    'auth_method' => $this->authManager->getAuthMethod(),
                    'hash' => $hash,
                    'username' => $user->getUsername(),
                    'useCaptcha' => $this->captchaService->active('changePassword'),
                    'passwordPolicy' => $this->authManager->getPasswordPolicy(),
                ];

                $this->auditEventService->addEvent(
                    AuditEventType::User,
                    AuditEventSubtype::VerifyEmailHash,
                    $user,
                );

                return $this->renderTemplate($request, $response, $templateParams, 'myresearch/newpassword');
            }
        }
        $this->getHelper(FlashMessagesHelper::class)->addErrorMessage('recovery_invalid_hash');
        return $this->getHelper(ForwardHelper::class)->forwardTo($request, $response, 'myresearch/login');
    }

    /**
     * Helper function for verification hashes.
     *
     * @param string $hash User-unique hash string from request
     *
     * @return int age in seconds
     */
    protected function getHashAge(string $hash): int
    {
        return intval(substr($hash, -10));
    }
}
