<?php

/**
 * Solr Collection aspect of the Search Multi-class (Options)
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Search_SolrAuthor
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Search\SolrCollection;

use VuFind\Config\ConfigManagerInterface;

/**
 * Solr Collection Search Options
 *
 * @category VuFind
 * @package  Search_SolrAuthor
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class Options extends \VuFind\Search\Solr\Options
{
    /**
     * Constructor
     *
     * @param ConfigManagerInterface $configManager Config manager
     */
    public function __construct(ConfigManagerInterface $configManager)
    {
        $this->facetsIni = 'Collection';
        parent::__construct($configManager);

        // Load sort preferences from Collection.ini even though other settings are loaded from searches.ini
        // (or set defaults if none in .ini file):
        $searchSettings = $configManager->getConfigArray('Collection');
        if (null !== ($sortOptions = $searchSettings['Sort'] ?? null)) {
            $this->sortOptions = (array)$sortOptions;
        } else {
            $this->sortOptions = [
                'title' => 'sort_title',
                'year' => 'sort_year', 'year asc' => 'sort_year_asc',
                'author' => 'sort_author',
            ];
        }
        $this->defaultSort = key($this->sortOptions);
    }

    /**
     * Return the route name for the facet list action. Returns false to cover
     * unimplemented support.
     *
     * @return string|bool
     */
    public function getFacetListAction()
    {
        return 'search-collectionfacetlist';
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
        // Collection recommendations
        $searchSettings = $this->configManager->getConfigArray('Collection');
        return $searchSettings['Recommend'] ?? ['side' => ['CollectionSideFacets:Facets::Collection:true']];
    }

    /**
     * Return the route name for the search results action.
     *
     * @return string
     */
    public function getSearchAction()
    {
        return 'collection';
    }
}
