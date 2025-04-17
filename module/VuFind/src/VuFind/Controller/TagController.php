<?php

/**
 * Tag Controller
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Controller;

use Laminas\ServiceManager\ServiceLocatorInterface;
use VuFind\Exception\Forbidden as ForbiddenException;
use VuFind\Validator\CsrfInterface;

/**
 * Tag Controller
 *
 * @category VuFind
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class TagController extends AbstractSearch
{
    /**
     * Constructor
     *
     * @param ServiceLocatorInterface $sm Service locator
     */
    public function __construct(ServiceLocatorInterface $sm)
    {
        $this->searchClassId = 'Tags';
        parent::__construct($sm);
    }

    /**
     * Home action
     *
     * @return mixed
     */
    public function homeAction()
    {
        if (!$this->tagsEnabled()) {
            throw new ForbiddenException('Tags disabled');
        }
        return parent::resultsAction();
    }

    /**
     * Get all tags for the logged in user
     *
     * @return View
     */
    public function userTagAction()
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->forceLogin();
        }
        if (!$this->tagsEnabled()) {
            throw new ForbiddenException('Tags disabled.');
        }
        $limit = $this->getService(\VuFind\Config\AccountCapabilities::class)->getUserReviewsPageSize();
        $page = $this->params()->fromQuery('page', 1);
        $sort = $this->params()->fromQuery('sort', 'posted desc');
        $service = $this->getDbService(\VuFind\Db\Service\ResourceTagsServiceInterface::class);
        $tags = $service->getResourceTagsPaginator(
            $user->getId(),
            null,
            null,
            $sort,
            $page,
            $limit,
        );
        $sortList = [
            'posted desc'  => [
                'desc' => 'sort_created_desc',
                'url' => '?sort=' . urlencode('posted desc'),
                'selected' => $sort === 'posted desc',
            ],
            'posted asc'  => [
                'desc' => 'sort_created_asc',
                'url' => '?sort=' . urlencode('posted asc'),
                'selected' => $sort === 'posted asc',
            ],
            'title'  => [
                'desc' => 'sort_title',
                'url' => '?sort=' . urlencode('title'),
                'selected' => $sort === 'title',
            ],
        ];
        $recordLoader = $this->serviceLocator->get(\VuFind\Record\Loader::class);
        $ids = [];
        foreach ($tags as $tag) {
            $ids[] = $tag['source'] . '|' . $tag['record_id'];
        }
        $records = $recordLoader->loadBatch($ids, true);
        foreach ($tags as $i => $c) {
            $c['recordTitle'] = $records[$i]->getTitle() ?? '';
        }
        $view = $this->createViewModel(
            [
                'tags' => $tags,
                'sortList' => $sortList,
                'params' => $this->params()->fromQuery(),
            ]
        );
        $view->setTemplate('tag/usertags.phtml');
        return $view;
    }

    /**
     * Delete given tags by the logged in user
     *
     * @return View
     */
    public function deleteTagsAction()
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->forceLogin();
        }
        if ($this->formWasSubmitted(['deleteSelectedtag'])) {
            $csrf = $this->getService(CsrfInterface::class);
            if (!$csrf->isValid($this->getRequest()->getPost()->get('csrf'))) {
                throw new \VuFind\Exception\BadRequest(
                    'error_inconsistent_parameters'
                );
            }
        }
        if (!empty($tags = $this->params()->fromPost('deleteSelectedtag', []))) {
            $tagService = $this->getDbService(\VuFind\Db\Service\ResourceTagsServiceInterface::class);
            $tagService->deleteLinksByResourceTagsIdArray($tags);
        }

        return $this->redirect()->toRoute('tag-usertag');
    }
}
