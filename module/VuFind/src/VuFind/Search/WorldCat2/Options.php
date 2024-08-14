<?php

/**
 * WorldCat v2 Search Options
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2024.
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
 * @package  Search_WorldCat2
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Search\WorldCat2;

/**
 * WorldCat v2 Search Options
 *
 * @category VuFind
 * @package  Search_WorldCat2
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class Options extends \VuFind\Search\Base\Options
{
    /**
     * Constructor
     *
     * @param \VuFind\Config\PluginManager $configLoader Config loader
     */
    public function __construct(\VuFind\Config\PluginManager $configLoader)
    {
        parent::__construct($configLoader);
        $this->searchIni = $this->facetsIni = 'WorldCat2';

        // Load the configuration file:
        $searchSettings = $configLoader->get($this->searchIni);

        // Search handler setup:
        $this->defaultHandler = 'kw';
        foreach ($searchSettings->Basic_Searches ?? [] as $key => $value) {
            $this->basicHandlers[$key] = $value;
        }
        foreach ($searchSettings->Advanced_Searches ?? [] as $key => $value) {
            $this->advancedHandlers[$key] = $value;
        }

        // Load sort preferences:
        foreach ($searchSettings->Sorting ?? [] as $key => $value) {
            $this->sortOptions[$key] = $value;
        }
        $this->defaultSort = $searchSettings->General->default_sort ?? 'bestMatch';
        foreach ($searchSettings->DefaultSortingByType ?? [] as $key => $val) {
            $this->defaultSortByHandler[$key] = $val;
        }
        // Load list view for result (controls AJAX embedding vs. linking)
        if (isset($searchSettings->List->view)) {
            $this->listviewOption = $searchSettings->List->view;
        }
    }

    /**
     * Return the route name for the search results action.
     *
     * @return string
     */
    public function getSearchAction()
    {
        return 'worldcat2-search';
    }

    /**
     * Return the route name of the action used for performing advanced searches.
     * Returns false if the feature is not supported.
     *
     * @return string|bool
     */
    public function getAdvancedSearchAction()
    {
        return 'worldcat2-advanced';
    }
}
