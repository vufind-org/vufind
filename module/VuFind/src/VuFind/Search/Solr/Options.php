<?php
/**
 * Solr aspect of the Search Multi-class (Options)
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
 * @package  Search_Solr
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
namespace VuFind\Search\Solr;

/**
 * Solr Search Options
 *
 * @category VuFind2
 * @package  Search_Solr
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class Options extends \VuFind\Search\Base\Options
{
    /**
     * Spelling limit
     *
     * @var int
     */
    protected $spellingLimit = 3;

    /**
     * Spell check words with numbers in them?
     *
     * @var bool
     */
    protected $spellSkipNumeric = true;

    /**
     * Pre-assigned filters
     *
     * @var array
     */
    protected $hiddenFilters = array();

    /**
     * Perform initialization that cannot occur in constructor due to need for
     * injected dependencies.
     *
     * @return void
     */
    public function init()
    {
        parent::init();

        $searchSettings = $this->getServiceLocator()->get('VuFind\Config')
            ->get($this->searchIni);
        if (isset($searchSettings->General->default_limit)) {
            $this->defaultLimit = $searchSettings->General->default_limit;
        }
        if (isset($searchSettings->General->limit_options)) {
            $this->limitOptions
                = explode(",", $searchSettings->General->limit_options);
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
        if (isset($searchSettings->RSS->sort)) {
            $this->rssSort = $searchSettings->RSS->sort;
        }
        if (isset($searchSettings->General->default_view)) {
            $this->defaultView = $searchSettings->General->default_view;
        }
        if (isset($searchSettings->General->default_handler)) {
            $this->defaultHandler = $searchSettings->General->default_handler;
        }
        if (isset($searchSettings->General->retain_filters_by_default)) {
            $this->retainFiltersByDefault
                = $searchSettings->General->retain_filters_by_default;
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
            $this->sortOptions = array('relevance' => 'sort_relevance',
                'year' => 'sort_year', 'year asc' => 'sort_year asc',
                'callnumber' => 'sort_callnumber', 'author' => 'sort_author',
                'title' => 'sort_title');
        }
        // Load view preferences (or defaults if none in .ini file):
        if (isset($searchSettings->Views)) {
            foreach ($searchSettings->Views as $key => $value) {
                $this->viewOptions[$key] = $value;
            }
        } elseif (isset($searchSettings->General->default_view)) {
            $this->viewOptions = array($this->defaultView => $this->defaultView);
        } else {
            $this->viewOptions = array('list' => 'List');
        }

        // Load facet preferences
        $facetSettings = $this->getServiceLocator()->get('VuFind\Config')
            ->get($this->facetsIni);
        if (isset($facetSettings->Advanced_Settings->translated_facets)
            && count($facetSettings->Advanced_Settings->translated_facets) > 0
        ) {
            foreach ($facetSettings->Advanced_Settings->translated_facets as $c) {
                $this->translatedFacets[] = $c;
            }
        }
        if (isset($facetSettings->Advanced_Settings->special_facets)) {
            $this->specialAdvancedFacets
                = $facetSettings->Advanced_Settings->special_facets;
        }

        // Load Spelling preferences
        $config = $this->getServiceLocator()->get('VuFind\Config')->get('config');
        if (isset($config->Spelling->enabled)) {
            $this->spellcheck = $config->Spelling->enabled;
        }
        if (isset($config->Spelling->limit)) {
            $this->spellingLimit = $config->Spelling->limit;
        }
        if (isset($config->Spelling->skip_numeric)) {
            $this->spellSkipNumeric = $config->Spelling->skip_numeric;
        }

        // Turn on highlighting if the user has requested highlighting or snippet
        // functionality:
        $highlight = !isset($searchSettings->General->highlighting)
            ? false : $searchSettings->General->highlighting;
        $snippet = !isset($searchSettings->General->snippets)
            ? false : $searchSettings->General->snippets;
        if ($highlight || $snippet) {
            $this->highlight = true;
        }

        // Load autocomplete preference:
        if (isset($searchSettings->Autocomplete->enabled)) {
            $this->autocompleteEnabled = $searchSettings->Autocomplete->enabled;
        }

        // Load shard settings
        if (isset($searchSettings->IndexShards)
            && !empty($searchSettings->IndexShards)
        ) {
            foreach ($searchSettings->IndexShards as $k => $v) {
                $this->shards[$k] = $v;
            }
            // If we have a default from the configuration, use that...
            if (isset($searchSettings->ShardPreferences->defaultChecked)
                && !empty($searchSettings->ShardPreferences->defaultChecked)
            ) {
                $defaultChecked
                    = is_object($searchSettings->ShardPreferences->defaultChecked)
                    ? $searchSettings->ShardPreferences->defaultChecked->toArray()
                    : array($searchSettings->ShardPreferences->defaultChecked);
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
     * Add a hidden (i.e. not visible in facet controls) filter query to the object.
     *
     * @param string $fq Filter query for Solr.
     *
     * @return void
     */
    public function addHiddenFilter($fq)
    {
        $this->hiddenFilters[] = $fq;
    }

    /**
     * Get an array of hidden filters.
     *
     * @return array
     */
    public function getHiddenFilters()
    {
        return $this->hiddenFilters;
    }


    /**
     * Are we skipping numeric words?
     *
     * @return bool
     */
    public function shouldSkipNumericSpelling()
    {
        return $this->spellSkipNumeric;
    }


    /**
     * Get the spelling limit.
     *
     * @return int
     */
    public function getSpellingLimit()
    {
        return $this->spellingLimit;
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
}