<?php
/**
 * Admin Tag Controller
 *
 * PHP version 5
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace VuFindAdmin\Controller;

/**
 * Class controls distribution of tags and resource tags.
 *
 * @category VuFind2
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
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
        return (isset($this->params[$param]))
            ? $this->params[$param]
            : $this->params()->fromPost(
                $param,
                $this->params()->fromQuery($param, null)
            );
    }

    /**
     * Tag Details
     *
     * @return \Zend\View\Model\ViewModel
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
     * @return \Zend\View\Model\ViewModel
     */
    public function manageAction()
    {
        $this->params = $this->params()->fromQuery();

        $view = $this->createViewModel();
        $view->setTemplate('admin/tags/manage');
        $view->type = !is_null($this->params()->fromPost('type', null))
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
     * @return \Zend\View\Model\ViewModel
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
     * @return \Zend\View\Model\ViewModel
     */
    public function deleteAction()
    {
        $this->params = $this->params()->fromPost();
        $tags = $this->getTable('ResourceTags');

        $origin = $this->params()
            ->fromPost('origin', $this->params()->fromQuery('origin'));

        $action = ("list" == $origin) ? 'List' : 'Manage';

        $originUrl = $this->url()
            ->fromRoute('admin/tags', array('action' => $action));
        if ($action == 'List') {
            $originUrl .= '?' . http_build_query(
                array(
                    'user_id' => $this->getParam('user_id'),
                    'resource_id' => $this->getParam('resource_id'),
                    'tag_id' => $this->getParam('tag_id'),
                )
            );
        }
        $newUrl = $this->url()->fromRoute('admin/tags', array('action' => 'Delete'));

        $confirm = $this->params()->fromPost('confirm', false);

        // Delete All
        if ("manage" == $origin
            || !is_null($this->getRequest()->getPost('deleteFilter'))
            || !is_null($this->getRequest()->getQuery('deleteFilter'))
        ) {
            if (false === $confirm) {
                return $this->confirmTagsDeleteByFilter($tags, $originUrl, $newUrl);
            }
            $delete = $this->deleteResourceTagsByFilter();
        } else {
            // Delete by ID
            // Fail if we have nothing to delete:
            $ids = is_null($this->getRequest()->getPost('deletePage'))
                ? $this->params()->fromPost('ids')
                : $this->params()->fromPost('idsAll');

            if (!is_array($ids) || empty($ids)) {
                $this->flashMessenger()->setNamespace('error')
                    ->addMessage('bulk_noitems_advice');
                return $this->redirect()->toUrl($originUrl);
            }

            if (false === $confirm) {
                return $this->confirmTagsDelete($ids, $originUrl, $newUrl);
            }
            $delete = $tags->deleteByIdArray($ids);

        }

        if (0 == $delete) {
            $this->flashMessenger()->setNamespace('error')
                ->addMessage('tags_delete_fail');
            return $this->redirect()->toUrl($originUrl);
        }

        $this->flashMessenger()->setNamespace('info')
            ->addMessage(
                array(
                    'msg' => 'tags_deleted',
                    'tokens' => array('%count%' => $delete)
                )
            );
        return $this->redirect()->toUrl($originUrl);
    }

    /**
     * Confirm Delete by Id
     *
     * @param array  $ids       A list of resource tag Ids
     * @param string $originUrl An origin url
     * @param string $newUrl    The url of the desired action
     *
     * @return $this->confirmAction
     */
    protected function confirmTagsDelete($ids, $originUrl, $newUrl)
    {
        $messages = array();
        $count = count($ids);

        $user = $this->getTable('user')
            ->select(array('id' => $this->getParam('user_id')))
            ->current();
        $userMsg = (false !== $user)
            ? $user->username . " (" . $user->id . ")" : "All";

        $tag = $this->getTable('tags')
            ->select(array('id' => $this->getParam('tag_id')))
            ->current();
        $tagMsg = (false !== $tag) ? $tag->tag. " (" . $tag->id . ")" : " All";

        $resource = $this->getTable('resource')
            ->select(array('id' => $this->getParam('resource_id')))
            ->current();
        $resourceMsg = (false !== $resource)
            ? $resource->title. " (" . $resource->id . ")" : " All";

        $messages[] = array(
            'msg' => 'tag_delete_warning',
            'tokens' => array('%count%' => $count)
        );
        if (false !== $user || false!== $tag || false !== $resource) {
            $messages[] = array(
                'msg' => 'tag_delete_filter',
                'tokens' => array(
                    '%username%' => $userMsg,
                    '%tag%' => $tagMsg,
                    '%resource%' => $resourceMsg
                )
            );
        }
        $data = array(
            'data' => array(
                'confirm' => $newUrl,
                'cancel' => $originUrl,
                'title' => "confirm_delete_tags_brief",
                'messages' => $messages,
                'ids' => $ids,
                'extras' => array(
                    'origin' => 'list',
                    'user_id' => $this->getParam('user_id'),
                    'tag_id' => $this->getParam('tag_id'),
                    'resource_id' => $this->getParam('resource_id'),
                    'ids' => $ids
                )
            )
        );

        return $this->forwardTo('Confirm', 'Confirm', $data);
    }

    /**
     * Confirm Tag Delete by Filter
     *
     * @param object $tagModel  A Tag object
     * @param string $originUrl An origin url
     * @param string $newUrl    The url of the desired action
     *
     * @return $this->confirmAction
     */
    protected function confirmTagsDeleteByFilter($tagModel, $originUrl, $newUrl)
    {
        $messages = array();
        $count = $tagModel->getResourceTags(
            $this->convertFilter($this->getParam('user_id')),
            $this->convertFilter($this->getParam('resource_id')),
            $this->convertFilter($this->getParam('tag_id'))
        )->getTotalItemCount();

        $user = $this->getTable('user')
            ->select(array('id' => $this->getParam('user_id')))
            ->current();
        $userMsg = (false !== $user)
            ? $user->username . " (" . $user->id . ")" : "All";

        $tag = $this->getTable('tags')
            ->select(array('id' => $this->getParam('tag_id')))
            ->current();

        $tagMsg = (false !== $tag)
            ? $tag->tag. " (" . $tag->id . ")" : " All";

        $resource = $this->getTable('resource')
            ->select(array('id' => $this->getParam('resource_id')))
            ->current();

        $resourceMsg = (false !== $resource)
            ? $resource->title. " (" . $resource->id . ")" : " All";

        $messages[] = array(
            'msg' => 'tag_delete_warning',
            'tokens' => array('%count%' => $count)
        );

        if (false !== $user || false!== $tag || false !== $resource) {
            $messages[] = array(
                'msg' => 'tag_delete_filter',
                'tokens' => array(
                    '%username%' => $userMsg,
                    '%tag%' => $tagMsg,
                    '%resource%' => $resourceMsg
                )
            );
        }

        $data = array(
            'data' => array(
                'confirm' => $newUrl,
                'cancel' => $originUrl,
                'title' => "confirm_delete_tags_brief",
                'messages' => $messages,
                'origin' => 'manage',
                'extras' => array(
                    'type' => $this->getParam('type'),
                    'user_id' => $this->getParam('user_id'),
                    'tag_id' => $this->getParam('tag_id'),
                    'resource_id' => $this->getParam('resource_id'),
                    'deleteFilter' => $this->getParam('deleteFilter')
                )
            )
        );

        return $this->forwardTo('Confirm', 'Confirm', $data);
    }

    /**
     * Gets a list of unique resources based on the url params
     *
     * @return \Zend\Db\ResultSet
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
     * @return \Zend\Db\ResultSet
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
     * @return \Zend\Db\ResultSet
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
     * @return \Zend\Paginator\Paginator
     */
    protected function getResourceTags()
    {
        $currentPage = isset($this->params['page']) ? $this->params['page'] : "1";
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
        $ids = array();
        foreach ($tags as $tag) {
            $ids[] = $tag->id;
        }
        return $this->getTable('ResourceTags')->deleteByIdArray($ids);
    }
}
