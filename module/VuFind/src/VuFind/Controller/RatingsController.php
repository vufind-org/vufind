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
    /**
     * Get all ratings for the logged in user
     *
     * @return View
     */
    public function userRatingsAction()
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->forceLogin();
        }
        if (!$this->ratingsEnabled()) {
            throw new ForbiddenException('Ratings disabled.');
        }
        $limit = $this->getService(\VuFind\Config\AccountCapabilities::class)->getUserContentPageSize();
        $page = $this->params()->fromQuery('page', 1);
        $sort = $this->params()->fromQuery('sort', 'created desc');
        $service = $this->getDbService(\VuFind\Db\Service\RatingsServiceInterface::class);
        $ratings = $service->getRatingsPaginator(
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
        foreach ($ratings as $rating) {
            $ids[] = $rating['source'] . '|' . $rating['record_id'];
        }
        $records = $recordLoader->loadBatch($ids, true);
        foreach ($ratings as $i => $c) {
            $c['recordTitle'] = $records[$i]->getTitle() ?? '';
        }
        return $this->createViewModel(
            [
                'ratings' => $ratings,
                'sortList' => $sortList,
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

        return $this->redirect()->toRoute('ratings-userratings');
    }
}
