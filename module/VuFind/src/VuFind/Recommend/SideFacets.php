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
class SideFacets extends AbstractFacets
{
    /**
     * Date facet configuration
     *
     * @var array
     */
    protected $dateFacets = array();

    /**
     * Generic range facet configuration
     *
     * @var array
     */
    protected $genericRangeFacets = array();

    /**
     * Numeric range facet configuration
     *
     * @var array
     */
    protected $numericRangeFacets = array();

    /**
     * Main facet configuration
     *
     * @var array
     */
    protected $mainFacets = array();

    /**
     * Checkbox facet configuration
     *
     * @var array
     */
    protected $checkboxFacets = array();

    /**
     * Collapsed facet setting
     *
     * @var bool|string
     */
    protected $collapsedFacets = false;

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

        // Load boolean configurations:
        $this->loadBooleanConfigs($config, array_keys($this->mainFacets));

        // Get a list of fields that should be displayed as ranges rather than
        // standard facet lists.
        if (isset($config->SpecialFacets->dateRange)) {
            $this->dateFacets = $config->SpecialFacets->dateRange->toArray();
        }
        if (isset($config->SpecialFacets->genericRange)) {
            $this->genericRangeFacets
                = $config->SpecialFacets->genericRange->toArray();
        }
        if (isset($config->SpecialFacets->numericRange)) {
            $this->numericRangeFacets
                = $config->SpecialFacets->numericRange->toArray();
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

        // Collapsed facets:
        if (isset($config->Results_Settings->collapsedFacets)) {
            $this->collapsedFacets = $config->Results_Settings->collapsedFacets;
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
        return $this->getRangeFacets('dateFacets');
    }

    /**
     * getGenericRangeFacets
     *
     * Return generic range facet information in a format processed for use in the
     * view.
     *
     * @return array Array of from/to value arrays keyed by field.
     */
    public function getGenericRangeFacets()
    {
        return $this->getRangeFacets('genericRangeFacets');
    }

    /**
     * getNumericRangeFacets
     *
     * Return numeric range facet information in a format processed for use in the
     * view.
     *
     * @return array Array of from/to value arrays keyed by field.
     */
    public function getNumericRangeFacets()
    {
        return $this->getRangeFacets('numericRangeFacets');
    }

    /**
     * Get combined range details.
     *
     * @return array
     */
    public function getAllRangeFacets()
    {
        $raw = array(
            'date' => $this->getDateFacets(),
            'generic' => $this->getGenericRangeFacets(),
            'numeric' => $this->getNumericRangeFacets()
        );
        $processed = array();
        foreach ($raw as $type => $values) {
            foreach ($values as $field => $range) {
                $processed[$field] = array('type' => $type, 'values' => $range);
            }
        }
        return $processed;
    }

    /**
     * Return the list of facets configured to be collapsed
     *
     * @return array
     */
    public function getCollapsedFacets()
    {
        if (empty($this->collapsedFacets)) {
            return array();
        } elseif ($this->collapsedFacets == '*') {
            return array_keys($this->getFacetSet());
        }
        return array_map('trim', explode(',', $this->collapsedFacets));
    }

    /**
     * Get the list of filters to display
     *
     * @param array $extraFilters Extra filters to add to the list.
     *
     * @return array
     */
    public function getVisibleFilters($extraFilters = array())
    {
        // Merge extras into main list:
        $filterList = array_merge(
            $this->results->getParams()->getFilterList(true), $extraFilters
        );

        // Filter out suppressed values:
        $final = array();
        foreach ($filterList as $field => $filters) {
            $current = array();
            foreach ($filters as $i => $filter) {
                if (!isset($filter['suppressDisplay'])
                    || !$filter['suppressDisplay']
                ) {
                    $current[] = $filter;
                }
            }
            if (!empty($current)) {
                $final[$field] = $current;
            }
        }

        return $final;
    }

    /**
     * getRangeFacets
     *
     * Return range facet information in a format processed for use in the view.
     *
     * @param string $property Name of property containing active range facets
     *
     * @return array Array of from/to value arrays keyed by field.
     */
    protected function getRangeFacets($property)
    {
        $filters = $this->results->getParams()->getFilters();
        $result = array();
        if (isset($this->$property) && is_array($this->$property)) {
            foreach ($this->$property as $current) {
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
        }
        return $result;
    }
}
