<?php
/**
 * Summon Search Options
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
 * @package  Search_Summon
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
namespace VuFind\Search\Summon;

/**
 * Summon Search Options
 *
 * @category VuFind2
 * @package  Search_Summon
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class Options extends \VuFind\Search\Base\Options
{
    /**
     * Maximum number of results
     *
     * @var int
     */
    protected $resultLimit = 400;

    /**
     * Constructor
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->searchIni = $this->facetsIni = 'Summon';
    }

    /**
     * Perform initialization that cannot occur in constructor due to need for
     * injected dependencies.
     *
     * @return void
     */
    public function init()
    {
        parent::init();

        // Load facet preferences:
        $facetSettings = $this->getServiceLocator()->get('VuFind\Config')
            ->get($this->facetsIni);
        if (isset($facetSettings->Advanced_Facet_Settings->translated_facets)
            && count($facetSettings->Advanced_Facet_Settings->translated_facets) > 0
        ) {
            $list = $facetSettings->Advanced_Facet_Settings->translated_facets;
            foreach ($list as $c) {
                $this->translatedFacets[] = $c;
            }
        }
        if (isset($facetSettings->Advanced_Facet_Settings->special_facets)) {
            $this->specialAdvancedFacets
                = $facetSettings->Advanced_Facet_Settings->special_facets;
        }

        // Load the search configuration file:
        $searchSettings = $this->getServiceLocator()->get('VuFind\Config')
            ->get($this->searchIni);

        // Set up highlighting preference
        if (isset($searchSettings->General->highlighting)) {
            $this->highlight = $searchSettings->General->highlighting;
        }

        // Set up spelling preference
        if (isset($searchSettings->Spelling->enabled)) {
            $this->spellcheck = $searchSettings->Spelling->enabled;
        }

        // Load search preferences:
        if (isset($searchSettings->General->retain_filters_by_default)) {
            $this->retainFiltersByDefault
                = $searchSettings->General->retain_filters_by_default;
        }
        if (isset($searchSettings->General->result_limit)) {
            $this->resultLimit = $searchSettings->General->result_limit;
        }

        // Search handler setup:
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
     * Return the route name for the search results action.
     *
     * @return string
     */
    public function getSearchAction()
    {
        return 'summon-search';
    }

    /**
     * Return the route name of the action used for performing advanced searches.
     * Returns false if the feature is not supported.
     *
     * @return string|bool
     */
    public function getAdvancedSearchAction()
    {
        return 'summon-advanced';
    }

    /**
     * If there is a limit to how many search results a user can access, this
     * method will return that limit.  If there is no limit, this will return
     * -1.
     *
     * @return int
     */
    public function getVisibleSearchResultLimit()
    {
        return intval($this->resultLimit);
    }
}