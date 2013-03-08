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

use VuFindSearch\Query\Query;
use VuFind\Search\Legacy\QueryAdapter;

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
     */
    protected $facetLimit = 30;
    /**
     * Offset for facet results
     */
    protected $facetOffset = null;
    /**
     * Prefix for facet searching
     */
    protected $facetPrefix = null;
    /**
     * Sorting order for facet search results
     */
    protected $facetSort = null;

    /**
     * Override Query
     */
    protected $overrideQuery = false;

    /**
     * Perform initialization that cannot occur in constructor due to need for
     * injected dependencies.
     *
     * @return void
     */
    public function init()
    {
        parent::init();

        // Use basic facet limit by default, if set:
        $config = $this->getServiceLocator()->get('VuFind\Config')->get('facets');
        if (isset($config->Results_Settings->facet_limit)
            && is_numeric($config->Results_Settings->facet_limit)
        ) {
            $this->setFacetLimit($config->Results_Settings->facet_limit);
        }
    }

    /**
     * Set the override query
     *
     * @param string $q Override query
     *
     * @return void
     */
    public function setOverrideQuery($q)
    {
        $this->overrideQuery = $q;
    }

    /**
     * Get the override query
     *
     * @return string
     */
    public function getOverrideQuery()
    {
        return $this->overrideQuery;
    }

    /**
     * Return search query object.
     *
     * @return VuFindSearch\Query\AbstractQuery
     *
     * @tag NEW SEARCH
     */
    public function getQuery ()
    {
        if ($this->overrideQuery) {
            return new Query($this->overrideQuery);
        }

        $legacy = $this->getSearchTerms();
        if (empty($legacy)) {
            return new Query();
        }
        return QueryAdapter::create($legacy);
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
        foreach ($this->filterList as $field => $filter) {
            foreach ($filter as $value) {
                // Special case -- allow trailing wildcards and ranges:
                if (substr($value, -1) == '*'
                    || preg_match('/\[[^\]]+\s+TO\s+[^\]]+\]/', $value)
                ) {
                    $filterQuery[] = $field.':'.$value;
                } else {
                    $filterQuery[] = $field.':"'.$value.'"';
                }
            }
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

            // Another special case -- are we doing a tag search?
            $tag = $request->get('tag', '');
            if (!empty($tag)) {
                $this->setBasicSearch($tag, 'tag');
            }
            if ($this->getSearchHandler() == 'tag') {
                $this->initTagSearch();
            }
        }
    }

    /**
     * Restore settings from a minified object found in the database.
     *
     * @param \VuFind\Search\Minified $minified Minified Search Object
     *
     * @return void
     */
    public function deminify($minified)
    {
        parent::deminify($minified);

        // Special case: deminified tag searches need some extra help:
        if ('tag' == $this->getSearchHandler()) {
            $this->initTagSearch();
        }
    }

    /**
     * Special case -- set up a tag-based search.
     *
     * @return void
     */
    protected function initTagSearch()
    {
        $table = $this->getTable('Tags');
        $tag = $table->getByText($this->getDisplayQuery());
        if (!empty($tag)) {
            $rawResults = $tag->getResources('VuFind');
        } else {
            $rawResults = array();
        }
        $ids = array();
        $max = $this->getQueryIDLimit();
        $count = 0;
        foreach ($rawResults as $current) {
            $ids[] = $current->record_id;
            // If we have too many hits for Solr to handle, quit now:
            if (++$count == $max) {
                break;
            }
        }
        $this->setQueryIDs($ids);
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
        foreach ($config->$facetList as $key => $value) {
            $this->addFacet($key, $value);
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
        $config = $this->getServiceLocator()->get('VuFind\Config')->get('facets');
        if (isset($config->ResultsTop)) {
            foreach ($config->ResultsTop as $key => $value) {
                $this->addFacet($key, $value);
            }
        }
        if (isset($config->Results)) {
            foreach ($config->Results as $key => $value) {
                $this->addFacet($key, $value);
            }
        }
    }

    /**
     * Adapt the search query to a spelling query
     *
     * @return string Spelling query
     */
    protected function buildSpellingQuery()
    {
        if ($this->searchType == 'advanced') {
            return $this->extractAdvancedTerms();
        }
        return $this->getDisplayQuery();
    }

    /**
     * Get Spelling Query
     *
     * @return string
     */
    public function getSpellingQuery()
    {
        // Build our spellcheck query
        if ($this->getOptions()->spellcheckEnabled()) {
            if ($this->getOptions()->usesSimpleSpelling()) {
                $this->getOptions()->useBasicDictionary();
            }
            $spellcheck = $this->buildSpellingQuery();

            // If the spellcheck query is purely numeric, skip it if
            // the appropriate setting is turned on.
            if ($this->getOptions()->shouldSkipNumericSpelling()
                && is_numeric($spellcheck)
            ) {
                return '';
            }
            return $spellcheck;
        }
        return '';
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
        // No need for spell checking on an ID query!
        $this->getOptions()->spellcheckEnabled(false);

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
}