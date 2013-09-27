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
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:recommendation_modules Wiki
 */
namespace VuFind\Recommend;
use VuFind\Solr\Utils as SolrUtils;

/**
 * SideFacets Recommendations Module
 *
 * This class provides recommendations displaying facets beside search results
 *
 * @category VuFind2
 * @package  Recommendations
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:recommendation_modules Wiki
 */
class SideFacets implements RecommendInterface
{
    /**
     * Date facet configuration
     *
     * @var array
     */
    protected $dateFacets = array();

    /**
     * Main facet configuration
     *
     * @var array
     */
    protected $mainFacets = array();

    /**
     * Facets with "exclude" links enabled
     *
     * @var array
     */
    protected $excludableFacets = array();

    /**
     * Facets that are "ORed" instead of "ANDed."
     *
     * @var array
     */
    protected $orFacets = array();

    /**
     * Checkbox facet configuration
     *
     * @var array
     */
    protected $checkboxFacets = array();

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
     * @param string $settings Settings from searches.ini.
     *
     * @return void
     */
    public function setConfig($settings)
    {
        // Parse the additional settings:
        $settings = explode(':', $settings);
        $mainSection = empty($settings[0]) ? 'Results' : $settings[0];
        $checkboxSection = isset($settings[1]) ? $settings[1] : false;
        $iniName = isset($settings[2]) ? $settings[2] : 'facets';

        // Load the desired facet information...
        $config = $this->configLoader->get($iniName);

        // All standard facets to display:
        $this->mainFacets = isset($config->$mainSection) ?
            $config->$mainSection->toArray() : array();

        // Which facets are excludable?
        if (isset($config->Results_Settings->exclude)) {
            if ($config->Results_Settings->exclude === '*') {
                $this->excludableFacets = array_keys($this->mainFacets);
            } else {
                $this->excludableFacets = array_map(
                    'trim', explode(',', $config->Results_Settings->exclude)
                );
            }
        }

        // Which facets are ORed?
        if (isset($config->Results_Settings->orFacets)) {
            if ($config->Results_Settings->orFacets === '*') {
                $this->orFacets = array_keys($this->mainFacets);
            } else {
                $this->orFacets = array_map(
                    'trim', explode(',', $config->Results_Settings->orFacets)
                );
            }
        }

        // Get a list of fields that should be displayed as date ranges rather than
        // standard facet lists.
        if (isset($config->SpecialFacets->dateRange)) {
            $this->dateFacets = $config->SpecialFacets->dateRange->toArray();
        }

        // Checkbox facets:
        if (substr($checkboxSection, 0, 1) == '~') {
            $checkboxSection = substr($checkboxSection, 1);
            $flipCheckboxes = true;
        }
        $this->checkboxFacets
            = ($checkboxSection && isset($config->$checkboxSection))
            ? $config->$checkboxSection->toArray() : array();
        if (isset($flipCheckboxes) && $flipCheckboxes) {
            $this->checkboxFacets = array_flip($this->checkboxFacets);
        }
    }

    /**
     * init
     *
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
        foreach ($this->mainFacets as $name => $desc) {
            $params->addFacet($name, $desc, in_array($name, $this->orFacets));
        }
        foreach ($this->checkboxFacets as $name => $desc) {
            $params->addCheckboxFacet($name, $desc);
        }
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
     * getFacetSet
     *
     * Get facet information from the search results.
     *
     * @return array
     */
    public function getFacetSet()
    {
        return $this->results->getFacetList($this->mainFacets);
    }

    /**
     * getDateFacets
     *
     * Return date facet information in a format processed for use in the view.
     *
     * @return array Array of from/to value arrays keyed by field.
     */
    public function getDateFacets()
    {
        $filters = $this->results->getParams()->getFilters();
        $result = array();
        foreach ($this->dateFacets as $current) {
            $from = $to = '';
            if (isset($filters[$current])) {
                foreach ($filters[$current] as $filter) {
                    if ($range = SolrUtils::parseRange($filter)) {
                        $from = $range['from'] == '*' ? '' : $range['from'];
                        $to = $range['to'] == '*' ? '' : $range['to'];
                        break;
                    }
                }
            }
            $result[$current] = array($from, $to);
        }
        return $result;
    }

    /**
     * Is the specified field allowed to be excluded?
     *
     * @param string $field Field name
     *
     * @return bool
     */
    public function excludeAllowed($field)
    {
        return in_array($field, $this->excludableFacets);
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
}
