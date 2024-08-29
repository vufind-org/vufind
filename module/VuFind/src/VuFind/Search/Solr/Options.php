<?php

/**
 * Solr aspect of the Search Multi-class (Options)
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
 * @package  Search_Solr
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Search\Solr;

use function count;
use function is_object;

/**
 * Solr Search Options
 *
 * @category VuFind
 * @package  Search_Solr
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class Options extends \VuFind\Search\Base\Options
{
    use \VuFind\Config\Feature\ExplodeSettingTrait;
    use \VuFind\Search\Options\ViewOptionsTrait;

    /**
     * Available sort options for facets
     *
     * @var array
     */
    protected $facetSortOptions = [
        '*' => ['count' => 'sort_count', 'index' => 'sort_alphabetic'],
    ];

    /**
     * Relevance sort override for empty searches
     *
     * @var string
     */
    protected $emptySearchRelevanceOverride = null;

    /**
     * Whether to display record versions
     *
     * @var bool
     */
    protected $displayRecordVersions = true;

    /**
     * Solr field to be used as a tie-breaker.
     *
     * @var string
     */
    protected $sortTieBreaker = null;

    /**
     * Constructor
     *
     * @param \VuFind\Config\PluginManager $configLoader Config loader
     */
    public function __construct(\VuFind\Config\PluginManager $configLoader)
    {
        parent::__construct($configLoader);
        $searchSettings = $configLoader->get($this->searchIni);
        if (isset($searchSettings->General->default_limit)) {
            $this->defaultLimit = $searchSettings->General->default_limit;
        }
        if (isset($searchSettings->General->limit_options)) {
            $this->limitOptions = $this->explodeListSetting($searchSettings->General->limit_options);
        }
        if (isset($searchSettings->General->default_sort)) {
            $this->defaultSort = $searchSettings->General->default_sort;
        }
        if (isset($searchSettings->General->tie_breaker_sort)) {
            $this->sortTieBreaker = $searchSettings->General->tie_breaker_sort;
        }
        if (isset($searchSettings->General->empty_search_relevance_override)) {
            $this->emptySearchRelevanceOverride
                = $searchSettings->General->empty_search_relevance_override;
        }
        if (
            isset($searchSettings->DefaultSortingByType)
            && count($searchSettings->DefaultSortingByType) > 0
        ) {
            foreach ($searchSettings->DefaultSortingByType as $key => $val) {
                $this->defaultSortByHandler[$key] = $val;
            }
        }
        if (isset($searchSettings->RSS->sort)) {
            $this->rssSort = $searchSettings->RSS->sort;
        }
        if (isset($searchSettings->General->default_handler)) {
            $this->defaultHandler = $searchSettings->General->default_handler;
        }
        if (isset($searchSettings->General->default_filters)) {
            $this->defaultFilters = $searchSettings->General->default_filters
                ->toArray();
        }
        if (isset($searchSettings->General->display_versions)) {
            $this->displayRecordVersions
                = $searchSettings->General->display_versions;
        }

        // Result limit:
        if (isset($searchSettings->General->result_limit)) {
            $this->resultLimit = $searchSettings->General->result_limit;
        }
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

        // Load sort preferences (or defaults if none in .ini file):
        if (isset($searchSettings->Sorting)) {
            foreach ($searchSettings->Sorting as $key => $value) {
                $this->sortOptions[$key] = $value;
            }
        } else {
            $this->sortOptions = ['relevance' => 'sort_relevance',
                'year' => 'sort_year', 'year asc' => 'sort_year_asc',
                'callnumber-sort' => 'sort_callnumber', 'author' => 'sort_author',
                'title' => 'sort_title'];
        }

        // Set up views
        $this->initViewOptions($searchSettings);

        // Load list view for result (controls AJAX embedding vs. linking)
        if (isset($searchSettings->List->view)) {
            $this->listviewOption = $searchSettings->List->view;
        }

        // Load facet preferences
        $facetSettings = $configLoader->get($this->facetsIni);
        if (
            isset($facetSettings->Advanced_Settings->translated_facets)
            && count($facetSettings->Advanced_Settings->translated_facets) > 0
        ) {
            $this->setTranslatedFacets(
                $facetSettings->Advanced_Settings->translated_facets->toArray()
            );
        }
        if (isset($facetSettings->Advanced_Settings->delimiter)) {
            $this->setDefaultFacetDelimiter(
                $facetSettings->Advanced_Settings->delimiter
            );
        }
        if (
            isset($facetSettings->Advanced_Settings->delimited_facets)
            && count($facetSettings->Advanced_Settings->delimited_facets) > 0
        ) {
            $this->setDelimitedFacets(
                $facetSettings->Advanced_Settings->delimited_facets->toArray()
            );
        }
        if (isset($facetSettings->Advanced_Settings->special_facets)) {
            $this->specialAdvancedFacets
                = $facetSettings->Advanced_Settings->special_facets;
        }
        if (isset($facetSettings->SpecialFacets->hierarchical)) {
            $this->hierarchicalFacets
                = $facetSettings->SpecialFacets->hierarchical->toArray();
        }
        if (isset($facetSettings->SpecialFacets->hierarchicalFacetSeparators)) {
            $this->hierarchicalFacetSeparators = $facetSettings->SpecialFacets
                ->hierarchicalFacetSeparators->toArray();
        }
        $this->hierarchicalFacetSortSettings
            = $facetSettings?->SpecialFacets?->hierarchicalFacetSortOptions?->toArray() ?? [];

        // Load Spelling preferences
        $config = $configLoader->get($this->mainIni);
        if (isset($config->Spelling->enabled)) {
            $this->spellcheck = $config->Spelling->enabled;
        }

        // Turn on first/last navigation if configured:
        if (
            isset($config->Record->first_last_navigation)
            && $config->Record->first_last_navigation
        ) {
            $this->recordPageFirstLastNavigation = true;
        }

        // Turn on highlighting if the user has requested highlighting or snippet
        // functionality:
        $highlight = $searchSettings->General->highlighting ?? false;
        $snippet = $searchSettings->General->snippets ?? false;
        if ($highlight || $snippet) {
            $this->highlight = true;
        }

        // Load autocomplete preferences:
        $this->configureAutocomplete($searchSettings);

        // Load shard settings
        if (
            isset($searchSettings->IndexShards)
            && !empty($searchSettings->IndexShards)
        ) {
            foreach ($searchSettings->IndexShards as $k => $v) {
                $this->shards[$k] = $v;
            }
            // If we have a default from the configuration, use that...
            if (
                isset($searchSettings->ShardPreferences->defaultChecked)
                && !empty($searchSettings->ShardPreferences->defaultChecked)
            ) {
                $defaultChecked
                    = is_object($searchSettings->ShardPreferences->defaultChecked)
                    ? $searchSettings->ShardPreferences->defaultChecked->toArray()
                    : [$searchSettings->ShardPreferences->defaultChecked];
                foreach ($defaultChecked as $current) {
                    $this->defaultSelectedShards[] = $current;
                }
            } else {
                // If no default is configured, use all shards...
                $this->defaultSelectedShards = array_keys($this->shards);
            }
            // Apply checkbox visibility setting if applicable:
            if (isset($searchSettings->ShardPreferences->showCheckboxes)) {
                $this->visibleShardCheckboxes
                    = $searchSettings->ShardPreferences->showCheckboxes;
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
        return 'search-results';
    }

    /**
     * Return the route name of the action used for performing advanced searches.
     * Returns false if the feature is not supported.
     *
     * @return string|bool
     */
    public function getAdvancedSearchAction()
    {
        return 'search-advanced';
    }

    /**
     * Return the route name for the facet list action. Returns false to cover
     * unimplemented support.
     *
     * @return string|bool
     */
    public function getFacetListAction()
    {
        return 'search-facetlist';
    }

    /**
     * Return the route name for the versions search action or false if disabled.
     *
     * @return string|bool
     */
    public function getVersionsAction()
    {
        return $this->displayRecordVersions ? 'search-versions' : false;
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
     * Get the field to be used as a sort tie-breaker.
     *
     * @return ?string Sort field or null if not set
     */
    public function getSortTieBreaker()
    {
        return $this->sortTieBreaker;
    }

    /**
     * Does this search backend support scheduled searching?
     *
     * @return bool
     */
    public function supportsScheduledSearch()
    {
        // Solr supports this!
        return true;
    }
}
