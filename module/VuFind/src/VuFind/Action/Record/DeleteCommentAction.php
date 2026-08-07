<?php

/**
 * Record "delete comment" action.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010-2024.
 * Copyright (C) The National Library of Finland 2026.
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

namespace VuFind\Action\Record;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\ActionHelper\FlashMessagesHelper;
use VuFind\ActionHelper\LoginHelper;
use VuFind\ActionHelper\UserContentHelper;
use VuFind\Auth\Manager as AuthManager;
use VuFind\Db\Service\CommentsServiceInterface;
use VuFind\Db\Service\PluginManager as DbServicePluginManager;
use VuFind\Exception\Forbidden as ForbiddenException;
use VuFind\Record\Loader as RecordLoader;
use VuFind\Record\Router as RecordRouter;
use VuFind\RecordTab\TabManager;
use VuFind\Search\Memory as SearchMemory;
use VuFind\Search\ResultScroller;
use VuFind\ServiceManager\Factory\Autowire;

/**
 * Record "delete comment" action.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class DeleteComentAction extends AbstractRecordAction
{
    /**
     * Constructor.
     *
     * @param SearchMemory             $searchMemory    Search memory
     * @param TabManager               $tabManager      Tab manager
     * @param AuthManager              $authManager     Authentication manager
     * @param RecordLoader             $recordLoader    Record loader
     * @param RecordRouter             $recordRouter    Record router
     * @param ResultScroller           $resultScroller  Result scroller
     * @param array                    $config          VuFind configuration
     * @param CommentsServiceInterface $commentsService Comments database service
     */
    public function __construct(
        SearchMemory $searchMemory,
        TabManager $tabManager,
        AuthManager $authManager,
        RecordLoader $recordLoader,
        RecordRouter $recordRouter,
        ResultScroller $resultScroller,
        #[Autowire(config: 'config')]
        array $config,
        #[Autowire(container: DbServicePluginManager::class)]
        protected CommentsServiceInterface $commentsService,
    ) {
        parent::__construct(
            $searchMemory,
            $tabManager,
            $authManager,
            $recordLoader,
            $recordRouter,
            $resultScroller,
            $config
        );
    }

    /**
     * Delete a comment.
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
        // Make sure comments are enabled:
        if (!$this->getHelper(UserContentHelper::class)->commentsEnabled()) {
            throw new ForbiddenException('Comments disabled');
        }

        // Force login:
        if (!($user = $this->getUser())) {
            // Remember comment since POST data will be lost:
            return $this->getHelper(LoginHelper::class)->forceLogin($request, $response);
        }

        $id = $this->getQueryParam('delete');
        $flashMessagesHelper = $this->getHelper(FlashMessagesHelper::class);
        if (null !== $id && $this->commentsService->deleteIfOwnedByUser($id, $user)) {
            $flashMessagesHelper->addSuccessMessage('delete_comment_success');
        } else {
            $flashMessagesHelper->addErrorMessage('delete_comment_failure');
        }
        return $this->redirectToRecord('', 'UserComments');
    }
}
