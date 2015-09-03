<?php
/**
 * Primo Central Search Options
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
 * @package  Search_Primo
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
namespace VuFind\Search\Primo;

/**
 * Primo Search Options
 *
 * @category VuFind2
 * @package  Search_Primo
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class Options extends \VuFind\Search\Base\Options
{
    /**
     * Advanced search operators
     *
     * @var array
     */
    protected $advancedOperators = [];

    /**
     * Constructor
     *
     * @param \VuFind\Config\PluginManager $configLoader Config loader
     */
    public function __construct(\VuFind\Config\PluginManager $configLoader)
    {
        parent::__construct($configLoader);
        $this->searchIni = $this->facetsIni = 'Primo';
        // Load facet preferences:
        $facetSettings = $configLoader->get($this->facetsIni);
        if (isset($facetSettings->Advanced_Facet_Settings->translated_facets)
            && count($facetSettings->Advanced_Facet_Settings->translated_facets) > 0
        ) {
            $this->setTranslatedFacets(
                $facetSettings->Advanced_Facet_Settings->translated_facets->toArray()
            );
        }
        if (isset($facetSettings->Advanced_Facet_Settings->special_facets)) {
            $this->specialAdvancedFacets
                = $facetSettings->Advanced_Facet_Settings->special_facets;
        }

        // Load the search configuration file:
        $searchSettings = $configLoader->get($this->searchIni);

        // Set up limit preferences
        if (isset($searchSettings->General->default_limit)) {
            $this->defaultLimit = $searchSettings->General->default_limit;
        }
        if (isset($searchSettings->General->limit_options)) {
            $this->limitOptions
                = explode(",", $searchSettings->General->limit_options);
        }

        // Load search preferences:
        if (isset($searchSettings->General->retain_filters_by_default)) {
            $this->retainFiltersByDefault
                = $searchSettings->General->retain_filters_by_default;
        }
        if (isset($searchSettings->General->default_filters)) {
            $this->defaultFilters = $searchSettings->General->default_filters
                ->toArray();
        }

        // Result limit:
        if (isset($searchSettings->General->result_limit)) {
            $this->resultLimit = $searchSettings->General->result_limit;
        } else {
            $this->resultLimit = 3980;  // default
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

        // Advanced operator setup:
        if (isset($searchSettings->Advanced_Operators)) {
            foreach ($searchSettings->Advanced_Operators as $key => $value) {
                $this->advancedOperators[$key] = $value;
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
        return 'primo-search';
    }

    /**
     * Return the route name of the action used for performing advanced searches.
     * Returns false if the feature is not supported.
     *
     * @return string|bool
     */
    public function getAdvancedSearchAction()
    {
        return 'primo-advanced';
    }

    /**
     * Basic 'getter' for Primo advanced search operators.
     *
     * @return array
     */
    public function getAdvancedOperators()
    {
        return $this->advancedOperators;
    }
}
