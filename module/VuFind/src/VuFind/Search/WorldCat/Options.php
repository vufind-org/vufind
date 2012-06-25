<?php
/**
 * WorldCat Search Options
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2011.
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
 * @package  SearchObject
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */

/**
 * WorldCat Search Options
 *
 * @category VuFind2
 * @package  SearchObject
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class VF_Search_WorldCat_Options extends VF_Search_Base_Options
{
    /**
     * Constructor
     *
     * @return void
     */
    public function __construct()
    {
        $this->searchIni = $this->facetsIni = 'WorldCat';
        parent::__construct();

        // Load the configuration file:
        $searchSettings = VF_Config_Reader::getConfig($this->searchIni);

        // Search handler setup:
        $this->defaultHandler = 'srw.kw';
        if (isset($searchSettings->Basic_Searches)) {
            foreach ($searchSettings->Basic_Searches as $key => $value) {
                $this->basicHandlers[$key] = $value;
            }
        }
        if (isset($searchSettings->Advanced_Searches)) {
            foreach ($searchSettings->Advanced_Searches as $key => $value) {
                $this->advancedHandlers[$key] = $value;
            }
        }

        // Load sort preferences:
        if (isset($searchSettings->Sorting)) {
            foreach ($searchSettings->Sorting as $key => $value) {
                $this->sortOptions[$key] = $value;
            }
        }
        if (isset($searchSettings->General->default_sort)) {
            $this->defaultSort = $searchSettings->General->default_sort;
        }
        if (isset($searchSettings->DefaultSortingByType)
            && count($searchSettings->DefaultSortingByType) > 0
        ) {
            foreach ($searchSettings->DefaultSortingByType as $key => $val) {
                $this->defaultSortByHandler[$key] = $val;
            }
        }
    }

    /**
     * Return an array describing the action used for rendering search results
     * (same format as expected by the URL view helper).
     *
     * @return array
     */
    public function getSearchAction()
    {
        return array('controller' => 'WorldCat', 'action' => 'Search');
    }

    /**
     * Return an array describing the action used for performing advanced searches
     * (same format as expected by the URL view helper).  Return false if the feature
     * is not supported.
     *
     * @return array|bool
     */
    public function getAdvancedSearchAction()
    {
        return array('controller' => 'WorldCat', 'action' => 'Advanced');
    }
}