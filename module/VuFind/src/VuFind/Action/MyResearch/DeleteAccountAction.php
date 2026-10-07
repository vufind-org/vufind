<?php

/**
 * Delete user account action.
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
use VuFind\Account\UserAccountService;
use VuFind\ActionHelper\FormHelper;
use VuFind\ActionHelper\LoginHelper;
use VuFind\ActionHelper\RedirectHelper;
use VuFind\Auth\EmailAuthenticator;
use VuFind\Auth\Manager as AuthManager;
use VuFind\Auth\UserSessionPersistenceInterface;
use VuFind\Db\Service\AuditEventServiceInterface;
use VuFind\Db\Service\PluginManager as DbServicePluginManager;
use VuFind\Db\Type\AuditEventSubtype;
use VuFind\Db\Type\AuditEventType;
use VuFind\Http\ServerUrlHelper;
use VuFind\ILS\Connection;
use VuFind\Mailer\Mailer;
use VuFind\ServiceManager\Factory\Autowire;
use VuFind\Session\Helper\FollowupHelper;
use VuFind\Validator\CsrfInterface;

/**
 * Delete user account action.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class DeleteAccountAction extends AbstractMyResearchAction
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
     * @param CsrfInterface                   $csrf               CSRF validator
     * @param UserAccountService              $userAccountService User account service
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
        protected CsrfInterface $csrf,
        protected UserAccountService $userAccountService,
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
     * Delete a user's account.
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
        // Force login:
        if (!($user = $this->authManager->getUserObject())) {
            return $this->getHelper(LoginHelper::class)->forceLogin($request, $response);
        }

        if (!($this->config['Authentication']['account_deletion'] ?? false)) {
            throw new \VuFind\Exception\BadRequest();
        }

        $templateParams = ['accountDeleted' => false];
        $formHelper = $this->getHelper(FormHelper::class);
        if ($formHelper->formWasSubmitted($request)) {
            if (!$this->csrf->isValid($this->getPostParam('csrf'))) {
                throw new \VuFind\Exception\BadRequest('error_inconsistent_parameters');
            }
            // After successful token verification, clear list to shrink session:
            $this->csrf->trimTokenList(0);

            $this->auditEventService->addEvent(
                AuditEventType::User,
                AuditEventSubtype::Delete,
                $user,
                data: [
                    'user_id' => $user->getId(),
                ]
            );

            $this->userAccountService->purgeUserData(
                $user,
                $this->config['Authentication']['delete_comments_with_user'] ?? true,
                $this->config['Authentication']['delete_ratings_with_user'] ?? true
            );
            $templateParams['accountDeleted'] = true;
            $templateParams['redirectUrl'] = $this->authManager->getLogoutRedirectUrl(
                $this->serverUrlHelper->getUrlForPath($this->getRouteHelper()->getUrlFromRoute('home'))
            );
            $this->authManager->clearLoginState();
        } elseif ($formHelper->formWasSubmitted($request, 'reset')) {
            return $this->getHelper(RedirectHelper::class)->redirectToRoute($response, 'myresearch-profile');
        }

        return $this->renderTemplate($request, $response, $templateParams);
    }
}
