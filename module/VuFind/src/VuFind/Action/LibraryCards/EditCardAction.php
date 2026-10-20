<?php

/**
 * "Edit library card" action.
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
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Service\AuditEventServiceInterface;
use VuFind\Db\Service\PluginManager as DbServicePluginManager;
use VuFind\Db\Service\UserCardServiceInterface;
use VuFind\Db\Type\AuditEventSubtype;
use VuFind\Db\Type\AuditEventType;
use VuFind\Exception\ILS as ILSException;
use VuFind\ILS\Connection;
use VuFind\ServiceManager\Factory\Autowire;

/**
 * "Edit library card" action.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class EditCardAction extends AbstractTemplateRenderingAction
{
    /**
     * Constructor.
     *
     * @param AuthManager                     $authManager        Authentication manager
     * @param UserCardServiceInterface        $userCardService    User card database service
     * @param Connection                      $ilsConnection      ILS connection
     * @param AuditEventServiceInterface      $auditEventService  Audit event service
     * @param EmailAuthenticator              $emailAuthenticator Email authenticator
     * @param UserSessionPersistenceInterface $userSession        User session
     */
    public function __construct(
        protected AuthManager $authManager,
        #[Autowire(container: DbServicePluginManager::class)]
        protected UserCardServiceInterface $userCardService,
        protected Connection $ilsConnection,
        #[Autowire(container: DbServicePluginManager::class)]
        protected AuditEventServiceInterface $auditEventService,
        protected EmailAuthenticator $emailAuthenticator,
        #[Autowire(container: DbServicePluginManager::class)]
        protected UserSessionPersistenceInterface $userSession
    ) {
        parent::__construct();
    }

    /**
     * Edit a library card.
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

        // Process form submission:
        if ($this->getHelper(FormHelper::class)->formWasSubmitted($request)) {
            if ($redirect = $this->processEditLibraryCard($user)) {
                return $redirect;
            }
        }

        $id = $this->getRouteParam('id') ?? $this->getQueryParam('id');
        $card = $this->userCardService->getOrCreateLibraryCard($user, $id == 'NEW' ? null : $id);

        $target = null;
        $username = $card->getCatUsername();

        $loginSettings = $this->getHelper(LoginHelper::class)->getILSLoginSettings();
        // Split target and username if multiple login targets are available:
        if ($loginSettings['targets'] && strstr($username, '.')) {
            [$target, $username] = explode('.', $username, 2);
        }

        $cardName = $this->getPostParam('card_name', $card->getCardName());
        $username = $this->getPostParam('username', $username);
        $target = $this->getPostParam('target', $target);

        $templateParams = [
            'card' => $card,
            'cardName' => $cardName,
            'target' => $target ?: $loginSettings['defaultTarget'],
            'username' => $username,
            'targets' => $loginSettings['targets'],
            'defaultTarget' => $loginSettings['defaultTarget'],
            'loginMethod' => $loginSettings['loginMethod'],
            'loginMethods' => $loginSettings['loginMethods'],
        ];
        return $this->renderTemplate($request, $response, $templateParams);
    }

    /**
     * Process the "edit library card" submission.
     *
     * @param UserEntityInterface $user Logged in user
     *
     * @return ?ResponseInterface Response object if redirect is needed, or null if form needs to be redisplayed
     */
    protected function processEditLibraryCard(UserEntityInterface $user): ?ResponseInterface
    {
        $cardName = $this->getPostParam('card_name', '');
        $target = $this->getPostParam('target', '');
        $username = $this->getPostParam('username', '');
        $password = $this->getPostParam('password', '');
        $id = $this->getRouteParam('id') ?? $this->getQueryParam('id');

        $flashMessagesHelper = $this->getHelper(FlashMessagesHelper::class);
        if (!$username) {
            $flashMessagesHelper->addErrorMessage('authentication_error_blank');
            return null;
        }

        $rawUsername = $username;
        if ($target) {
            $username = "$target.$username";
        }

        // Check the credentials if the username is changed or a new password is entered:
        $card = $this->userCardService->getOrCreateLibraryCard($user, $id == 'NEW' ? null : $id);
        if ($card->getCatUsername() !== $username || trim($password)) {
            // Connect to the ILS and check that the credentials are correct:
            $loginMethod = $this->getHelper(LoginHelper::class)->getILSLoginMethod($target);
            if ('password' === $loginMethod && !$this->authManager->allowsUserIlsLogin()) {
                throw new \Exception('Illegal configuration: password-based library cards and disabled user login');
            }
            try {
                $patron = $this->ilsConnection->patronLogin($username, $password);
            } catch (ILSException $e) {
                $flashMessagesHelper->addErrorMessage('ils_connection_failed');
                return null;
            }
            if ($patron) {
                $this->auditEventService->addEvent(
                    AuditEventType::User,
                    AuditEventSubtype::EditCard,
                    $user,
                    data: [
                        'username' => $username,
                        'card_id' => $id,
                    ]
                );
            } else {
                if ('password' === $loginMethod) {
                    $flashMessagesHelper->addErrorMessage('authentication_error_invalid');
                }
                $this->auditEventService->addEvent(
                    AuditEventType::User,
                    AuditEventSubtype::ILSLoginFailure,
                    $user,
                    data: [
                        'username' => $username,
                        'card_id' => $id,
                    ]
                );
                return null;
            }
            if ('email' === $loginMethod) {
                // Use raw (non-prefixed) username as email to display so that we don't accidentally reveal if a patron
                // was found:
                $authData = [
                    'email' => $rawUsername,
                    'authId' => null,
                ];
                if ($patron) {
                    $cardData = [
                        'cat_username' => $patron['cat_username'],
                        'email' => $patron['email'],
                        'cardID' => $id,
                        'cardName' => $cardName,
                    ];
                    $authData['authId']
                        = $this->emailAuthenticator->sendAuthenticationCode($cardData['email'], $cardData);
                    $this->auditEventService->addEvent(
                        AuditEventType::User,
                        AuditEventSubtype::SendCardAuthEmail,
                        $user,
                        data: $cardData
                    );
                }
                // Don't reveal the result
                $this->userSession->setLibraryCardAuthenticationData($authData);
                return $this->getHelper(RedirectHelper::class)
                    ->redirectToRoute($this->response, 'librarycards-verifyotp');
            }
        }

        try {
            $this->userCardService->persistLibraryCardData(
                $user,
                $id == 'NEW' ? null : $id,
                $cardName,
                $username,
                $password
            );
        } catch (\VuFind\Exception\LibraryCard $e) {
            $flashMessagesHelper->addErrorMessage($e->getMessage());
            return null;
        }

        return $this->getHelper(RedirectHelper::class)->redirectToRoute($this->response, 'librarycards-home');
    }
}
