<?php

/**
 * "Delete library card" action.
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
use VuFind\ActionHelper\ForwardHelper;
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
use VuFind\ILS\Connection;
use VuFind\ServiceManager\Factory\Autowire;

/**
 * "Delete library card" action.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class DeleteCardAction extends AbstractTemplateRenderingAction
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

        // Get requested library card ID:
        if (!($cardID = $this->getPostOrQueryParam('cardID'))) {
            return $this->getHelper(RedirectHelper::class)->redirectToRoute($response, 'librarycards-home');
        }

        // Have we confirmed this?
        if ($this->getPostOrQueryParam('confirm')) {
            $this->userCardService->deleteLibraryCard($user, $cardID);

            $this->auditEventService->addEvent(
                AuditEventType::User,
                AuditEventSubtype::DeleteCard,
                $user,
                data: [
                    'card_id' => $cardID,
                ]
            );

            // Success Message
            $this->getHelper(FlashMessagesHelper::class)->addSuccessMessage('Library Card Deleted');
            return $this->getHelper(RedirectHelper::class)->redirectToRoute($response, 'librarycards-home');
        }

        // If we got this far, we must display a confirmation message:
        return $this->getHelper(ForwardHelper::class)->forwardToConfirm(
            $request,
            $response,
            'confirm_delete_library_card_brief',
            $this->routeHelper->getUrlFromRoute('librarycards-deletecard'),
            $this->routeHelper->getUrlFromRoute('librarycards-home'),
            'confirm_delete_library_card_text',
            compact('cardID')
        );
    }
}
