<?php
/**
 * Solr aspect of the Search Multi-class (Params)
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2011.
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
 * @package  Search_Solr
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
namespace VuFind\Search\Solr;
use VuFindSearch\ParamBag;

/**
 * Solr Search Parameters
 *
 * @category VuFind2
 * @package  Search_Solr
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class Params extends \VuFind\Search\Base\Params
{
    /**
     * Facet result limit
     *
     * @var int
     */
    protected $facetLimit = 30;

    /**
     * Offset for facet results
     *
     * @var int
     */
    protected $facetOffset = null;

    /**
     * Prefix for facet searching
     *
     * @var string
     */
    protected $facetPrefix = null;

    /**
     * Sorting order for facet search results
     *
     * @var string
     */
    protected $facetSort = null;

    /**
     * Constructor
     *
     * @param \VuFind\Search\Base\Options  $options      Options to use
     * @param \VuFind\Config\PluginManager $configLoader Config loader
     */
    public function __construct($options, \VuFind\Config\PluginManager $configLoader)
    {
        parent::__construct($options, $configLoader);

        // Use basic facet limit by default, if set:
        $config = $configLoader->get('facets');
        if (isset($config->Results_Settings->facet_limit)
            && is_numeric($config->Results_Settings->facet_limit)
        ) {
            $this->setFacetLimit($config->Results_Settings->facet_limit);
        }
    }

    /**
     * Return the current filters as an array of strings ['field:filter']
     *
     * @return array $filterQuery
     */
    public function getFilterSettings()
    {
        // Define Filter Query
        $filterQuery = $this->getOptions()->getHiddenFilters();
        $orFilters = array();
        foreach ($this->filterList as $field => $filter) {
            if ($orFacet = (substr($field, 0, 1) == '~')) {
                $field = substr($field, 1);
            }
            foreach ($filter as $value) {
                // Special case -- allow trailing wildcards and ranges:
                if (substr($value, -1) == '*'
                    || preg_match('/\[[^\]]+\s+TO\s+[^\]]+\]/', $value)
                ) {
                    $q = $field.':'.$value;
                } else {
                    $q = $field.':"'.addcslashes($value, '"\\').'"';
                }
                if ($orFacet) {
                    $orFilters[$field] = isset($orFilters[$field])
                        ? $orFilters[$field] : array();
                    $orFilters[$field][] = $q;
                } else {
                    $filterQuery[] = $q;
                }
            }
        }
        foreach ($orFilters as $field => $parts) {
            $filterQuery[] = '{!tag=' . $field . '_filter}' . $field
                . ':(' . implode(' OR ', $parts) . ')';
        }
        return $filterQuery;
    }

    /**
     * Return current facet configurations
     *
     * @return array $facetSet
     */
    public function getFacetSettings()
    {
        // Build a list of facets we want from the index
        $facetSet = array();
        if (!empty($this->facetConfig)) {
            $facetSet['limit'] = $this->facetLimit;
            foreach ($this->facetConfig as $facetField => $facetName) {
                if ($this->getFacetOperator($facetField) == 'OR') {
                    $facetField = '{!ex=' . $facetField . '_filter}' . $facetField;
                }
                $facetSet['field'][] = $facetField;
            }
            if ($this->facetOffset != null) {
                $facetSet['offset'] = $this->facetOffset;
            }
            if ($this->facetPrefix != null) {
                $facetSet['prefix'] = $this->facetPrefix;
            }
            if ($this->facetSort != null) {
                $facetSet['sort'] = $this->facetSort;
            } else {
                // No explicit setting? Set one based on the documented Solr behavior
                // (index order for limit = -1, count order for limit > 0)
                // Later Solr versions may have different defaults than earlier ones,
                // so making this explicit ensures consistent behavior.
                $facetSet['sort'] = ($this->facetLimit > 0) ? 'count' : 'index';
            }
        }
        return $facetSet;
    }

    /**
     * Initialize the object's search settings from a request object.
     *
     * @param \Zend\StdLib\Parameters $request Parameter object representing user
     * request.
     *
     * @return void
     */
    protected function initSearch($request)
    {
        // Special case -- did we get a list of IDs instead of a standard query?
        $ids = $request->get('overrideIds', null);
        if (is_array($ids)) {
            $this->setQueryIDs($ids);
        } else {
            // Use standard initialization:
            parent::initSearch($request);
        }
    }

    /**
     * Set Facet Limit
     *
     * @param int $l the new limit value
     *
     * @return void
     */
    public function setFacetLimit($l)
    {
        $this->facetLimit = $l;
    }

    /**
     * Set Facet Offset
     *
     * @param int $o the new offset value
     *
     * @return void
     */
    public function setFacetOffset($o)
    {
        $this->facetOffset = $o;
    }

    /**
     * Set Facet Prefix
     *
     * @param string $p the new prefix value
     *
     * @return void
     */
    public function setFacetPrefix($p)
    {
        $this->facetPrefix = $p;
    }

    /**
     * Set Facet Sorting
     *
     * @param string $s the new sorting action value
     *
     * @return void
     */
    public function setFacetSort($s)
    {
        $this->facetSort = $s;
    }

    /**
     * Initialize facet settings for the specified configuration sections.
     *
     * @param string $facetList     Config section containing fields to activate
     * @param string $facetSettings Config section containing related settings
     *
     * @return bool                 True if facets set, false if no settings found
     */
    protected function initFacetList($facetList, $facetSettings)
    {
        $config = $this->getServiceLocator()->get('VuFind\Config')->get('facets');
        if (!isset($config->$facetList)) {
            return false;
        }
        if (isset($config->$facetSettings->orFacets)) {
            $orFields
                = array_map('trim', explode(',', $config->$facetSettings->orFacets));
        } else {
            $orFields = array();
        }
        foreach ($config->$facetList as $key => $value) {
            $useOr = (isset($orFields[0]) && $orFields[0] == '*')
                || in_array($key, $orFields);
            $this->addFacet($key, $value, $useOr);
        }
        if (isset($config->$facetSettings->facet_limit)
            && is_numeric($config->$facetSettings->facet_limit)
        ) {
            $this->setFacetLimit($config->$facetSettings->facet_limit);
        }
        return true;
    }

    /**
     * Initialize facet settings for the advanced search screen.
     *
     * @return void
     */
    public function initAdvancedFacets()
    {
        $this->initFacetList('Advanced', 'Advanced_Settings');
    }

    /**
     * Initialize facet settings for the home page.
     *
     * @return void
     */
    public function initHomePageFacets()
    {
        // Load Advanced settings if HomePage settings are missing (legacy support):
        if (!$this->initFacetList('HomePage', 'HomePage_Settings')) {
            $this->initAdvancedFacets();
        }
    }

    /**
     * Initialize facet settings for the standard search screen.
     *
     * @return void
     */
    public function initBasicFacets()
    {
        $this->initFacetList('ResultsTop', 'Results_Settings');
        $this->initFacetList('Results', 'Results_Settings');
    }

    /**
     * Load all available facet settings.  This is mainly useful for showing
     * appropriate labels when an existing search has multiple filters associated
     * with it.
     *
     * @param string $preferredSection Section to favor when loading settings; if
     * multiple sections contain the same facet, this section's description will
     * be favored.
     *
     * @return void
     */
    public function activateAllFacets($preferredSection = false)
    {
        // Based on preference, change the order of initialization to make sure
        // that preferred facet labels come in last.
        if ($preferredSection == 'Advanced') {
            $this->initHomePageFacets();
            $this->initBasicFacets();
            $this->initAdvancedFacets();
        } else {
            $this->initHomePageFacets();
            $this->initAdvancedFacets();
            $this->initBasicFacets();
        }
    }

    /**
     * Add filters to the object based on values found in the request object.
     *
     * @param \Zend\StdLib\Parameters $request Parameter object representing user
     * request.
     *
     * @return void
     */
    protected function initFilters($request)
    {
        // Use the default behavior of the parent class, but add support for the
        // special illustrations filter.
        parent::initFilters($request);
        switch ($request->get('illustration', -1)) {
        case 1:
            $this->addFilter('illustrated:Illustrated');
            break;
        case 0:
            $this->addFilter('illustrated:"Not Illustrated"');
            break;
        }

        // Check for hidden filters:
        $hidden = $request->get('hiddenFilters');
        if (!empty($hidden) && is_array($hidden)) {
            foreach ($hidden as $current) {
                $this->getOptions()->addHiddenFilter($current);
            }
        }
    }

    /**
     * Override the normal search behavior with an explicit array of IDs that must
     * be retrieved.
     *
     * @param array $ids Record IDs to load
     *
     * @return void
     */
    public function setQueryIDs($ids)
    {
        // No need for spell checking or highlighting on an ID query!
        $this->getOptions()->spellcheckEnabled(false);
        $this->getOptions()->disableHighlighting();

        // Special case -- no IDs to set:
        if (empty($ids)) {
            return $this->setOverrideQuery('NOT *:*');
        }

        $callback = function ($i) {
            return '"' . addcslashes($i, '"') . '"';
        };
        $ids = array_map($callback, $ids);
        $this->setOverrideQuery('id:(' . implode(' OR ', $ids) . ')');
    }

    /**
     * Get the maximum number of IDs that may be sent to setQueryIDs (-1 for no
     * limit).
     *
     * @return int
     */
    public function getQueryIDLimit()
    {
        $config = $this->getServiceLocator()->get('VuFind\Config')->get('config');
        return isset($config->Index->maxBooleanClauses)
            ? $config->Index->maxBooleanClauses : 1024;
    }

    /**
     * Normalize sort parameters.
     *
     * @param string $sort Sort parameter
     *
     * @return string
     */
    protected function normalizeSort($sort)
    {
        static $table = array(
            'year' => array('field' => 'publishDateSort', 'order' => 'desc'),
            'publishDateSort' =>
                array('field' => 'publishDateSort', 'order' => 'desc'),
            'author' => array('field' => 'authorStr', 'order' => 'asc'),
            'title' => array('field' => 'title_sort', 'order' => 'asc'),
            'relevance' => array('field' => 'score', 'order' => 'desc'),
            'callnumber' => array('field' => 'callnumber', 'order' => 'asc'),
        );
        $normalized = array();
        foreach (explode(',', $sort) as $component) {
            $parts = explode(' ', trim($component));
            $field = reset($parts);
            $order = next($parts);
            if (isset($table[$field])) {
                $normalized[] = sprintf(
                    '%s %s',
                    $table[$field]['field'],
                    $order ?: $table[$field]['order']
                );
            } else {
                $normalized[] = sprintf(
                    '%s %s',
                    $field,
                    $order ?: 'asc'
                );
            }
        }
        return implode(',', $normalized);
    }

    /**
     * Create search backend parameters for advanced features.
     *
     * @return ParamBag
     */
    public function getBackendParameters()
    {
        $backendParams = new ParamBag();

        // Spellcheck
        $backendParams->set(
            'spellcheck', $this->getOptions()->spellcheckEnabled() ? 'true' : 'false'
        );

        // Facets
        $facets = $this->getFacetSettings();
        if (!empty($facets)) {
            $backendParams->add('facet', 'true');
            foreach ($facets as $key => $value) {
                $backendParams->add("facet.{$key}", $value);
            }
            $backendParams->add('facet.mincount', 1);
        }

        // Filters
        $filters = $this->getFilterSettings();
        foreach ($filters as $filter) {
            $backendParams->add('fq', $filter);
        }

        // Shards
        $allShards = $this->getOptions()->getShards();
        $shards = $this->getSelectedShards();
        if (is_null($shards)) {
            $shards = array_keys($allShards);
        }

        // If we have selected shards, we need to format them:
        if (!empty($shards)) {
            $selectedShards = array();
            foreach ($shards as $current) {
                $selectedShards[$current] = $allShards[$current];
            }
            $shards = $selectedShards;
            $backendParams->add('shards', implode(',', $selectedShards));
        }

        // Sort
        $sort = $this->getSort();
        if ($sort) {
            $backendParams->add('sort', $this->normalizeSort($sort));
        }

        // Highlighting -- on by default, but we should disable if necessary:
        if (!$this->getOptions()->highlightEnabled()) {
            $backendParams->add('hl', 'false');
        }

        return $backendParams;
    }

    /**
     * Format a single filter for use in getFilterList().
     *
     * @param string $field     Field name
     * @param string $value     Field value
     * @param string $operator  Operator (AND/OR/NOT)
     * @param bool   $translate Should we translate the label?
     *
     * @return array
     */
    protected function formatFilterListEntry($field, $value, $operator, $translate)
    {
        $filter = parent::formatFilterListEntry(
            $field, $value, $operator, $translate
        );

        // Convert range queries to a language-non-specific format:
        if (preg_match('/^\[(.*) TO (.*)\]$/', $value, $matches)) {
            $filter['displayText'] = $matches[1] . '-' . $matches[2];
        }

        return $filter;
    }
}