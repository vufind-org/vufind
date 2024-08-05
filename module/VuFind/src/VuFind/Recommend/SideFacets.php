<?php

/**
 * SideFacets Recommendations Module
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Recommendations
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:recommendation_modules Wiki
 */

namespace VuFind\Recommend;

use VuFind\Search\Solr\HierarchicalFacetHelper;
use VuFind\Solr\Utils as SolrUtils;

use function get_class;
use function in_array;
use function intval;
use function is_array;

/**
 * SideFacets Recommendations Module
 *
 * This class provides recommendations displaying facets beside search results
 *
 * @category VuFind
 * @package  Recommendations
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:recommendation_modules Wiki
 */
class SideFacets extends AbstractFacets
{
    /**
     * Year-only date facet configuration
     *
     * @var array
     */
    protected $dateFacets = [];

    /**
     * Day/month/year date facet configuration
     *
     * @var array
     */
    protected $fullDateFacets = [];

    /**
     * Generic range facet configuration
     *
     * @var array
     */
    protected $genericRangeFacets = [];

    /**
     * Numeric range facet configuration
     *
     * @var array
     */
    protected $numericRangeFacets = [];

    /**
     * Main facet configuration
     *
     * @var array
     */
    protected $mainFacets = [];

    /**
     * Checkbox facet configuration
     *
     * @var array
     */
    protected $checkboxFacets = [];

    /**
     * Should we display dynamically-generated checkbox facets that are not
     * explicitly configured in $checkboxFacets?
     *
     * @var bool
     */
    protected $showDynamicCheckboxFacets = true;

    /**
     * Settings controlling how lightbox is used for facet display.
     *
     * @var bool|string
     */
    protected $showInLightboxSettings = [];

    /**
     * Settings controlling how many values to display before "show more."
     *
     * @var array
     */
    protected $showMoreSettings = [];

    /**
     * Collapsed facet setting
     *
     * @var bool|string
     */
    protected $collapsedFacets = false;

    /**
     * Hierarchical facet setting
     *
     * @var array
     */
    protected $hierarchicalFacets = [];

    /**
     * Hierarchical facet sort options
     *
     * @var array
     */
    protected $hierarchicalFacetSortOptions = [];

    /**
     * Hierarchical facet helper
     *
     * @var HierarchicalFacetHelper
     */
    protected $hierarchicalFacetHelper;

