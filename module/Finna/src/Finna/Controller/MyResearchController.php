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

        $view->sortList = $this->createSortList();

        return $view;
    }

    /**
     * Create sort list for public list page
     *
     * @return array
     */
    protected function createSortList()
    {
        $sort = isset($_GET['sort']) ? $_GET['sort'] : false;

        $config = $this->getServiceLocator()->get('VuFind\Config');
        $searchSettings = $config->get('searches');

        $sortList = [];
        if (isset($searchSettings->FavoritesSort)) {
            foreach ($searchSettings->FavoritesSort as $key => $value) {
                $sortList[$key] = [
                    'desc' => $value,
                    'selected' => $key === $sort,
                ];
            }

            if (!$sort) {
                if (isset($searchSettings->General->favorites_default_sort)) {
                    $sortList[$searchSettings->General->favorites_default_sort]
                        ['selected'] = true;
                }
            }
        }

        return $sortList;
    }

}
