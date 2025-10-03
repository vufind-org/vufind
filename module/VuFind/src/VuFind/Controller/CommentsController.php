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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
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
    use Feature\UserContentTrait;

    /**
     * Array of sort options for userListAction
     *
     * @var array
     */
    protected array $sortList = [
        'created desc' => 'sort_created_desc',
        'created asc' => 'sort_created_asc',
        'title' => 'sort_title',
    ];

    /**
     * Get all comments for the logged in user
     *
     * @return View
     */
    public function userListAction()
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->forceLogin();
        }
        if (!$this->commentsEnabled()) {
            throw new ForbiddenException('Comments disabled.');
        }
        $paging = $this->getPagingParams($this->params());
        $service = $this->getDbService(\VuFind\Db\Service\CommentsServiceInterface::class);
        $comments = $this->getUserContentRecordTitles(
            $service->getCommentsPaginator(
                $user->getId(),
                $paging['limit'],
                $paging['page'],
                $paging['sort'],
            )
        );
        return $this->createViewModel(
            [
                'comments' => $comments,
                'sortList' => $this->getSortList($this->sortList, $paging['sort']),
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

        return $this->redirect()->toRoute('comments-userlist');
    }
}
