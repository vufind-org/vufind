<?php

/**
 * FacetList content block.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2018.
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
 * @package  ContentBlock
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:recommendation_modules Wiki
 */

namespace VuFind\ContentBlock;

use Laminas\Config\Config;
use VuFind\Config\PluginManager as ConfigManager;
use VuFind\Search\FacetCache\PluginManager as FacetCacheManager;

/**
 * FacetList content block.
 *
 * @category VuFind
 * @package  ContentBlock
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:recommendation_modules Wiki
 */
class FacetList implements ContentBlockInterface
{
    /**
     * Number of values to put in each column of results.
     *
     * @var int
     */
    protected $columnSize = 10;

    /**
     * Search class ID to use for retrieving facets.
     *
     * @var string
     */
    protected $searchClassId = 'Solr';

    /**
     * Configuration manager
     *
     * @var ConfigManager
     */
    protected $configManager;

    /**
     * Facet cache plugin manager
     *
     * @var FacetCacheManager
     */
    protected $facetCacheManager;

    /**
     * Constructor
     *
     * @param FacetCacheManager $fcm Facet cache plugin manager
     * @param ConfigManager     $cm  Configuration manager
     */
    public function __construct(FacetCacheManager $fcm, ConfigManager $cm)
    {
        $this->facetCacheManager = $fcm;
        $this->configManager = $cm;
    }

    /**
     * Get an array of hierarchical facets
     *
     * @param Config $facetConfig Facet configuration object.
     *
     * @return array Facets
     */
    protected function getHierarchicalFacets($facetConfig)
    {
        return isset($facetConfig->SpecialFacets->hierarchical)
            ? $facetConfig->SpecialFacets->hierarchical->toArray()
            : [];
    }

    /**
     * Get hierarchical facet sort settings
     *
     * @param Config $facetConfig Facet configuration object.
     *
     * @return array Array of sort settings keyed by facet
     */
    protected function getHierarchicalFacetSortSettings($facetConfig)
    {
        $baseConfig
            = isset($facetConfig->SpecialFacets->hierarchicalFacetSortOptions)
            ? $facetConfig->SpecialFacets->hierarchicalFacetSortOptions->toArray()
            : [];
        $homepageConfig
            = isset($facetConfig->HomePage_Settings->hierarchicalFacetSortOptions)
            ? $facetConfig->HomePage_Settings->hierarchicalFacetSortOptions
                ->toArray()
            : [];

        return array_merge($baseConfig, $homepageConfig);
    }

    /**
     * Store the configuration of the content block.
     *
     * @param string $settings Settings from searches.ini.
     *
     * @return void
     */
    public function setConfig($settings)
    {
        $parts = explode(':', $settings);
        $this->searchClassId = empty($parts[0]) ? $this->searchClassId : $parts[0];
        $this->columnSize = $parts[1] ?? $this->columnSize;
    }

    /**
     * Return context variables used for rendering the block's template.
     *
     * @return array
     */
    public function getContext()
    {
        $facetCache = $this->facetCacheManager->get($this->searchClassId);
        $results = $facetCache->getResults();
        $facetConfig = $this->configManager
            ->get($results->getOptions()->getFacetsIni());
        return [
            'searchClassId' => $this->searchClassId,
            'columnSize' => $this->columnSize,
            'facetList' => $facetCache->getList('HomePage'),
            'hierarchicalFacets' => $this->getHierarchicalFacets($facetConfig),
            'hierarchicalFacetSortOptions' =>
                $this->getHierarchicalFacetSortSettings($facetConfig),
            'results' => $results,
        ];
    }
}
