<?php
/**
 * Default Controller
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
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace Finna\Controller;

/**
 * Redirects the user to the appropriate default VuFind action.
 *
 * @category VuFind2
 * @package  Controller
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class SearchController extends \VuFind\Controller\SearchController
{
    use SearchControllerTrait;

    /**
     * Results action.
     *
     * @return mixed
     */
    public function resultsAction()
    {
        if ($this->getRequest()->getQuery()->get('combined')) {
            $this->saveToHistory = false;
        }

        $this->initCombinedViewFilters();
        $view = parent::resultsAction();
        $this->initSavedTabs();
        return $view;
    }

    /**
     * Handle an advanced search
     *
     * @return mixed
     */
    public function advancedAction()
    {
        $view = parent::advancedAction();

        $range = [
            'type' => 'date',
            'field' => \Finna\Search\Solr\Params::SPATIAL_DATERANGE_FIELD,
        ];

        if ($view->saved
            && $filter = $view->saved->getParams()->getSpatialDateRangeFilter()
        ) {
            if (isset($filter['from']) && isset($filter['to'])) {
                $range['values'] = [$filter['from'], $filter['to']];
                $range['rangeType'] = $filter['type'];
            } else {
                $range['values'] = [null, null];
            }
        }

        $view->daterange = [$range];
        return $view;
    }

    /**
     * Either assign the requested search object to the view or display a flash
     * message indicating why the operation failed.
     *
     * @param string $searchId ID value of a saved advanced search.
     *
     * @return bool|object     Restored search object if found, false otherwise.
     */
    protected function restoreAdvancedSearch($searchId)
    {
        $savedSearch = parent::restoreAdvancedSearch($searchId);
        if ($savedSearch) {
            if ($filter = $savedSearch->getParams()->getSpatialDateRangeFilter(true)
            ) {
                $req = new \Zend\Stdlib\Parameters();
                $req->set(
                    'filter',
                    [$filter['field'] . ':"' . $filter['value'] . '"']
                );
                if (isset($filter['type'])) {
                    $req->set('search_sdaterange_mvtype', $filter['type']);
                }
                $savedSearch->getParams()->initSpatialDateRangeFilter($req);
            }
        }
        return $savedSearch;
    }

    /**
     * Sends search history, alert schedules for saved searches and user's
     * email address to view.
     *
     * @return mixed
     */
    public function historyAction()
    {
        $view = parent::historyAction();
        $user = $this->getUser();
        if ($user) {
            $view->alertemail = $user->email;
        }

        // Retrieve saved searches
        $search = $this->getTable('Search');
        $savedsearches
            = $search->getSavedSearches(is_object($user) ? $user->id : null);

        $schedule = [];
        foreach ($savedsearches as $current) {
            $minSO = $current->getSearchObject(true);
            // Only Solr searches allowed
            if ($minSO->cl !== 'Solr') {
                continue;
            }
            $minSO = $minSO->deminify($this->getResultsManager());
            $schedule[$minSO->getSearchId()] = $current->finna_schedule;
        }
        $view->schedule = $schedule;
        return $view;
    }
}

