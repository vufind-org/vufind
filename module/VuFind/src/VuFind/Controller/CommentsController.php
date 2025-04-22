<?php

/**
 * Comments Controller
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2025.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.    See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Controller
 * @author   Jaro Ravila <jaro.ravila@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Controller;

use VuFind\Exception\Forbidden as ForbiddenException;
use VuFind\Validator\CsrfInterface;

/**
 * Comments controller.
 *
 * @category VuFind
 * @package  Controller
 * @author   Jaro Ravila <jaro.ravila@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class CommentsController extends AbstractBase
{
    /**
     * Get all comments for the logged in user
     *
     * @return View
     */
    public function userCommentsAction()
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->forceLogin();
        }
        if (!$this->commentsEnabled()) {
            throw new ForbiddenException('Comments disabled.');
        }
        $limit = $this->getService(\VuFind\Config\AccountCapabilities::class)->getUserContentPageSize();
        $page = $this->params()->fromQuery('page', 1);
        $sort = $this->params()->fromQuery('sort', 'created desc');
        $service = $this->getDbService(\VuFind\Db\Service\CommentsServiceInterface::class);
        $comments = $service->getCommentsPaginator(
            $user->getId(),
            $limit,
            $page,
            $sort,
        );

        $sortList = [
            'created desc'  => [
                'desc' => 'sort_created_desc',
                'url' => '?sort=' . urlencode('created desc'),
                'selected' => $sort === 'created desc',
            ],
            'created asc'  => [
                'desc' => 'sort_created_asc',
                'url' => '?sort=' . urlencode('created asc'),
                'selected' => $sort === 'created asc',
            ],
            'title'  => [
                'desc' => 'sort_title',
                'url' => '?sort=' . urlencode('title'),
                'selected' => $sort === 'title',
            ],
        ];
        $recordLoader = $this->serviceLocator->get(\VuFind\Record\Loader::class);
        $ids = [];
        foreach ($comments as $comment) {
            $ids[] = $comment['source'] . '|' . $comment['record_id'];
        }
        $records = $recordLoader->loadBatch($ids, true);
        foreach ($comments as $i => $c) {
            $c['recordTitle'] = $records[$i]->getTitle() ?? '';
        }
        return $this->createViewModel(
            [
                'comments' => $comments,
                'sortList' => $sortList,
                'params' => $this->params()->fromQuery(),
            ]
        );
    }

    /**
     * Delete given comments by the logged in user
     *
     * @return View
     */
    public function deleteCommentsAction()
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->forceLogin();
        }
        if ($this->formWasSubmitted(['deleteSelectedcomment'])) {
            $csrf = $this->getService(CsrfInterface::class);
            if (!$csrf->isValid($this->getRequest()->getPost()->get('csrf'))) {
                throw new \VuFind\Exception\BadRequest(
                    'error_inconsistent_parameters'
                );
            }
        }

        if (!empty($comments = $this->params()->fromPost('deleteSelectedcomment', []))) {
            $commentsService = $this->getDbService(\VuFind\Db\Service\CommentsServiceInterface::class);
            $commentsService->deleteByIdsAndUserId($comments, $user->getId());
        }

        return $this->redirect()->toRoute('comments-usercomments');
    }
}
