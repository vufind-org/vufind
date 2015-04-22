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
     * Return the Favorites sort list options.
     *
     * @return array
     */
    public static function getFavoritesSortList()
    {
        return [
            'saved' => 'sort_saved',
            'title' => 'sort_title',
            'author' => 'sort_author',
            'date' => 'sort_year asc',
            'format' => 'sort_format',
        ];
    }

    /**
     * Create sort list for public list page.
     * If no sort option selected, set first one from the list to default.
     *
     * @return array
     */
    protected function createSortList()
    {
        $sortOptions = self::getFavoritesSortList();
        $sort = isset($_GET['sort']) ? $_GET['sort'] : false;
        if (!$sort) {
            reset($sortOptions);
            $sort = key($sortOptions);
        }
        $sortList = [];
        foreach ($sortOptions as $key => $value) {
            $sortList[$key] = [
                'desc' => $value,
                'selected' => $key === $sort,
            ];
        }

        return $sortList;
    }

    /**
     * Send list of holds to view
     *
     * @return mixed
     */
    public function holdsAction()
    {
        $view = parent::holdsAction();
        $view->recordList = $this->orderAvailability($view->recordList);
        return $view;
    }

    /**
     * Send list of storage retrieval requests to view
     *
     * @return mixed
     */
    public function storageRetrievalRequestsAction()
    {
        $view = parent::storageRetrievalRequestsAction();
        $view->recordList = $this->orderAvailability($view->recordList);
        return $view;
    }

    /**
     * Send list of ill requests to view
     *
     * @return mixed
     */
    public function illRequestsAction()
    {
        $view = parent::illRequestsAction();
        $view->recordList = $this->orderAvailability($view->recordList);
        return $view;
    }

    /**
     * Order available records to beginning of the record list
     *
     * @param type $recordList list to order
     *
     * @return type
     */
    protected function orderAvailability($recordList)
    {
        $availableRecordList = [];
        $recordListBasic = [];
        foreach ($recordList as $item) {
            if ($item->getExtraDetail('ils_details')['available']) {
                $availableRecordList[] = $item;
            } else {
                $recordListBasic[] = $item;
            }
        }
        return array_merge($availableRecordList, $recordListBasic);
    }
}
