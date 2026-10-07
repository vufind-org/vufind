<?php

/**
 * Favorite list edit action.
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

use Laminas\Session\SessionManager;
use Laminas\Stdlib\Parameters;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\ActionHelper\FlashMessagesHelper;
use VuFind\ActionHelper\FormHelper;
use VuFind\ActionHelper\ForwardHelper;
use VuFind\ActionHelper\LoginHelper;
use VuFind\ActionHelper\RedirectHelper;
use VuFind\ActionHelper\UserContentHelper;
use VuFind\Auth\EmailAuthenticator;
use VuFind\Auth\Manager as AuthManager;
use VuFind\Auth\UserSessionPersistenceInterface;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Entity\UserListEntityInterface;
use VuFind\Db\Service\AuditEventServiceInterface;
use VuFind\Db\Service\PluginManager as DbServicePluginManager;
use VuFind\Db\Service\UserListServiceInterface;
use VuFind\Exception\Forbidden as ForbiddenException;
use VuFind\Exception\ListPermission as ListPermissionException;
use VuFind\Exception\LoginRequired as LoginRequiredException;
use VuFind\Exception\MissingField as MissingFieldException;
use VuFind\Favorites\FavoritesService;
use VuFind\Http\ServerUrlHelper;
use VuFind\ILS\Connection;
use VuFind\Mailer\Mailer;
use VuFind\Record\Loader as RecordLoader;
use VuFind\Record\Router as RecordRouter;
use VuFind\ServiceManager\Factory\Autowire;
use VuFind\Session\Helper\FollowupHelper;
use VuFind\Tags\TagsService;

/**
 * Favorite list edit action.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class EditListAction extends AbstractMyResearchAction
{
    /**
     * Constructor.
     *
     * @param AuthManager                     $authManager        Authentication manager
     * @param FollowupHelper                  $followupHelper     Followup helper
     * @param EmailAuthenticator              $emailAuthenticator Email authenticator
     * @param UserSessionPersistenceInterface $userSessionService User session service
     * @param AuditEventServiceInterface      $auditEventService  Audit event service
     * @param ServerUrlHelper                 $serverUrlHelper    Server URL helper
     * @param Mailer                          $mailer             Mailer
     * @param SessionManager                  $sessionManager     Session manager
     * @param Connection                      $ilsConnection      ILS connection
     * @param array                           $config             VuFind configuration
     * @param UserListServiceInterface        $userListService    User list database service
     * @param FavoritesService                $favoritesService   Favorites service
     * @param TagsService                     $tagsService        Tags service
     * @param RecordLoader                    $recordLoader       Record loader
     * @param RecordRouter                    $recordRouter       Record router
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
        protected UserListServiceInterface $userListService,
        protected FavoritesService $favoritesService,
        protected TagsService $tagsService,
        protected RecordLoader $recordLoader,
        protected RecordRouter $recordRouter,
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
     * Edit a favorite list.
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
        // Fail if lists are disabled:
        if (!$this->getHelper(UserContentHelper::class)->listsEnabled()) {
            throw new ForbiddenException('Lists disabled');
        }

        // User must be logged in to edit list:
        if (!($user = $this->authManager->getUserObject())) {
            return $this->getHelper(LoginHelper::class)->forceLogin($request, $response);
        }

        // Is this a new list or an existing list?  Handle the special 'NEW' value
        // of the ID parameter:
        $id = $this->getRouteParam('id') ?? $this->getPostOrQueryParam('id', preferQuery: true);
        $newList = ($id === 'NEW');
        // If this is a new list, use the FavoritesService to pre-populate some values in a fresh object; if it's an
        // existing list, we can just fetch from the database.
        $list = $newList
            ? $this->favoritesService->createListForUser($user)
            : $this->userListService->getUserListById($id);

        // Make sure the user isn't fishing for other people's lists:
        if (!$newList && !$this->favoritesService->userCanEditList($user, $list)) {
            throw new ListPermissionException('Access denied.');
        }

        // Process form submission:
        if ($this->getHelper(FormHelper::class)->formWasSubmitted($request)) {
            if ($redirect = $this->processEditList($user, $list)) {
                return $redirect;
            }
        }

        $listTags = null;
        if ($this->getHelper(UserContentHelper::class)->listTagsEnabled() && !$newList) {
            $listTags = $this->favoritesService
                ->formatTagStringForEditing($this->tagsService->getListTags($list, $list->getUser()));
        }

        return $this->renderTemplate(
            $request,
            $response,
            [
                'list' => $list,
                'newList' => $newList,
                'listTags' => $listTags,
                'recordIds' => (array)($this->getPostOrQueryParam('ids', [], true)),
                'recordId' => $this->getPostOrQueryParam('recordId', preferQuery: true),
                'recordSource' => $this->getPostOrQueryParam('recordSource', DEFAULT_SEARCH_BACKEND, true),
            ]
        );
    }

    /**
     * Process the "edit list" submission.
     *
     * @param UserEntityInterface     $user Logged in user
     * @param UserListEntityInterface $list List being created/edited
     *
     * @return ?ResponseInterface Response object if redirect is needed, null if form needs to be redisplayed.
     */
    protected function processEditList(UserEntityInterface $user, UserListEntityInterface $list): ?ResponseInterface
    {
        // Process form within a try..catch so we can handle errors appropriately:
        try {
            $finalId = $this->favoritesService
                ->updateListFromRequest($list, $user, new Parameters($this->request->getParsedBody()));

            // If the user is in the process of saving a record, send them back to the save screen; otherwise, send them
            // back to the list they just edited.
            $recordId = $this->getPostOrQueryParam('recordId', preferQuery: true);
            $recordSource = $this->getPostOrQueryParam('recordSource', DEFAULT_SEARCH_BACKEND, true);
            if ($recordId) {
                $details = $this->recordRouter->getActionRouteDetails(
                    $recordSource . '|' . $recordId,
                    'Save'
                );
                return $this->getHelper(RedirectHelper::class)
                    ->redirectToRoute($this->response, $details['route'], $details['params']);
            }

            // Similarly, if the user is in the process of bulk-saving records, send them back to the appropriate place
            // in the cart.
            $bulkIds = (array)($this->getPostOrQueryParam('ids', []));
            if ($bulkIds) {
                // Add final id of the list to request post so Cart/Save action
                // can properly load the list
                $postParams = ['list' => $finalId] + $this->request->getParsedBody();
                return $this->getHelper(ForwardHelper::class)
                    ->forwardTo($this->request->withParsedBody($postParams), $this->response, 'cart/save');
            }

            return $this->getHelper(RedirectHelper::class)
                    ->redirectToRoute($this->response, 'userList', ['id' => $finalId]);
        } catch (ListPermissionException | MissingFieldException $e) {
            $this->getHelper(FlashMessagesHelper::class)->addErrorMessage($e->getMessage());
            return null;
        } catch (LoginRequiredException $e) {
            return $this->getHelper(LoginHelper::class)->forceLogin($this->request, $this->response);
        }
    }
}
