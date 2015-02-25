<?php
/**
 * SideFacets Recommendations Module
 *
 * PHP version 5
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Recommendations
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:recommendation_modules Wiki
 */
namespace VuFind\Recommend;

/**
 * SideFacets Recommendations Module
 *
 * This class provides recommendations displaying facets beside search results
 *
 * @category VuFind2
 * @package  Recommendations
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:recommendation_modules Wiki
 */
class TopFacets extends AbstractFacets
{
    /**
     * Facet configuration
     *
     * @var array
     */
    protected $facets;

    /**
     * Basic configurations
     *
     * @var array
     */
    protected $baseSettings;

    /**
     * Store the configuration of the recommendation module.
     *
     * TopFacets:[ini section]:[ini name]
     *      Display facets listed in the specified section of the specified ini file;
     *      if [ini name] is left out, it defaults to "facets."
     *
     * @param string $settings Settings from searches.ini.
     *
     * @return void
     */
    public function setConfig($settings)
    {
        $settings = explode(':', $settings);
        $mainSection = empty($settings[0]) ? 'ResultsTop' : $settings[0];
        $iniName = isset($settings[1]) ? $settings[1] : 'facets';

        // Load the desired facet information:
        $config = $this->configLoader->get($iniName);
        $this->facets = isset($config->$mainSection)
            ? $config->$mainSection->toArray() : [];

        // Load other relevant settings:
        $this->baseSettings = [
            'rows' => $config->Results_Settings->top_rows,
            'cols' => $config->Results_Settings->top_cols
        ];

        // Load boolean configurations:
        $this->loadBooleanConfigs($config, array_keys($this->facets));
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
        // Turn on top facets in the search results:
        foreach ($this->facets as $name => $desc) {
            $params->addFacet($name, $desc, in_array($name, $this->orFacets));
        }
    }

    /**
     * Get facet information taken from the search.
     *
     * @return array
     */
    public function getTopFacetSet()
    {
        return $this->results->getFacetList($this->facets);
    }

    /**
     * Get configuration settings related to top facets.
     *
     * @return array
     */
    public function getTopFacetSettings()
    {
        return $this->baseSettings;
    }
}