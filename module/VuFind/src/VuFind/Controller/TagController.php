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
    use Feature\UserContentTrait;

    /**
     * Array of sort options for userListAction
     *
     * @var array
     */
    protected array $sortList = [
        'posted desc' => 'sort_created_desc',
        'posted asc' => 'sort_created_asc',
        'title' => 'sort_title',
    ];

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
    public function userListAction()
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->forceLogin();
        }
        if (!$this->tagsEnabled()) {
            throw new ForbiddenException('Tags disabled.');
        }
        $paging = $this->getPagingParams($this->params());
        $service = $this->getDbService(\VuFind\Db\Service\ResourceTagsServiceInterface::class);
        $tags = $this->getUserContentRecordTitles(
            $service->getResourceTagsPaginator(
                $user->getId(),
                null,
                null,
                $paging['sort'],
                $paging['page'],
                $paging['limit'],
            )
        );
        return $this->createViewModel(
            [
                'tags' => $tags,
                'sortList' => $this->getSortList($this->sortList, $paging['sort']),
                'params' => $this->params()->fromQuery(),
            ]
        );
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
            $this->getDbService(\VuFind\Db\Service\TagServiceInterface::class)->deleteOrphanedTags();
        }

        return $this->redirect()->toRoute('tag-userlist');
    }
}
