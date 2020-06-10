<?php
/**
 * Finna search controller trait.
 *
 * PHP version 7
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Controller
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
namespace Finna\Controller;

/**
 * Finna search controller trait.
 *
 * @category VuFind
 * @package  Controller
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
trait FinnaSearchControllerTrait
{
    /**
     * Save a search to the history in the database.
     * Save search Id and type to memory
     *
     * @param \VuFind\Search\Base\Results $results Search results
     *
     * @return void
     */
    public function saveSearchToHistory($results)
    {
        parent::saveSearchToHistory($results);
        $this->getSearchMemory()->rememberSearchData(
            $results->getSearchId(),
            $results->getParams()->getSearchType(),
            $results->getUrlQuery()->isQuerySuppressed()
                ? '' : $results->getParams()->getDisplayQuery(),
            $results->getBackendId()
        );
    }

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
        if (!(bool)$query->get('combined')) {
            return;
        }

        $combined = $this->getCombinedSearches();
        if (!isset($combined[$this->searchClassId])) {
            // No active search with this search class
            return;
        }

        $searchId = $combined[$this->searchClassId];
        $search = $this->getTable('Search')->getRowById($searchId, false);
        if (!$search) {
            return;
        }

        $minSO = $search->getSearchObject();
        $savedSearch = $minSO->deminify(
            $this->serviceLocator->get(\VuFind\Search\Results\PluginManager::class)
        );
        $params = $savedSearch->getUrlQuery()->getParamArray();
        foreach ($params as $key => $value) {
            if ($key == 'filter') {
                $this->getRequest()->getQuery()->set('filter', $value);
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
