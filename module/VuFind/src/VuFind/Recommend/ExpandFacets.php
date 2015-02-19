<?php
/**
 * ExpandFacets Module Controller
 *
 * PHP Version 5
 *
 * Copyright (C) Villanova University 2011.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.    See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA    02111-1307    USA
 *
 * @category VuFind2
 * @package  Recommendations
 * @author   Mark Triggs <vufind-tech@lists.sourceforge.net>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
namespace VuFind\Recommend;

/**
 * Recommendation class to expand recommendation interfaces
 *
 * @category VuFind2
 * @package  Recommendations
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class ExpandFacets implements RecommendInterface
{
    /**
     * Facets to display
     *
     * @var array
     */
    protected $facets;

    /**
     * Settings from configuration
     *
     * @var string
     */
    protected $settings;

    /**
     * Search results
     *
     * @var \VuFind\Search\Base\Results
     */
    protected $searchObject;

    /**
     * Configuration loader
     *
     * @var \VuFind\Config\PluginManager
     */
    protected $configLoader;

    /**
     * Empty result set (used by the template as the basis for URL generation)
     *
     * @var \VuFind\Search\Solr\Results
     */
    protected $emptyResults;

    /**
     * Constructor
     *
     * @param \VuFind\Config\PluginManager $configLoader Configuration loader
     * @param \VuFind\Search\Solr\Results  $emptyResults Empty result set (used
     * by the template as the basis for URL generation)
     */
    public function __construct(\VuFind\Config\PluginManager $configLoader,
        \VuFind\Search\Solr\Results $emptyResults
    ) {
        $this->configLoader = $configLoader;
        $this->emptyResults = $emptyResults;
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
        // Save the basic parameters:
        $this->settings = $settings;

        // Parse the additional settings:
        $settings = explode(':', $settings);
        $mainSection = empty($settings[0]) ? 'Results' : $settings[0];
        $iniName = isset($settings[1]) ? $settings[1] : 'facets';

        // Load the desired facet information...
        $config = $this->configLoader->get($iniName);

        // All standard facets to display:
        $this->facets = isset($config->$mainSection) ?
            $config->$mainSection->toArray() : [];
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
        // Turn on side facets in the search results:
        foreach ($this->facets as $name => $desc) {
            $params->addFacet($name, $desc);
        }
    }

    /**
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
        $this->searchObject = $results;
    }

    /**
     * Get the facet data
     *
     * @return array
     */
    public function getExpandedSet()
    {
        return $this->searchObject->getFacetList($this->facets);
    }

    /**
     * Get an empty search object (the template uses this as the basis for URL
     * generation).
     *
     * @return \VuFind\Search\Base\Results
     */
    public function getEmptyResults()
    {
        return $this->emptyResults;
    }
}
