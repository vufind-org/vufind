<?php

/**
 * Library card "verify OTP" action.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2015-2026.
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

namespace VuFind\Action\LibraryCards;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\Action\AbstractTemplateRenderingAction;
use VuFind\ActionHelper\FlashMessagesHelper;
use VuFind\ActionHelper\FormHelper;
use VuFind\ActionHelper\LoginHelper;
use VuFind\ActionHelper\RedirectHelper;
use VuFind\Auth\EmailAuthenticator;
use VuFind\Auth\Manager as AuthManager;
use VuFind\Auth\UserSessionPersistenceInterface;
use VuFind\Db\Service\AuditEventServiceInterface;
use VuFind\Db\Service\PluginManager as DbServicePluginManager;
use VuFind\Db\Service\UserCardServiceInterface;
use VuFind\Db\Type\AuditEventSubtype;
use VuFind\Db\Type\AuditEventType;
use VuFind\ServiceManager\Factory\Autowire;
use VuFind\Validator\CsrfInterface;

/**
 * Library card "verify OTP" action.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class VerifyOtpAction extends AbstractTemplateRenderingAction
{
    /**
     * Constructor.
     *
     * @param AuthManager                     $authManager        Authentication manager
     * @param UserCardServiceInterface        $userCardService    User card database service
     * @param AuditEventServiceInterface      $auditEventService  Audit event service
     * @param EmailAuthenticator              $emailAuthenticator Email authenticator
     * @param UserSessionPersistenceInterface $userSession        User session
     * @param CsrfInterface                   $csrf               CSRF validator
     */
    public function __construct(
        protected AuthManager $authManager,
        #[Autowire(container: DbServicePluginManager::class)]
        protected UserCardServiceInterface $userCardService,
        #[Autowire(container: DbServicePluginManager::class)]
        protected AuditEventServiceInterface $auditEventService,
        protected EmailAuthenticator $emailAuthenticator,
        #[Autowire(container: DbServicePluginManager::class)]
        protected UserSessionPersistenceInterface $userSession,
        protected CsrfInterface $csrf,
    ) {
        parent::__construct();
    }

    /**
     * Verify new library card using a one-time password.
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

        if (!($authData = $this->userSession->getLibraryCardAuthenticationData())) {
            return $this->getHelper(RedirectHelper::class)->redirectToRoute($response, 'librarycards-home');
        }

        // Process form submission:
        if ($this->getHelper(FormHelper::class)) {
            if (!$this->csrf->isValid($this->getPostParam('csrf'))) {
                throw new \VuFind\Exception\BadRequest('error_inconsistent_parameters');
            } else {
                // After successful token verification, clear list to shrink session:
                $this->csrf->trimTokenList(0);
            }

            $password = $this->getPostParam('password', '');
            if (
                ($authId = $authData['authId'] ?? null)
                && ($cardData = $this->emailAuthenticator->verifyAuthenticationCode($authId, $password))
                && ($cardId = $cardData['cardID'] ?? null)
            ) {
                $this->userCardService->persistLibraryCardData(
                    $user,
                    'NEW' === $cardId ? null : $cardId,
                    $cardData['cardName'],
                    $cardData['cat_username'],
                    ' '
                );
                $this->auditEventService->addEvent(
                    AuditEventType::User,
                    AuditEventSubtype::ConnectCardByEmail,
                    $user,
                    data: $cardData
                );
                $this->userSession->setLibraryCardAuthenticationData(null);
                return $this->getHelper(RedirectHelper::class)->redirectToRoute($response, 'librarycards-home');
            }
            $this->getHelper(FlashMessagesHelper::class)->addErrorMessage('authentication_error_invalid');
        }

        return $this->renderTemplate($request, $response, compact('authData'));
    }
}
