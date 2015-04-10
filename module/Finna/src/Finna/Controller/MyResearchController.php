<?php
/**
 * MyResearch Controller
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
 * @category VuFind2
 * @package  Controller
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace Finna\Controller;

/**
 * Controller for the user account area.
 *
 * @category VuFind2
 * @package  Controller
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class MyResearchController extends \VuFind\Controller\MyResearchController
{

    /**
     * Send user's saved favorites from a particular list to the view
     *
     * @return mixed
     */
    public function mylistAction()
    {
        $view = parent::mylistAction();
        if (!$user = $this->getUser()) {
            return $view;
        }

        $params = $view->params->getSortList();
        $sort = isset($_GET['sort']) ? $_GET['sort'] : false;

        $sortList = [
            'saved' => ['desc' => 'sort_saved'],
            'title' => ['desc' => 'sort_title'],
            'author' => ['desc' => 'sort_author'],
            'date' => ['desc' => 'sort_year asc'],
            'format' => ['desc' => 'sort_format']
        ];
        foreach ($sortList as $key => &$data) {
            $data['selected'] = $key === $sort;
        }
        if (!$sort) {
            $sortList['saved']['selected'] = true;
        }
        $view->sortList = $sortList;

        // Number of distinct user resources in all lists
        $resource = $this->getTable('Resource');
        $userResources = $resource->getFavorites(
            $user->id, null, null, null
        );
        $view->numOfResources = count($userResources);

        return $view;
    }
}
