<?php

/**
 * Favorite list action.
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

use Exception;
use Laminas\Session\SessionManager;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\ActionHelper\FlashMessagesHelper;
use VuFind\ActionHelper\ForwardHelper;
use VuFind\ActionHelper\LoginHelper;
use VuFind\ActionHelper\UserContentHelper;
use VuFind\Auth\EmailAuthenticator;
use VuFind\Auth\Manager as AuthManager;
use VuFind\Auth\UserSessionPersistenceInterface;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Service\AuditEventServiceInterface;
use VuFind\Db\Service\PluginManager as DbServicePluginManager;
use VuFind\Db\Service\UserListServiceInterface;
use VuFind\Exception\Forbidden as ForbiddenException;
use VuFind\Exception\ListPermission as ListPermissionException;
use VuFind\Favorites\FavoritesService;
use VuFind\Http\ServerUrlHelper;
use VuFind\ILS\Connection;
use VuFind\Mailer\Mailer;
use VuFind\Recommend\PluginManager as RecommendPluginManager;
use VuFind\Search\RecommendListener;
use VuFind\Search\SearchRunner;
use VuFind\ServiceManager\Factory\Autowire;
use VuFind\Session\Helper\FollowupHelper;
use VuFind\Tags\TagsService;
use VuFind\View\Helper\Root\Context;

/**
 * Favorite list action.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class MyListAction extends AbstractMyResearchAction
{
    /**
     * Constructor.
     *
     * @param AuthManager                     $authManager            Authentication manager
     * @param FollowupHelper                  $followupHelper         Followup helper
     * @param EmailAuthenticator              $emailAuthenticator     Email authenticator
     * @param UserSessionPersistenceInterface $userSessionService     User session service
     * @param AuditEventServiceInterface      $auditEventService      Audit event service
     * @param ServerUrlHelper                 $serverUrlHelper        Server URL helper
     * @param Mailer                          $mailer                 Mailer
     * @param SessionManager                  $sessionManager         Session manager
     * @param Connection                      $ilsConnection          ILS connection
     * @param array                           $config                 VuFind configuration
     * @param UserListServiceInterface        $userListService        User list database service
     * @param FavoritesService                $favoritesService       Favorites service
     * @param TagsService                     $tagsService            Tags service
     * @param SearchRunner                    $searchRunner           Search runner
     * @param RecommendPluginManager          $recommendPluginManager Recommendation plugin manager
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
        protected SearchRunner $searchRunner,
        protected RecommendPluginManager $recommendPluginManager,
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
     * Display a favorite list.
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

        // Check for "delete item" request; parameter may be in GET or POST depending on calling context.
        $deleteId = $this->getPostOrQueryParam('delete');
        if ($deleteId) {
            if (!($user = $this->authManager->getUserObject())) {
                return $this->getHelper(LoginHelper::class)->forceLogin($request, $response);
            }

            $deleteSource = $this->getPostOrQueryParam('source', DEFAULT_SEARCH_BACKEND);
            // Normally list ID is found in the route match, but in lightbox context it may sometimes be a GET
            // parameter. We must cover both cases.
            $listID = $this->getRouteParam('id') ?? $this->getQueryParam('id');
            $listID = $listID ? (int)$listID : null;
            // If the user already confirmed the operation, perform the delete now; otherwise prompt for confirmation:
            if ($this->getPostOrQueryParam('confirm')) {
                $listID = $this->getRouteParam('id') ?: null;
                $this->performDeleteFavorite($user, $listID, $deleteId, $deleteSource);
            } else {
                return $this->confirmDeleteFavorite($listID, $deleteId, $deleteSource);
            }
        }

        // If we got this far, we just need to display the favorites:
        try {
            // We want to merge together GET, POST and route parameters to
            // initialize our search object:
            $requestParams = $request->getQueryParams()
                + $request->getParsedBody()
                + ['id' => $this->getRouteParam('id')];

            // Set up listener for recommendations:
            $setupCallback = function ($runner, $params, $searchId): void {
                $listener = new RecommendListener($this->recommendPluginManager, $searchId);
                $listener->setConfig($params->getOptions()->getRecommendationSettings());
                $listener->attach($runner->getEventManager()->getSharedManager());
            };

            $results = $this->searchRunner->run($requestParams, 'Favorites', $setupCallback);
            $listTags = [];

            if ($this->getHelper(UserContentHelper::class)->listTagsEnabled()) {
                if (!($results instanceof \VuFind\Search\Favorites\Results)) {
                    throw new Exception('Results class must be an instance of favorites results!');
                }
                if ($list = $results->getListObject()) {
                    $tags = $this->tagsService->getListTags($list, $list->getUser());
                    foreach ($tags as $tag) {
                        $listTags[$tag['id']] = $tag['tag'];
                    }
                }
            }
            return $this->renderTemplate(
                $request,
                $response,
                [
                    'params' => $results->getParams(),
                    'results' => $results,
                    'listTags' => $listTags,
                ]
            );
        } catch (ListPermissionException $e) {
            if (!$this->authManager->getUserObject()) {
                return $this->getHelper(LoginHelper::class)->forceLogin($request, $response);
            }
            throw $e;
        }
    }

    /**
     * Delete a record from favorites.
     *
     * @param UserEntityInterface $user   User
     * @param ?int                $listID List ID, or null
     * @param string              $id     ID of record to delete
     * @param string              $source Source of record to delete
     *
     * @return void
     */
    public function performDeleteFavorite(UserEntityInterface $user, ?int $listID, string $id, string $source): void
    {
        // Check id parameter:
        if ('' === $id) {
            throw new \Exception('Cannot delete empty ID!');
        }

        // Perform delete and send appropriate flash message:
        if (null !== $listID) {
            // ...Specific List
            $list = $this->userListService->getUserListById($listID);
            $this->favoritesService->removeListResourcesById($list, $user, [$id], $source);
            $this->getHelper(FlashMessagesHelper::class)->addSuccessMessage('Item removed from list');
        } else {
            // ...All Saved Items
            $this->favoritesService->removeUserResourcesById($user, [$id], $source);
            $this->getHelper(FlashMessagesHelper::class)->addSuccessMessage('Item removed from favorites');
        }
    }

    /**
     * Confirm a request to delete a favorite item.
     *
     * @param ?int   $listID List ID, or null
     * @param string $id     ID of record to delete
     * @param string $source Source of record to delete
     *
     * @return mixed
     */
    protected function confirmDeleteFavorite(?int $listID, string $id, string $source): ResponseInterface
    {
        // Normally list ID is found in the route match, but in lightbox context it may sometimes be a GET parameter.
        // We must cover both cases.
        if (null === $listID) {
            $url = $this->getRouteHelper()->getUrlFromRoute('myresearch-favorites');
        } else {
            $url = $this->getRouteHelper()->getUrlFromRoute('userList', ['id' => $listID]);
        }
        return $this->getHelper(ForwardHelper::class)->forwardToConfirm(
            $this->request,
            $this->response,
            'confirm_delete_brief',
            $url,
            $url,
            'confirm_delete',
            ['delete' => $id, 'source' => $source]
        );
    }
}
