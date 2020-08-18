<?php
/**
 * List Controller
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2015-2019.
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
 * @author   Mika Hatakka <mika.hatakka@helsinki.fi>
 * @author   Tuure Ilmarinen <tuure.ilmarinen@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace Finna\Controller;

use Laminas\Stdlib\Parameters;
use VuFind\Exception\ListPermission as ListPermissionException;
use VuFind\Exception\RecordMissing as RecordMissingException;

/**
 * Controller for the public favorite lists.
 *
 * @category VuFind
 * @package  Controller
 * @author   Mika Hatakka <mika.hatakka@helsinki.fi>
 * @author   Tuure Ilmarinen <tuure.ilmarinen@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class ListController extends \Finna\Controller\MyResearchController
{
    /**
     * Send user's saved favorites from a particular list to the view
     *
     * @return mixed
     */
    public function listAction()
    {
        $lid = $this->params()->fromRoute('lid');
        if ($lid === null) {
            return $this->notFoundAction();
        }
        try {
            $list = $this->getTable('UserList')->getExisting($lid);
            if (!$list->isPublic()) {
                return $this->createNoAccessView();
            }
        } catch (RecordMissingException $e) {
            return $this->notFoundAction();
        }

        try {
            $results = $this->serviceLocator
                ->get(\VuFind\Search\Results\PluginManager::class)->get('Favorites');
            $params = $results->getParams();

            // We want to merge together GET, POST and route parameters to
            // initialize our search object:
            $params->initFromRequest(
                new Parameters(
                    $this->getRequest()->getQuery()->toArray()
                    + $this->getRequest()->getPost()->toArray()
                    + ['id' => $lid]
                )
            );

            $results->performAndProcessSearch();
            $listObj = $results->getListObject();

            // Special case: If we're in RSS view, we need to render differently:
            if (isset($params) && $params->getView() == 'rss') {
                $response = $this->getResponse();
                $response->getHeaders()->addHeaderLine('Content-type', 'text/xml');

                if (!$listObj = $results->getListObject()) {
                    return $this->notFoundAction();
                }

                $feed = $this->getViewRenderer()->plugin('resultfeed');
                $feed->setList($listObj);
                $feed = $feed($results);
                $feed->setTitle($listObj->title);
                if ($desc = $listObj->description) {
                    $feed->setDescription($desc);
                }
                $feed->setLink($this->getServerUrl('home') . "List/$lid");
                $response->setContent($feed->export('rss'));
                return $response;
            }

            $this->rememberCurrentSearchUrl();

            $listTags = null;
            if ($this->listTagsEnabled()) {
                $listTags = $this->getTable('Tags')
                    ->getForList($listObj->id, $listObj->user_id);
            }

            $view = $this->createViewModel(
                [
                    'params' => $params,
                    'results' => $results,
                    'sortList' => $this->createSortList($listObj),
                    'listTags' => $listTags
                ]
            );
            return $view;
        } catch (ListPermissionException $e) {
            return $this->createNoAccessView();
        }
    }

    /**
     * Save action - Allows the save template to appear,
     *   passes containingLists & nonContainingLists
     *
     * @return mixed
     */
    public function saveAction()
    {
        // Fail if lists are disabled:
        if (!$this->listsEnabled()) {
            throw new ForbiddenException('Lists disabled');
        }

        // Check permission:
        $response = $this->permission()->check('feature.Favorites', false);
        if (is_object($response)) {
            return $response;
        }
        $listId = $this->params()->fromRoute('id');
        if ($listId === null) {
            return $this->notFoundAction();
        }
        try {
            $list = $this->getTable('UserList')->getExisting($listId);
            if (!$list->isPublic()) {
                return $this->createNoAccessView();
            }
        } catch (RecordMissingException $e) {
            return $this->notFoundAction();
        }

        // Retrieve user object and force login if necessary:
        if (!($user = $this->getUser())) {
            return $this->forceLogin();
        }

        $this->setFollowupUrlToReferer();

        // Process form submission:
        if ($this->formWasSubmitted('submit')) {
            $runner = $this->serviceLocator->get(\VuFind\Search\SearchRunner::class);
            $callback = function ($callback, $params, $runningSearchId) {
                $params->setLimit(100000);
            };
            $records = $runner->run(
                ['id' => $listId],
                'Favorites',
                $callback
            )->getResults();
            $this->processSave($user, $records);
            if ($this->params()->fromQuery('layout', 'false') == 'lightbox') {
                return $this->getResponse()->setStatusCode(204);
            }
            // redirect to previously stored followup
            if ($url = $this->getFollowupUrl()) {
                $this->clearFollowupUrl();
                return $this->redirect()->toUrl($url);
            }
            // No followup info found?  Send back to list view:
            return $this->redirect()->toRoute('list-page', ['lid' => $sourceListId]);
        }
        $view = $this->createViewModel(
            [
                'listId' => $listId,
                'lists' => $user->getLists()
            ]
        );
        $view->setTemplate('list/save');
        return $view;
    }

    /**
     * ProcessSave -- store the results of the Save action.
     *
     * @param VuFind\Db\Row\User $user    User
     * @param array              $records Records to be saved in userlist
     *
     * @return void
     */
    protected function processSave($user, array $records): void
    {
        // Perform the save operation:
        $post = $this->getRequest()->getPost()->toArray();
        $favorites = $this->serviceLocator
            ->get(\VuFind\Favorites\FavoritesService::class);
        $results = $favorites->saveMany($post, $user, $records);

        // Display a success status message:
        $listUrl = $this->url()->fromRoute('userList', ['id' => $results['listId']]);
        $message = [
            'html' => true,
            'msg' => $this->translate('bulk_save_success') . '. '
            . '<a href="' . $listUrl . '" class="gotolist">'
            . $this->translate('go_to_list') . '</a>.'
        ];
        $this->flashMessenger()->addMessage($message, 'success');
    }

    /**
     * Create simple error page for no access error.
     *
     * @return type
     */
    protected function createNoAccessView()
    {
        $config = $this->serviceLocator->get(\VuFind\Config\PluginManager::class)
            ->get('config');
        $view = $this->createViewModel();
        $view->setTemplate('list/no_access');
        $view->email = $config->Site->email;
        return $view;
    }
}
