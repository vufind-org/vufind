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
use Zend\Config\Config;

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
abstract class AbstractFacets implements RecommendInterface
{
    /**
     * Facets with "exclude" links enabled
     *
     * @var array
     */
    protected $excludableFacets = [];

    /**
     * Facets that are "ORed" instead of "ANDed."
     *
     * @var array
     */
    protected $orFacets = [];

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
     * Get the facet boolean operator
     *
     * @param string $field Field name
     *
     * @return string 'AND' or 'OR'
     */
    public function getFacetOperator($field)
    {
        return in_array($field, $this->orFacets) ? 'OR' : 'AND';
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
     * Read boolean (OR/NOT) settings from the provided configuration
     *
     * @param Config $config    Configuration to read
     * @param array  $allFacets All facets (to use when config = *)
     * @param string $section   Configuration section containing settings
     *
     * @return void
     */
    protected function loadBooleanConfigs(Config $config, $allFacets,
        $section = 'Results_Settings'
    ) {
        // Which facets are excludable?
        if (isset($config->$section->exclude)) {
            $this->excludableFacets = ($config->$section->exclude === '*')
                ? $allFacets
                : array_map('trim', explode(',', $config->$section->exclude));
        }

        // Which facets are ORed?
        if (isset($config->Results_Settings->orFacets)) {
            $this->orFacets = ($config->$section->orFacets === '*')
                ? $allFacets
                : array_map('trim', explode(',', $config->$section->orFacets));
        }
    }
}
