<?php

/**
 * SideFacets Recommendations Module
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2015.
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
 * @package  Recommendations
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:recommendation_modules Wiki
 */

namespace Finna\Recommend;

use VuFind\I18n\Translator\TranslatorAwareInterface;
use VuFind\I18n\Translator\TranslatorAwareTrait;

use function in_array;

/**
 * SideFacets Recommendations Module
 *
 * This class provides recommendations displaying facets beside search results
 *
 * @category VuFind
 * @package  Recommendations
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:recommendation_modules Wiki
 */
class SideFacets extends \VuFind\Recommend\SideFacets implements TranslatorAwareInterface
{
    use TranslatorAwareTrait;
    use SideFacetsTrait;

    /**
     * Display the map under region facet
     *
     * @var array
     */
    protected $geographicFacet = [
        'map_selection' => false,
    ];

    /**
     * Constructor
     *
     * @param \VuFind\Config\configManagerInterface        $configManager   Configuration loader
     * @param \Finna\Search\Solr\AuthorityHelper           $authorityHelper Authority helper
     * @param ?\VuFind\Search\Solr\HierarchicalFacetHelper $facetHelper     Helper for handling hierarchical facets
     */
    public function __construct(
        \VuFind\Config\configManagerInterface $configManager,
        protected \Finna\Search\Solr\AuthorityHelper $authorityHelper,
        ?\VuFind\Search\Solr\HierarchicalFacetHelper $facetHelper = null
    ) {
        parent::__construct($configManager, $facetHelper);
    }

    /**
     * Store the configuration of the recommendation module.
     *
     * @param string $settings Settings from searches.ini.
     *
     * @return void
     */
    public function setConfig($settings)
    {
        parent::setConfig($settings);

        // Parse the additional settings:
        $settings = explode(':', $settings);
        $iniName = $settings[2] ?? 'facets';

        // Load the desired facet information...
        $config = $this->configManager->getConfigArray($iniName);

        // New items facets
        if (null !== ($facets = $config['SpecialFacets']['newItems'] ?? null)) {
            $this->newItemsFacets = $facets;
        }

        // Fallback check for older style of enabling the map in facets
        if (null !== ($finnaGeographic = $config['SpecialFacets']['finna_geographic'] ?? null)) {
            $this->geographicFacet['map_selection']
                = in_array('geographic_facet:location_geo', $finnaGeographic);
        }

        if (null !== ($mapSelection = $config->Geographical->map_selection ?? null)) {
            $this->geographicFacet['map_selection'] = (bool)$mapSelection;
        }
    }

    /**
     * Called before the Search Results object performs its main search
     * (specifically, in response to \VuFind\Search\SearchRunner::EVENT_CONFIGURED).
     * This method is responsible for setting search parameters needed by the
     * recommendation module and for reading any existing search parameters that may
     * be needed.
     *
     * @param \VuFind\Search\Base\Params $params  Search parameter object
     * @param \Laminas\Stdlib\Parameters $request Parameter object representing
     * user request.
     *
     * @return void
     */
    public function init($params, $request)
    {
        // If facets are listed in $params, enable only them
        $facets = null !== $request ? $request->get('enabledFacets') : [];
        if (!empty($facets)) {
            $filterFunc = function ($key) use ($facets) {
                return in_array($key, $facets);
            };

            $this->mainFacets = array_filter(
                $this->mainFacets,
                $filterFunc,
                ARRAY_FILTER_USE_KEY
            );
            $this->checkboxFacets = array_filter(
                $this->checkboxFacets,
                $filterFunc,
                ARRAY_FILTER_USE_KEY
            );
        }
        parent::init($params, $request);
    }

    /**
     * Returns the geographic map facet array.
     *
     * @return array
     */
    public function getGeographicFacet()
    {
        return $this->geographicFacet;
    }

    /**
     * Get facet information from the search results.
     *
     * @return array
     * @throws \Exception
     */
    public function getFacetSet()
    {
        $facetSet = parent::getFacetSet();
        if ($this->authorityHelper) {
            $facetSet = $this->authorityHelper->formatFacetSet($facetSet);
        }
        return $facetSet;
    }
}
