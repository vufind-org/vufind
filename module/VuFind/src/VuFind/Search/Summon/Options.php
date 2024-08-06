<?php

/**
 * Summon Search Options
 *
 * PHP version 8
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Search_Summon
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Search\Summon;

use function count;

/**
 * Summon Search Options
 *
 * @category VuFind
 * @package  Search_Summon
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class Options extends \VuFind\Search\Base\Options
{
    use \VuFind\Config\Feature\ExplodeSettingTrait;
    use \VuFind\Search\Options\ViewOptionsTrait;

    /**
     * Maximum number of topic recommendations to show (false for none)
     *
     * @var int|bool
     */
    protected $maxTopicRecommendations = false;

    /**
     * Relevance sort override for empty searches
     *
     * @var string
     */
    protected $emptySearchRelevanceOverride = null;

    /**
     * Constructor
     *
     * @param \VuFind\Config\PluginManager $configLoader Config loader
     */
    public function __construct(\VuFind\Config\PluginManager $configLoader)
    {
        parent::__construct($configLoader);
        $this->searchIni = $this->facetsIni = 'Summon';
        // Load facet preferences:
        $facetSettings = $configLoader->get($this->facetsIni);
        if (
            isset($facetSettings->Advanced_Facet_Settings->translated_facets)
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
            $this->limitOptions = $this->explodeListSetting($searchSettings->General->limit_options);
        }

        // Set up highlighting preference
        if (isset($searchSettings->General->highlighting)) {
            $this->highlight = $searchSettings->General->highlighting;
        }

        // Set up spelling preference
        if (isset($searchSettings->Spelling->enabled)) {
            $this->spellcheck = $searchSettings->Spelling->enabled;
        }

        // Load search preferences:
        if (isset($searchSettings->General->default_filters)) {
            $this->defaultFilters = $searchSettings->General->default_filters
                ->toArray();
        }
        if (isset($searchSettings->General->result_limit)) {
            $this->resultLimit = $searchSettings->General->result_limit;
        } else {
            $this->resultLimit = 400;   // default
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
        if (
            isset($searchSettings->DefaultSortingByType)
            && count($searchSettings->DefaultSortingByType) > 0
        ) {
            foreach ($searchSettings->DefaultSortingByType as $key => $val) {
                $this->defaultSortByHandler[$key] = $val;
            }
        }
        if (isset($searchSettings->General->empty_search_relevance_override)) {
            $this->emptySearchRelevanceOverride
                = $searchSettings->General->empty_search_relevance_override;
        }

        // Load autocomplete preferences:
        $this->configureAutocomplete($searchSettings);

        // Set up views
        $this->initViewOptions($searchSettings);

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
     * Return the route name for the facet list action. Returns false to cover
     * unimplemented support.
     *
     * @return string|bool
     */
    public function getFacetListAction()
    {
        return 'summon-facetlist';
    }

    /**
     * Get the relevance sort override for empty searches.
     *
     * @return string Sort field or null if not set
     */
    public function getEmptySearchRelevanceOverride()
    {
        return $this->emptySearchRelevanceOverride;
    }

    /**
     * Get the maximum number of topic recommendations (false for none)
     *
     * @return bool|int
     */
    public function getMaxTopicRecommendations()
    {
        return $this->maxTopicRecommendations;
    }

    /**
     * Set the maximum number of topic recommendations (false for none)
     *
     * @param bool|int $max New maximum setting
     *
     * @return void
     */
    public function setMaxTopicRecommendations($max)
    {
        $this->maxTopicRecommendations = $max;
    }
}
