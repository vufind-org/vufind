<?php
/**
 * VisualFacets Recommendations Module
 *
 * PHP version 5
 *
 * Copyright (C) Julia Bauder 2014.
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
 * @author   Julia Bauder <bauderj@grinnell.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:recommendation_modules Wiki
 */
namespace VuFind\Recommend;
use VuFind\Solr\Utils as SolrUtils;

/**
 * VisualFacets Recommendations Module
 *
 * This class supports visualizing pivot facet information as a treemap or circle packing visualization. 
 * It must be used in combination with a template file including the necessary Javascript in order to display the visualization to the user.
 *
 * @category VuFind2
 * @package  Recommendations
 * @author   Julia Bauder <bauderj@grinnell.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:recommendation_modules Wiki
 */
class VisualFacets implements \VuFind\Recommend\RecommendInterface
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
     * Search results
     *
     * @var \VuFind\Search\Base\Results
     */
    protected $results;

    /**
     * Configuration loader
     *
     * @var \VuFind\Config\PluginManager
     */
    protected $configLoader;

    /**
     * Constructor
     *
     * @param \VuFind\Config\PluginManager $configLoader Configuration loader
     */
    public function __construct(\VuFind\Config\PluginManager $configLoader)
    {
        $this->configLoader = $configLoader;
    }

    /**
     * setConfig
     *
     * Store the configuration of the recommendation module.
     *
     * Currently not implemented, since there is no configuration to store; 
     * the visualization is hard-coded to use callnumber-first and topic_facet. 
     * If and when the choice of which facets to visualize becomes a configurable 
     * option, this will need to be added.
     *
     * @return void
     */
    public function setConfig($settings)
    {

    }

    /**
     * init
     *
     * @param \VuFind\Search\Base\Params $params  Search parameter object
     * @param \Zend\StdLib\Parameters    $request Parameter object representing user
     * request.
     *
     * @return void
     */
    public function init($params, $request)
    {

    }

    /**
     * process
     *
     * Called after the Search Results object has performed its main search.  This
     * may be used to extract necessary information from the Search Results object
     * or to perform completely unrelated processing.
     *
     * @param \VuFind\Search\Base\Results $results Search results object
     *
     * @return void
     */
    public function process($results)
    {
        $this->results = $results;
    }

    /**
     * Get results stored in the object.
     *
     * @return \VuFind\Search\Base\Results
     */
    public function getResults()
    {
        return $this->results;
    }

    /**
     * Get facet information taken from the search.
     *
     * @return array
     */
    public function getVisualFacetSet()
    {
        return $this->results->getVisualFacetList();
    }

    /**
     * Get configuration settings related to visual facets.
     *
     * @return array
     */
    public function getVisualFacetSettings()
    {
        return $this->baseSettings;
    }
}
