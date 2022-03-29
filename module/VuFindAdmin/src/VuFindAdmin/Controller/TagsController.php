<?php
/**
 * Admin Tag Controller
 *
 * PHP version 7
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
namespace VuFindAdmin\Controller;

/**
 * Class controls distribution of tags and resource tags.
 *
 * @category VuFind
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class TagsController extends AbstractAdmin
{
    /**
     * Params
     *
     * @var array
     */
    protected $params;

    /**
     * Get the url parameters
     *
     * @param string $param A key to check the url params for
     *
     * @return string
     */
    protected function getParam($param)
    {
        return $this->params[$param] ?? $this->params()->fromPost(
            $param,
            $this->params()->fromQuery($param, null)
        );
    }

    /**
     * Tag Details
     *
     * @return \Laminas\View\Model\ViewModel
     */
    public function homeAction()
    {
        $view = $this->createViewModel();
        $view->setTemplate('admin/tags/home');
        $view->statistics = $this->getTable('resourcetags')->getStatistics(true);
        return $view;
    }

    /**
     * Manage Tags
     *
     * @return \Laminas\View\Model\ViewModel
     */
    public function manageAction()
    {
        $this->params = $this->params()->fromQuery();

        $view = $this->createViewModel();
        $view->setTemplate('admin/tags/manage');
        $view->type = null !== $this->params()->fromPost('type', null)
            ? $this->params()->fromPost('type')
            : $this->params()->fromQuery('type', null);
        $view->uniqueTags      = $this->getUniqueTags()->toArray();
        $view->uniqueUsers     = $this->getUniqueUsers()->toArray();
        $view->uniqueResources = $this->getUniqueResources()->toArray();
        $view->params = $this->params;
        return $view;
    }

    /**
     * List Tags
     *
     * @return \Laminas\View\Model\ViewModel
     */
    public function listAction()
    {
        $this->params = $this->params()->fromQuery();

        $view = $this->createViewModel();
        $view->setTemplate('admin/tags/list');
        $view->uniqueTags      = $this->getUniqueTags()->toArray();
        $view->uniqueUsers     = $this->getUniqueUsers()->toArray();
        $view->uniqueResources = $this->getUniqueResources()->toArray();
        $view->results = $this->getResourceTags();
        $view->params = $this->params;
        return $view;
    }

    /**
     * Delete Tags
     *
     * @return \Laminas\View\Model\ViewModel
     */
    public function deleteAction()
    {
        $this->params = $this->params()->fromPost();
        $tags = $this->getTable('ResourceTags');

        $origin = $this->params()
            ->fromPost('origin', $this->params()->fromQuery('origin'));

        $action = ("list" == $origin) ? 'List' : 'Manage';

        $originUrl = $this->url()
            ->fromRoute('admin/tags', ['action' => $action]);
        if ($action == 'List') {
            $originUrl .= '?' . http_build_query(
                [
                    'user_id' => $this->getParam('user_id'),
                    'resource_id' => $this->getParam('resource_id'),
                    'tag_id' => $this->getParam('tag_id'),
                ]
            );
        }
        $newUrl = $this->url()->fromRoute('admin/tags', ['action' => 'Delete']);

        $confirm = $this->params()->fromPost('confirm', false);

        // Delete All
        if ("manage" == $origin
            || null !== $this->getRequest()->getPost('deleteFilter')
            || null !== $this->getRequest()->getQuery('deleteFilter')
        ) {
            if (false === $confirm) {
                return $this->confirmTagsDeleteByFilter($tags, $originUrl, $newUrl);
            }
            $delete = $this->deleteResourceTagsByFilter();
        } else {
            // Delete by ID
            // Fail if we have nothing to delete:
            $ids = null === $this->getRequest()->getPost('deletePage')
                ? $this->params()->fromPost('ids')
                : $this->params()->fromPost('idsAll');

            if (!is_array($ids) || empty($ids)) {
                $this->flashMessenger()->addMessage('bulk_noitems_advice', 'error');
                return $this->redirect()->toUrl($originUrl);
            }

            if (false === $confirm) {
                return $this->confirmTagsDelete($ids, $originUrl, $newUrl);
            }
            $delete = $tags->deleteByIdArray($ids);
        }

        if (0 == $delete) {
            $this->flashMessenger()->addMessage('tags_delete_fail', 'error');
            return $this->redirect()->toUrl($originUrl);
        }

        $this->flashMessenger()->addMessage(
            [
                'msg' => 'tags_deleted',
                'tokens' => ['%count%' => $delete]
            ],
            'success'
        );
        return $this->redirect()->toUrl($originUrl);
    }

    /**
     * Get confirmation messages.
     *
     * @param int $count Count of tags that are about to be deleted
     *
     * @return array
     */
    protected function getConfirmDeleteMessages($count)
    {
        // Default all messages to "All"; we'll make them more specific as needed:
        $userMsg = $tagMsg = $resourceMsg = 'All';

        $userId = $this->getParam('user_id');
        if ($userId) {
            $user = $this->getTable('user')->select(['id' => $userId])->current();
            if (!is_object($user)) {
                throw new \Exception("Unexpected error retrieving user $userId");
            }
            $userMsg = "{$user->username} ({$user->id})";
        }

        $tagId = $this->getParam('tag_id');
        if ($tagId) {
            $tag = $this->getTable('tags')->select(['id' => $tagId])->current();
            if (!is_object($tag)) {
                throw new \Exception("Unexpected error retrieving tag $tagId");
            }
            $tagMsg = "{$tag->tag} ({$tag->id})";
        }

        $resourceId = $this->getParam('resource_id');
        if ($resourceId) {
            $resource = $this->getTable('resource')
                ->select(['id' => $resourceId])->current();
            if (!is_object($resource)) {
                throw new \Exception(
                    "Unexpected error retrieving resource $resourceId"
                );
            }
            $resourceMsg = "{$resource->title} ({$resource->id})";
        }

        $messages = [
            [
                'msg' => 'tag_delete_warning',
                'tokens' => ['%count%' => $count]
            ]
        ];
        if ($userId || $tagId || $resourceId) {
            $messages[] = [
                'msg' => 'tag_delete_filter',
                'tokens' => [
                    '%username%' => $userMsg,
                    '%tag%' => $tagMsg,
                    '%resource%' => $resourceMsg
                ]
            ];
        }
        $messages[] = ['msg' => 'confirm_delete'];
        return $messages;
    }

    /**
     * Confirm Delete by Id
     *
     * @param array  $ids       A list of resource tag Ids
     * @param string $originUrl An origin url
     * @param string $newUrl    The url of the desired action
     *
     * @return mixed
     */
    protected function confirmTagsDelete($ids, $originUrl, $newUrl)
    {
        $count = count($ids);

        $data = [
            'data' => [
                'confirm' => $newUrl,
                'cancel' => $originUrl,
                'title' => "confirm_delete_tags_brief",
                'messages' => $this->getConfirmDeleteMessages($count),
                'ids' => $ids,
                'extras' => [
                    'origin' => 'list',
                    'user_id' => $this->getParam('user_id'),
                    'tag_id' => $this->getParam('tag_id'),
                    'resource_id' => $this->getParam('resource_id'),
                    'ids' => $ids
                ]
            ]
        ];

        return $this->forwardTo('Confirm', 'Confirm', $data);
    }

    /**
     * Confirm Tag Delete by Filter
     *
     * @param object $tagModel  A Tag object
     * @param string $originUrl An origin url
     * @param string $newUrl    The url of the desired action
     *
     * @return mixed
     */
    protected function confirmTagsDeleteByFilter($tagModel, $originUrl, $newUrl)
    {
        $count = $tagModel->getResourceTags(
            $this->convertFilter($this->getParam('user_id')),
            $this->convertFilter($this->getParam('resource_id')),
            $this->convertFilter($this->getParam('tag_id'))
        )->getTotalItemCount();

        $data = [
            'data' => [
                'confirm' => $newUrl,
                'cancel' => $originUrl,
                'title' => "confirm_delete_tags_brief",
                'messages' => $this->getConfirmDeleteMessages($count),
                'extras' => [
                    'origin' => 'manage',
                    'type' => $this->getParam('type'),
                    'user_id' => $this->getParam('user_id'),
                    'tag_id' => $this->getParam('tag_id'),
                    'resource_id' => $this->getParam('resource_id'),
                    'deleteFilter' => $this->getParam('deleteFilter')
                ]
            ]
        ];

        return $this->forwardTo('Confirm', 'Confirm', $data);
    }

    /**
     * Gets a list of unique resources based on the url params
     *
     * @return \Laminas\Db\ResultSet
     */
    protected function getUniqueResources()
    {
        return $this->getTable('ResourceTags')->getUniqueResources(
            $this->convertFilter($this->getParam('user_id')),
            $this->convertFilter($this->getParam('resource_id')),
            $this->convertFilter($this->getParam('tag_id'))
        );
    }

    /**
     * Gets a list of unique tags based on the url params
     *
     * @return \Laminas\Db\ResultSet
     */
    protected function getUniqueTags()
    {
        return $this->getTable('ResourceTags')->getUniqueTags(
            $this->convertFilter($this->getParam('user_id')),
            $this->convertFilter($this->getParam('resource_id')),
            $this->convertFilter($this->getParam('tag_id'))
        );
    }

    /**
     * Gets a list of unique users based on the url params
     *
     * @return \Laminas\Db\ResultSet
     */
    protected function getUniqueUsers()
    {
        return $this->getTable('ResourceTags')->getUniqueUsers(
            $this->convertFilter($this->getParam('user_id')),
            $this->convertFilter($this->getParam('resource_id')),
            $this->convertFilter($this->getParam('tag_id'))
        );
    }

    /**
     * Converts empty params and "ALL" to null
     *
     * @param string $value A parameter to check
     *
     * @return string|null A modified parameter
     */
    protected function convertFilter($value)
    {
        return ("ALL" !== $value && "" !== $value && null !== $value)
            ? $value : null;
    }

    /**
     * Get and set a list of resource tags
     *
     * @return \Laminas\Paginator\Paginator
     */
    protected function getResourceTags()
    {
        $currentPage = $this->params['page'] ?? "1";
        $resourceTags = $this->getTable('ResourceTags');
        $tags = $resourceTags->getResourceTags(
            $this->convertFilter($this->getParam('user_id')),
            $this->convertFilter($this->getParam('resource_id')),
            $this->convertFilter($this->getParam('tag_id')),
            $this->getParam('order'),
            $currentPage
        );
        return $tags;
    }

    /**
     * Delete tags based on filter settings.
     *
     * @return int Number of IDs deleted
     */
    protected function deleteResourceTagsByFilter()
    {
        $tags = $this->getResourceTags();
        $ids = [];
        foreach ($tags as $tag) {
            $ids[] = $tag->id;
        }
        return $this->getTable('ResourceTags')->deleteByIdArray($ids);
    }
}
