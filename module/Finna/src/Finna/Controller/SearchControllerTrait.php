<?php
/**
 * Finna search controller trait.
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2015-2016.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace Finna\Controller;

/**
 * Finna search controller trait.
 *
 * @category VuFind2
 * @package  Controller
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
trait SearchControllerTrait
{
    /**
     * Pass saved search ids from all tabs to layout.
     *
     * @return void
     */
    protected function initSavedTabs()
    {
        if ($savedTabs = $this->getRequest()->getQuery()->get('search')) {
            $saved = [];
            foreach ($savedTabs as $tab) {
                list($searchClass, $searchId) = explode(':', $tab);
                $saved[$searchClass] = $searchId;
            }
            $this->layout()->savedTabs = $saved;
        }
    }

    /**
     * Append search filters from a active search to the request object.
     * This is used in the combined results view.
     *
     * @return void
     */
    protected function initCombinedViewFilters()
    {
        $query = $this->getRequest()->getQuery();
        if (!(boolean)$query->get('combined')) {
            return;
        }

        $combined = $this->getCombinedSearches();
        if (!isset($combined[$this->searchClassId])) {
            // No active search with this search class
            return;
        }

        $searchId = $combined[$this->searchClassId];
        $search = $this->getTable('Search')->getRowByHash($searchId);
        if (false === $search) {
            return;
        }

        $minSO = $search->getSearchObject();
        $savedSearch = $minSO->deminify(
            $this->getServiceLocator()->get('VuFind\SearchResultsPluginManager')
        );
        $params = $savedSearch->getUrlQuery()->getParamArray();
        foreach ($params as $key => $value) {
            if ($key == 'filter') {
                $this->getRequest()->getQuery()->set('filter', $value);
                // Check if we have a spatial date range filter and get its type too
                $field = $savedSearch->getParams()->getSpatialDateRangeField();
                foreach ($value as $filter) {
                    if (strncmp($filter, $field, strlen($field)) == 0) {
                        $filterInfo = $savedSearch->getParams()
                            ->getSpatialDateRangeFilter();
                        if (isset($filterInfo['type'])) {
                            $this->getRequest()->getQuery()
                                ->set($field . '_type', $filterInfo['type']);
                        }
                        break;
                    }
                }
                break;
            }
        }
    }

    /**
     * Return active searches from the request object as
     * an array of searchClass => searchId elements.
     * This is used in the combined results view.
     *
     * @return array
     */
    protected function getCombinedSearches()
    {
        $query = $this->getRequest()->getQuery();
        if (!$saved = $query->get('search')) {
            return false;
        }

        $ids = [];
        foreach ($saved as $search) {
            list($backend, $searchId) = explode(':', $search, 2);
            $ids[$backend] = $searchId;
        }
        return $ids;
    }
}
