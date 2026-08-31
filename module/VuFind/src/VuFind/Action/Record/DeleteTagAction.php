<?php

/**
 * Record "delete tag" action.
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
use VuFind\Exception\Forbidden as ForbiddenException;
use VuFind\Record\Loader as RecordLoader;
use VuFind\Record\Router as RecordRouter;
use VuFind\RecordTab\TabManager;
use VuFind\Search\Memory as SearchMemory;
use VuFind\Search\ResultScroller;
use VuFind\ServiceManager\Factory\Autowire;
use VuFind\Tags\TagsService;

/**
 * Record "delete tag" action.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class DeleteTagAction extends AbstractRecordAction
{
    /**
     * Constructor.
     *
     * @param SearchMemory   $searchMemory   Search memory
     * @param TabManager     $tabManager     Tab manager
     * @param AuthManager    $authManager    Authentication manager
     * @param RecordLoader   $recordLoader   Record loader
     * @param RecordRouter   $recordRouter   Record router
     * @param ResultScroller $resultScroller Result scroller
     * @param array          $config         VuFind configuration
     * @param TagsService    $tagsService    Tags service
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
        protected TagsService $tagsService,
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
     * Delete a tag.
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
        // Make sure tags are enabled:
        if (!$this->getHelper(UserContentHelper::class)->tagsEnabled()) {
            throw new ForbiddenException('Tags disabled');
        }

        // Force login:
        if (!($user = $this->getUser())) {
            return $this->getHelper(LoginHelper::class)->forceLogin($request, $response);
        }

        // Obtain the current record object:
        $driver = $this->loadRecord();

        // Delete tags, if any:
        if ($tag = $this->getPostParam('tag')) {
            $this->tagsService->unlinkTagsFromRecord($driver, $user, [$tag]);
            $this->getHelper(FlashMessagesHelper::class)->addSuccessMessage(
                [
                    'msg' => 'tags_deleted',
                    'tokens' => ['%count%' => 1],
                ],
            );
        }

        return $this->redirectToRecord();
    }
}