    /**
     * Constructor
     *
     * @param \VuFind\Config\PluginManager $configLoader Configuration loader
     * @param HierarchicalFacetHelper      $facetHelper  Helper for handling
     * hierarchical facets
     */
    public function __construct(
        \VuFind\Config\PluginManager $configLoader,
        HierarchicalFacetHelper $facetHelper = null
    ) {
        parent::__construct($configLoader);
        $this->hierarchicalFacetHelper = $facetHelper;
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
        // Parse the additional settings:
        $settings = explode(':', $settings);
        $mainSection = empty($settings[0]) ? 'Results' : $settings[0];
        $checkboxSection = $settings[1] ?? false;
        $iniName = $settings[2] ?? 'facets';
        $showDynamicCheckboxFacets = $settings[3] ?? true;

        // Load the desired facet information...
        $config = $this->configLoader->get($iniName);

        // All standard facets to display:
        $this->mainFacets = isset($config->$mainSection) ?
            $config->$mainSection->toArray() : [];

        // Load boolean configurations:
        $this->loadBooleanConfigs($config, array_keys($this->mainFacets));

        // Get a list of fields that should be displayed as ranges rather than
        // standard facet lists.
        if (isset($config->SpecialFacets->dateRange)) {
            $this->dateFacets = $config->SpecialFacets->dateRange->toArray();
        }
        if (isset($config->SpecialFacets->fullDateRange)) {
            $this->fullDateFacets = $config->SpecialFacets->fullDateRange->toArray();
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
        $flipCheckboxes = false;
        if (str_starts_with($checkboxSection, '~')) {
            $checkboxSection = substr($checkboxSection, 1);
            $flipCheckboxes = true;
        }
        $this->checkboxFacets
            = ($checkboxSection && isset($config->$checkboxSection))
            ? $config->$checkboxSection->toArray() : [];
        if ($flipCheckboxes) {
            $this->checkboxFacets = array_flip($this->checkboxFacets);
        }
        if (
            !$showDynamicCheckboxFacets
            || strtolower(trim($showDynamicCheckboxFacets)) === 'false'
        ) {
            $this->showDynamicCheckboxFacets = false;
        }

        // Show more settings:
        if (isset($config->Results_Settings->showMore)) {
            $this->showMoreSettings
                = $config->Results_Settings->showMore->toArray();
        }
        if (isset($config->Results_Settings->showMoreInLightbox)) {
            $this->showInLightboxSettings
                = $config->Results_Settings->showMoreInLightbox->toArray();
        }

        // Collapsed facets:
        if (isset($config->Results_Settings->collapsedFacets)) {
            $this->collapsedFacets = $config->Results_Settings->collapsedFacets;
        }

        // Hierarchical facets:
        if (isset($config->SpecialFacets->hierarchical)) {
            $this->hierarchicalFacets
                = $config->SpecialFacets->hierarchical->toArray();
        }

        // Hierarchical facet sort options:
        if (isset($config->SpecialFacets->hierarchicalFacetSortOptions)) {
            $this->hierarchicalFacetSortOptions
                = $config->SpecialFacets->hierarchicalFacetSortOptions->toArray();
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
     * @param \Laminas\Stdlib\Parameters $request Parameter object representing user
     * request.
     *
     * @return void
     */
    public function init($params, $request)
    {
        $mainFacets = $this->mainFacets;
        if ($request != null && ($enabledFacets = $request->get('enabledFacets', null)) !== null) {
            $mainFacets = array_intersect_key($mainFacets, array_flip($enabledFacets));
        }
        // Turn on side facets in the search results:
        foreach ($mainFacets as $name => $desc) {
            $params->addFacet($name, $desc, in_array($name, $this->orFacets));
        }
        foreach ($this->checkboxFacets as $name => $desc) {
            $params->addCheckboxFacet($name, $desc);
        }
    }

    /**
     * Get checkbox facet information from the search results.
     *
     * @return array
     */
    public function getCheckboxFacetSet()
    {
        return $this->results->getParams()->getCheckboxFacets(
            array_keys($this->checkboxFacets),
            $this->showDynamicCheckboxFacets
        );
    }

    /**
     * Get facet information from the search results.
     *
     * @return array
     * @throws \Exception
     */
    public function getFacetSet()
    {
        $facetSet = $this->results->getFacetList($this->mainFacets);

        foreach ($this->hierarchicalFacets as $hierarchicalFacet) {
            if (isset($facetSet[$hierarchicalFacet])) {
                if (!$this->hierarchicalFacetHelper) {
                    throw new \Exception(
                        get_class($this) . ': hierarchical facet helper unavailable'
                    );
                }

                $facetSet[$hierarchicalFacet]['list'] = $this->hierarchicalFacetHelper->filterFacets(
                    $hierarchicalFacet,
                    $facetSet[$hierarchicalFacet]['list'],
                    $this->results->getOptions()
                );
            }
        }

        return $facetSet;
    }

    /**
     * Return year-based date facet information in a format processed for use in the
     * view.
     *
     * @return array Array of from/to value arrays keyed by field.
     */
    public function getDateFacets()
    {
        return $this->getRangeFacets('dateFacets');
    }

    /**
     * Return year/month/day-based date facet information in a format processed for
     * use in the view.
     *
     * @return array Array of from/to value arrays keyed by field.
     */
    public function getFullDateFacets()
    {
        return $this->getRangeFacets('fullDateFacets');
    }

    /**
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
        $raw = [
            'date' => $this->getDateFacets(),
            'fulldate' => $this->getFullDateFacets(),
            'generic' => $this->getGenericRangeFacets(),
            'numeric' => $this->getNumericRangeFacets(),
        ];
        $processed = [];
        foreach ($raw as $type => $values) {
            foreach ($values as $field => $range) {
                $processed[$field] = ['type' => $type, 'values' => $range];
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
            return [];
        } elseif ($this->collapsedFacets == '*') {
            return array_keys($this->mainFacets);
        }
        return array_map('trim', explode(',', $this->collapsedFacets));
    }

    /**
     * Return the list of facets configured to be collapsed
     * defaults to 6
     *
     * @param string $facetName Name of the facet to get
     * @param int    $default   Value to use if configuration is absent/invalid
     *
     * @return int
     */
    public function getShowMoreSetting($facetName, $default = 6)
    {
        // Look for either facet-specific configuration or else a configured
        // default. If neither is found, initialize return value to null.
        $val = null;
        if (isset($this->showMoreSettings[$facetName])) {
            $val = intval($this->showMoreSettings[$facetName]);
        } elseif (isset($this->showMoreSettings['*'])) {
            $val = intval($this->showMoreSettings['*']);
        }

        // Validate the return value, using default if missing/invalid
        return (isset($val) && $val > 0) ? $val : $default;
    }

    /**
     * Return settings for showing more results in the lightbox
     *
     * @param string $facetName Name of the facet to get
     *
     * @return int
     */
    public function getShowInLightboxSetting($facetName)
    {
        // Look for either facet-specific configuration or else a configured
        // default.
        if (isset($this->showInLightboxSettings[$facetName])) {
            return $this->showInLightboxSettings[$facetName];
        } elseif (isset($this->showInLightboxSettings['*'])) {
            return $this->showInLightboxSettings['*'];
        }

        // No config found; use default behavior:
        return 'more';
    }

    /**
     * Return range facet information in a format processed for use in the view.
     *
     * @param string $property Name of property containing active range facets
     *
     * @return array Array of from/to value arrays keyed by field.
     */
    protected function getRangeFacets($property)
    {
        $filters = $this->results->getParams()->getRawFilters();
        $result = [];
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
                $result[$current] = [$from, $to];
            }
        }
        return $result;
    }

    /**
     * Return the list of facets configured to be hierarchical
     *
     * @return array
     */
    public function getHierarchicalFacets()
    {
        return $this->hierarchicalFacets;
    }

    /**
     * Return the list of configured hierarchical facet sort options
     *
     * @return array
     */
    public function getHierarchicalFacetSortOptions()
    {
        return $this->hierarchicalFacetSortOptions;
    }
}
