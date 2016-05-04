<?php
/**
 * List Controller
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2015.
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
 * @category VuFind
 * @package  Controller
 * @author   Mika Hatakka <mika.hatakka@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace Finna\Controller;

use VuFind\Exception\ListPermission as ListPermissionException,
    VuFind\Exception\RecordMissing as RecordMissingException,
    Zend\Stdlib\Parameters;

/**
 * Controller for the user account area.
 *
 * @category VuFind
 * @package  Controller
 * @author   Mika Hatakka <mika.hatakka@helsinki.fi>
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
            $results = $this->getServiceLocator()
                ->get('VuFind\SearchResultsPluginManager')->get('Favorites');
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

            $username = $this->getListUsername($results->getListObject()->user_id);

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

            $view = $this->createViewModel(
                [
                    'params' => $params,
                    'results' => $results,
                    'list_username' => $username,
                    'sortList' => $this->createSortList()
                ]
            );
            return $view;
        } catch (ListPermissionException $e) {
            return $this->createNoAccessView();
        }
    }

    /**
     * Return list owners username without institution and email domain part.
     *
     * @param int $listUserId lists owners user id
     *
     * @return string username
     */
    protected function getListUsername($listUserId)
    {
        $user = $this->getUser();
        if ($user && $user->id == $listUserId) {
            $listUser = $user;
        } else {
            $table = $this->getTable('User');
            $listUser = $table->getById($listUserId);
        }

        return $listUser;
    }

    /**
     * Create simple error page for no access error.
     *
     * @return type
     */
    protected function createNoAccessView()
    {
        $config = $this->getServiceLocator()->get('VuFind\Config')->get('config');
        $view = $this->createViewModel();
        $view->setTemplate('list/no_access');
        $view->email = $config->Site->email;
        return $view;
    }

}
