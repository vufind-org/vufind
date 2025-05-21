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
        $this->searchIni = $this->facetsIni = 'Summon';
        $this->advancedFacetSettingsSection = 'Advanced_Facet_Settings';

        // Override the default result limit with a value that we can always support:
        $this->defaultResultLimit = 400;

        parent::__construct($configLoader);

        // Set up highlighting preference
        if (null !== ($highlighting = $this->searchSettings['General']['highlighting'] ?? null)) {
            $this->highlight = $highlighting;
        }

        // Set up spelling preference
        if (null !== ($spellcheck = $this->searchSettings['Spelling']['enabled'] ?? null)) {
            $this->spellcheck = $spellcheck;
        }

        $this->emptySearchRelevanceOverride
            = $this->searchSettings['General']['empty_search_relevance_override'] ?? null;

        // Load autocomplete preferences:
        $this->configureAutocomplete($this->searchSettings);

        // Set up views
        $this->initViewOptions($this->searchSettings);
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
