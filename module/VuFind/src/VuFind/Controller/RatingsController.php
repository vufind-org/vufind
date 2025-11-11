<?php

/**
 * Ratings Controller
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
 * Ratings controller.
 *
 * @category VuFind
 * @package  Controller
 * @author   Jaro Ravila <jaro.ravila@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class RatingsController extends AbstractBase
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
     * Get all ratings for the logged in user
     *
     * @return View
     */
    public function userListAction()
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->forceLogin();
        }
        if (!$this->ratingsEnabled()) {
            throw new ForbiddenException('Ratings disabled.');
        }
        $paging = $this->getPagingParams($this->params());
        $service = $this->getDbService(\VuFind\Db\Service\RatingsServiceInterface::class);
        $ratings = $this->getUserContentRecordTitles(
            $service->getRatingsPaginator(
                $user->getId(),
                $paging['limit'],
                $paging['page'],
                $paging['sort'],
            )
        );
        return $this->createViewModel(
            [
                'ratings' => $ratings,
                'sortList' => $this->getSortList($this->sortList, $paging['sort']),
                'params' => $this->params()->fromQuery(),
            ]
        );
    }

    /**
     * Delete given ratings by the logged in user
     *
     * @return View
     */
    public function deleteRatingsAction()
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->forceLogin();
        }
        if ($this->formWasSubmitted(['deleteSelectedrating'])) {
            $csrf = $this->getService(CsrfInterface::class);
            if (!$csrf->isValid($this->getRequest()->getPost()->get('csrf'))) {
                throw new \VuFind\Exception\BadRequest(
                    'error_inconsistent_parameters'
                );
            }
        }
        if (
            !empty($ratings = $this->params()->fromPost('deleteSelectedrating', []))
            && $this->getService(\VuFind\Config\AccountCapabilities::class)->isRatingRemovalAllowed()
        ) {
            $ratingsService = $this->getDbService(\VuFind\Db\Service\RatingsServiceInterface::class);
            $ratingsService->deleteByIdsAndUserId($ratings, $user->getId());
        }

        return $this->redirect()->toRoute('ratings-userlist');
    }
}
