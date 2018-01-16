<?php
/**
 * SideFacets Recommendations Module
 *
 * PHP version 5
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Recommendations
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:recommendation_modules Wiki
 */
namespace Finna\Recommend;

use VuFind\I18n\Translator\TranslatorAwareInterface;
use VuFind\I18n\Translator\TranslatorAwareTrait;

/**
 * SideFacets Recommendations Module
 *
 * This class provides recommendations displaying facets beside search results
 *
 * @category VuFind
 * @package  Recommendations
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:recommendation_modules Wiki
 */
class SideFacets extends \VuFind\Recommend\SideFacets
    implements TranslatorAwareInterface
{
    use TranslatorAwareTrait;
    use SideFacetsTrait;

    /**
     * Geographic facet setting
     *
     * @var array
     */
    protected $geographicFacets = [];

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
        $iniName = isset($settings[2]) ? $settings[2] : 'facets';

        // Load the desired facet information...
        $config = $this->configLoader->get($iniName);

        // New items facets
        if (isset($config->SpecialFacets->newItems)) {
            $this->newItemsFacets = $config->SpecialFacets->newItems->toArray();
        }
        if (isset($config->SpecialFacets->finna_geographic)) {
            $this->geographicFacets
                = $config->SpecialFacets->finna_geographic->toArray();
        }
    }

    /**
     * Called at the end of the Search Params objects' initFromRequest() method.
     * This method is responsible for setting search parameters needed by the
     * recommendation module and for reading any existing search parameters that may
     * be needed.
     *
     * @param \VuFind\Search\Base\Params $params  Search parameter object
     * @param \Zend\StdLib\Parameters    $request Parameter object representing user
     * request.
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
        return parent::init($params, $request);
    }

    /**
     * Remove unlisted facets from main facets
     *
     * @param array $facets Facets to keep
     *
     * @return void
     */
    public function filterMainFacets($facets)
    {
        $this->mainFacets = array_filter(
            $this->mainFacets,
            function ($value, $key) {
                return in_array($key, $facets);
            }
        );
    }

    /**
     * Get an array of geographic facets.
     *
     * @return array
     */
    public function getGeographicFacets()
    {
        return array_map(
            function ($geo_facet) {
                return explode(':', $geo_facet)[0];
            }, $this->geographicFacets
        );
    }
}
