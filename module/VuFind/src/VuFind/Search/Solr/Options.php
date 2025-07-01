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
     * @var ?string
     */
    protected $emptySearchRelevanceOverride;

    /**
     * Whether to display record versions
     *
     * @var bool
     */
    protected $displayRecordVersions;

    /**
     * Solr field to be used as a tie-breaker.
     *
     * @var ?string
     */
    protected $sortTieBreaker;

    /**
     * Constructor
     *
     * @param \VuFind\Config\PluginManager $configLoader Config loader
     */
    public function __construct(\VuFind\Config\PluginManager $configLoader)
    {
        parent::__construct($configLoader);

        $this->sortTieBreaker = $this->searchSettings['General']['tie_breaker_sort'] ?? null;
        $this->emptySearchRelevanceOverride
            = $this->searchSettings['General']['empty_search_relevance_override'] ?? null;
        $this->displayRecordVersions = $this->searchSettings['General']['display_versions'] ?? true;

        // Use default sort options if not specified in configuration:
        if (!$this->sortOptions) {
            $this->sortOptions = ['relevance' => 'sort_relevance',
                'year' => 'sort_year', 'year asc' => 'sort_year_asc',
                'callnumber-sort' => 'sort_callnumber', 'author' => 'sort_author',
                'title' => 'sort_title'];
        }

        // Set up views
        $this->initViewOptions($this->searchSettings);

        // Load facet preferences
        if ($delimiter = $this->facetSettings['Advanced_Settings']['delimiter'] ?? null) {
            $this->setDefaultFacetDelimiter($delimiter);
        }
        if ($delimitedFacets = $this->facetSettings['Advanced_Settings']['delimited_facets'] ?? null) {
            $this->setDelimitedFacets((array)$delimitedFacets);
        }
        if ($hierarchical = $this->facetSettings['SpecialFacets']['hierarchical'] ?? null) {
            $this->hierarchicalFacets = (array)$hierarchical;
        }
        if ($separators = $this->facetSettings['SpecialFacets']['hierarchicalFacetSeparators'] ?? null) {
            $this->hierarchicalFacetSeparators = (array)$separators;
        }

        $this->hierarchicalFacetSortSettings
            = (array)($this->facetSettings['SpecialFacets']['hierarchicalFacetSortOptions'] ?? []);

        // Load Spelling preferences
        if (null !== ($spellcheck = $this->mainConfig['Spelling']['enabled'] ?? null)) {
            $this->spellcheck = $spellcheck;
        }

        // Turn on first/last navigation if configured:
        if ($this->mainConfig['Record']['first_last_navigation'] ?? false) {
            $this->recordPageFirstLastNavigation = true;
        }

        // Turn on highlighting if the user has requested highlighting or snippet functionality:
        $highlight = $this->searchSettings['General']['highlighting'] ?? false;
        $snippet = $this->searchSettings['General']['snippets'] ?? false;
        if ($highlight || $snippet) {
            $this->highlight = true;
        }

        // Load autocomplete preferences:
        $this->configureAutocomplete($this->searchSettings);

        // Load shard settings
        $this->shards = (array)($this->searchSettings['IndexShards'] ?? []);
        if ($this->shards) {
            // If we have a default from the configuration, use that...
            if ($defaultShards = $this->searchSettings['ShardPreferences']['defaultChecked'] ?? null) {
                $this->defaultSelectedShards = (array)$defaultShards;
            } else {
                // If no default is configured, use all shards...
                $this->defaultSelectedShards = array_keys($this->shards);
            }
            // Apply checkbox visibility setting if applicable:
            if (null !== ($visibleCheckboxes = $this->searchSettings['ShardPreferences']['showCheckboxes'])) {
                $this->visibleShardCheckboxes = $visibleCheckboxes;
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
