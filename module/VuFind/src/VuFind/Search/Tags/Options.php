<?php

/**
 * Tags aspect of the Search Multi-class (Options)
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
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
 * @package  Search_Tags
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Search\Tags;

/**
 * Search Tags Options
 *
 * @category VuFind
 * @package  Search_Tags
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class Options extends \VuFind\Search\Base\Options
{
    /**
     * Should we load Solr search options for a more integrated search experience
     * or omit them to prevent confusion in multi-backend environments?
     *
     * @var bool
     */
    protected $useSolrSearchOptions = false;

    /**
     * Constructor
     *
     * @param \VuFind\Config\PluginManager $configLoader Config loader
     */
    public function __construct(\VuFind\Config\PluginManager $configLoader)
    {
        parent::__construct($configLoader);
        $config = $configLoader->get($this->mainIni);
        if (
            isset($config->Social->show_solr_options_in_tag_search)
            && $config->Social->show_solr_options_in_tag_search
        ) {
            $this->useSolrSearchOptions = true;
        }
        $searchSettings = $this->useSolrSearchOptions
            ? $configLoader->get($this->searchIni) : null;
        if (isset($searchSettings->Basic_Searches)) {
            foreach ($searchSettings->Basic_Searches as $key => $value) {
                $this->basicHandlers[$key] = $value;
            }
        } else {
            $this->basicHandlers = ['tag' => 'Tag'];
        }
        $this->defaultHandler = 'tag';
        $this->defaultSort = 'title';
        $this->sortOptions = [
            'title' => 'sort_title', 'author' => 'sort_author',
            'year DESC' => 'sort_year', 'year' => 'sort_year_asc',
        ];
        // Load autocomplete preferences:
        $this->configureAutocomplete($searchSettings);
    }

    /**
     * Return the route name of the action used for performing advanced searches.
     * Returns false if the feature is not supported.
     *
     * @return string|bool
     */
    public function getAdvancedSearchAction()
    {
        return $this->useSolrSearchOptions ? 'search-advanced' : null;
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
     * Load all recommendation settings from the relevant ini file. Returns an
     * associative array where the key is the location of the recommendations (top
     * or side) and the value is the settings found in the file (which may be either
     * a single string or an array of strings).
     *
     * @param string $handler Name of handler for which to load specific settings.
     *
     * @return array associative: location (top/side/etc.) => search settings
     */
    public function getRecommendationSettings($handler = null)
    {
        // No recommendation modules in tag view currently:
        return [];
    }
}
