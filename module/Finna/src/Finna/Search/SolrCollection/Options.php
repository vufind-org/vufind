<?php

/**
 * Solr Collection aspect of the Search Multi-class (Options)
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2025.
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
 * @package  Search_Solr
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace Finna\Search\SolrCollection;

/**
 * Solr Collection Search Options
 *
 * @category VuFind
 * @package  Search_Solr
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class Options extends \VuFind\Search\SolrCollection\Options
{
    use \Finna\Search\FinnaOptions;

    /**
     * Date range visualization settings
     *
     * @var string
     */
    protected $dateRangeVis;

    /**
     * Constructor
     *
     * @param \VuFind\Config\PluginManager $configLoader Config loader
     */
    public function __construct(\VuFind\Config\PluginManager $configLoader)
    {
        parent::__construct($configLoader);

        $this->dateRangeVis = $this->facetSettings['SpecialFacets']['dateRangeVis'] ?? '';

        // Back-compatibility for display_versions setting in config.ini:
        if (
            !isset($this->searchSettings['General']['display_versions'])
            && (null !== ($setting = $this->mainConfig['Record']['display_versions'] ?? null))
        ) {
            $this->displayRecordVersions = (bool)$setting;
        }

        // Back-compatibility for hierarchical facet filters:
        $this->hierarchicalExcludeFilters
            = $this->facetSettings['HierarchicalExcludeFilters']
            ?? $this->facetSettings['ExcludeFilters']
            ?? [];
        $this->hierarchicalFacetFilters
            = $this->facetSettings['HierarchicalFacetFilters']
            ?? $this->facetSettings['FacetFilters']
            ?? [];
    }

    /**
     * Get the field used for date range search
     *
     * @return string
     */
    public function getDateRangeSearchField(): string
    {
        [$field] = explode(':', $this->dateRangeVis);
        return $field;
    }
}
