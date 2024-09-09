<?php

/**
 * Solr aspect of the Search Multi-class (Params)
 *
 * PHP version 8
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Search_Solr
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Search\Solr;

use VuFindSearch\ParamBag;

use function count;
use function in_array;
use function is_array;
use function sprintf;

/**
 * Solr Search Parameters
 *
 * @category VuFind
 * @package  Search_Solr
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class Params extends \VuFind\Search\Base\Params
{
    use \VuFind\Search\Params\FacetLimitTrait;
    use \VuFind\Search\Params\FacetRestrictionsTrait;

    /**
     * Search with facet.contains
     * cf. https://lucene.apache.org/solr/guide/7_3/faceting.html
     *
     * @var string
     */
    protected $facetContains = null;

    /**
     * Ignore Case when using facet.contains
     * cf. https://lucene.apache.org/solr/guide/7_3/faceting.html
     *
     * @var bool
     */
    protected $facetContainsIgnoreCase = null;

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
     * Sorting order of single facet by index
     *
     * @var array
     */
    protected $indexSortedFacets = null;

    /**
     * Fields for visual faceting
     *
     * @var string
     */
    protected $pivotFacets = null;

    /**
     * Hierarchical Facet Helper
     *
     * @var HierarchicalFacetHelper
     */
    protected $facetHelper;

    /**
     * Are we searching by ID only (instead of a normal query)?
     *
     * @var bool
     */
    protected $searchingById = false;

    /**
     * Config sections to search for facet labels if no override configuration
     * is set.
     *
     * @var array
     */
    protected $defaultFacetLabelSections
        = ['Advanced', 'HomePage', 'ResultsTop', 'Results', 'ExtraFacetLabels'];

    /**
     * Config sections to search for checkbox facet labels if no override
     * configuration is set.
     *
     * @var array
     */
    protected $defaultFacetLabelCheckboxSections = ['CheckboxFacets'];

    /**
     * Constructor
     *
     * @param \VuFind\Search\Base\Options  $options      Options to use
     * @param \VuFind\Config\PluginManager $configLoader Config loader
     * @param HierarchicalFacetHelper      $facetHelper  Hierarchical facet helper
     */
    public function __construct(
        $options,
        \VuFind\Config\PluginManager $configLoader,
        HierarchicalFacetHelper $facetHelper = null
    ) {
        parent::__construct($options, $configLoader);
        $this->facetHelper = $facetHelper;

        // Use basic facet limit by default, if set:
        $config = $configLoader->get($options->getFacetsIni());
        $this->initFacetLimitsFromConfig($config->Results_Settings ?? null);
        $this->initFacetRestrictionsFromConfig($config->Results_Settings ?? null);
        if (isset($config->LegacyFields)) {
            $this->facetAliases = $config->LegacyFields->toArray();
        }
        if (
            isset($config->Results_Settings->sorted_by_index)
            && count($config->Results_Settings->sorted_by_index) > 0
        ) {
            $this->setIndexSortedFacets(
                $config->Results_Settings->sorted_by_index->toArray()
            );
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
        $filterQuery = [];
        $orFilters = [];
        $filterList = array_merge_recursive(
            $this->getHiddenFilters(),
            $this->filterList
        );
        foreach ($filterList as $field => $filter) {
            if ($orFacet = str_starts_with($field, '~')) {
                $field = substr($field, 1);
            }
            foreach ($filter as $value) {
                // Special case -- complex filter, that should be taken as-is:
                if ($field == '#') {
                    $q = $value;
                } elseif (
                    str_ends_with($value, '*')
                    || preg_match('/\[[^\]]+\s+TO\s+[^\]]+\]/', $value)
                ) {
                    // Special case -- allow trailing wildcards and ranges
                    $q = $field . ':' . $value;
                } else {
                    $q = $field . ':"' . addcslashes($value, '"\\') . '"';
                }
                if ($orFacet) {
                    $orFilters[$field] ??= [];
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
        $facetSet = [];

        if (!empty($this->facetConfig)) {
            $facetSet['limit'] = $this->facetLimit;
            foreach (array_keys($this->facetConfig) as $facetField) {
                $fieldLimit = $this->getFacetLimitForField($facetField);
                if ($fieldLimit != $this->facetLimit) {
                    $facetSet["f.{$facetField}.facet.limit"] = $fieldLimit;
                }
                $fieldPrefix = $this->getFacetPrefixForField($facetField);
                if (!empty($fieldPrefix)) {
                    $facetSet["f.{$facetField}.facet.prefix"] = $fieldPrefix;
                }
                $fieldMatches = $this->getFacetMatchesForField($facetField);
                if (!empty($fieldMatches)) {
                    $facetSet["f.{$facetField}.facet.matches"] = $fieldMatches;
                }
                if ($this->getFacetOperator($facetField) == 'OR') {
                    $facetField = '{!ex=' . $facetField . '_filter}' . $facetField;
                }
                $facetSet['field'][] = $facetField;
            }
            if ($this->facetContains != null) {
                $facetSet['contains'] = $this->facetContains;
            }
            if ($this->facetContainsIgnoreCase != null) {
                $facetSet['contains.ignoreCase']
                    = $this->facetContainsIgnoreCase ? 'true' : 'false';
            }
            if ($this->facetOffset != null) {
                $facetSet['offset'] = $this->facetOffset;
            }
            if ($this->facetPrefix != null) {
                $facetSet['prefix'] = $this->facetPrefix;
            }
            $facetSet['sort'] = $this->facetSort ?: 'count';
            if ($this->indexSortedFacets != null) {
                foreach ($this->indexSortedFacets as $field) {
                    $facetSet["f.{$field}.facet.sort"] = 'index';
                }
            }
        }
        return $facetSet;
    }

    /**
     * Initialize the object's search settings from a request object.
     *
     * @param \Laminas\Stdlib\Parameters $request Parameter object representing user
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
     * Set Facet Contains
     *
     * @param string $p the new contains value
     *
     * @return void
     */
    public function setFacetContains($p)
    {
        $this->facetContains = $p;
    }

    /**
     * Set Facet Contains Ignore Case
     *
     * @param bool $val the new boolean value
     *
     * @return void
     */
    public function setFacetContainsIgnoreCase($val)
    {
        $this->facetContainsIgnoreCase = $val;
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
     * Set Index Facet Sorting
     *
     * @param array $s the facets sorted by index
     *
     * @return void
     */
    public function setIndexSortedFacets(array $s)
    {
        $this->indexSortedFacets = $s;
    }

    /**
     * Initialize facet settings for the specified configuration sections.
     *
     * @param string $facetList     Config section containing fields to activate
     * @param string $facetSettings Config section containing related settings
     * @param string $cfgFile       Name of configuration to load (null to load
     * default facets configuration).
     *
     * @return bool                 True if facets set, false if no settings found
     */
    protected function initFacetList($facetList, $facetSettings, $cfgFile = null)
    {
        $config = $this->configLoader
            ->get($cfgFile ?? $this->getOptions()->getFacetsIni());
        $this->initFacetLimitsFromConfig($config->$facetSettings ?? null);
        return parent::initFacetList($facetList, $facetSettings, $cfgFile);
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
     * Initialize facet settings for the new items page.
     *
     * @return void
     */
    public function initNewItemsFacets()
    {
        // Load Advanced settings if NewItems settings are missing (fallback to defaults):
        if (!$this->initFacetList('NewItems', 'NewItems_Settings')) {
            $this->initAdvancedFacets();
        }
    }

    /**
     * Add filters to the object based on values found in the request object.
     *
     * @param \Laminas\Stdlib\Parameters $request Parameter object representing user
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
            $this->setOverrideQuery('NOT *:*');
            return;
        }

        $callback = function ($i) {
            return '"' . addcslashes($i, '"') . '"';
        };
        $ids = array_map($callback, $ids);
        $this->searchingById = true;
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
        $config = $this->configLoader->get($this->getOptions()->getMainIni());
        return $config->Index->maxBooleanClauses ?? 1024;
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
        static $table = [
            'year' => ['field' => 'publishDateSort', 'order' => 'desc'],
            'publishDateSort' => ['field' => 'publishDateSort', 'order' => 'desc'],
            'author' => ['field' => 'author_sort', 'order' => 'asc'],
            'authorStr' => ['field' => 'author_sort', 'order' => 'asc'],
            'title' => ['field' => 'title_sort', 'order' => 'asc'],
            'relevance' => ['field' => 'score', 'order' => 'desc'],
            'callnumber' => ['field' => 'callnumber-sort', 'order' => 'asc'],
        ];
        $tieBreaker = $this->getOptions()->getSortTieBreaker();
        if ($tieBreaker) {
            $sort .= ',' . $tieBreaker;
        }

        $normalized = [];
        $fields = [];
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
                $fields[] = $field;
            } else {
                if (!in_array($field, $fields)) {
                    $normalized[] = sprintf(
                        '%s %s',
                        $field,
                        $order ?: 'asc'
                    );
                    $fields[] = $field;
                }
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
            'spellcheck',
            $this->getOptions()->spellcheckEnabled() ? 'true' : 'false'
        );

        // Facets
        $facets = $this->getFacetSettings();
        if (!empty($facets)) {
            $backendParams->add('facet', 'true');

            foreach ($facets as $key => $value) {
                // prefix keys with "facet" unless they already have a "f." prefix:
                $fullKey = str_starts_with($key, 'f.') ? $key : "facet.$key";
                $backendParams->add($fullKey, $value);
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
        if (empty($shards)) {
            $shards = array_keys($allShards);
        }

        // If we have selected shards, we need to format them:
        if (!empty($shards)) {
            $selectedShards = [];
            foreach ($shards as $current) {
                $selectedShards[$current] = $allShards[$current];
            }
            $backendParams->add('shards', implode(',', $selectedShards));
        }

        // Sort
        $sort = $this->getSort();
        if ($sort) {
            // If we have an empty search with relevance sort as the primary sort
            // field, see if there is an override configured:
            $sortFields = explode(',', $sort);
            $allTerms = trim($this->getQuery()->getAllTerms() ?? '');
            if (
                'relevance' === $sortFields[0]
                && ('' === $allTerms || '*:*' === $allTerms || $this->searchingById)
                && ($relOv = $this->getOptions()->getEmptySearchRelevanceOverride())
            ) {
                $sort = $relOv;
            }
            $backendParams->add('sort', $this->normalizeSort($sort));
        }

        // Highlighting -- on by default, but we should disable if necessary:
        if (!$this->getOptions()->highlightEnabled()) {
            $backendParams->add('hl', 'false');
        }

        // Pivot facets for visual results

        if ($pf = $this->getPivotFacets()) {
            $backendParams->add('facet.pivot', $pf);
            $backendParams->set('facet', 'true');
        }

        return $backendParams;
    }

    /**
     * Set pivot facet fields to use for visual results
     *
     * @param string $facets A comma-separated list of fields
     *
     * @return void
     */
    public function setPivotFacets($facets)
    {
        $this->pivotFacets = $facets;
    }

    /**
     * Get pivot facet information for visual facets
     *
     * @return string
     */
    public function getPivotFacets()
    {
        return $this->pivotFacets;
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
            $field,
            $value,
            $operator,
            $translate
        );

        $hierarchicalFacets = $this->getOptions()->getHierarchicalFacets();
        $hierarchicalFacetSeparators
            = $this->getOptions()->getHierarchicalFacetSeparators();
        // Convert range queries to a language-non-specific format:
        $caseInsensitiveRegex = '/^\(\[(.*) TO (.*)\] OR \[(.*) TO (.*)\]\)$/';
        if (preg_match('/^\[(.*) TO (.*)\]$/', $value, $matches)) {
            // Simple case: [X TO Y]
            $filter['displayText'] = $matches[1] . ' - ' . $matches[2];
        } elseif (preg_match($caseInsensitiveRegex, $value, $matches)) {
            // Case insensitive case: [x TO y] OR [X TO Y]; convert
            // only if values in both ranges match up!
            if (
                strtolower($matches[3]) == strtolower($matches[1])
                && strtolower($matches[4]) == strtolower($matches[2])
            ) {
                $filter['displayText'] = $matches[1] . ' - ' . $matches[2];
            }
        } elseif ($this->facetHelper && in_array($field, $hierarchicalFacets)) {
            // Display hierarchical facet levels nicely
            $separator = $hierarchicalFacetSeparators[$field] ?? '/';
            if (!$translate) {
                $filter['displayText'] = $this->facetHelper->formatDisplayText(
                    $filter['displayText'],
                    true,
                    $separator
                )->getDisplayString();
            } else {
                $domain = $this->getOptions()
                    ->getTextDomainForTranslatedFacet($field);

                // Provide translation of each separate element as a default
                // while allowing one to translate the full string too:
                $parts = $this->facetHelper
                    ->getFilterStringParts($filter['value']);
                $translated = [];
                foreach ($parts as $part) {
                    $translated[] = $this->translate([$domain, $part]);
                }
                $translatedParts = implode($separator, $translated);

                $parts = array_map(
                    function ($part) {
                        return $part->getDisplayString();
                    },
                    $parts
                );
                $str = implode($separator, $parts);
                $filter['displayText']
                    = $this->translate([$domain, $str], [], $translatedParts);
            }
        }

        return $filter;
    }

    /**
     * Get information on the current state of the boolean checkbox facets.
     *
     * @param array $include        List of checkbox filters to return (null for all)
     * @param bool  $includeDynamic Should we include dynamically-generated
     * checkboxes that are not part of the include list above?
     *
     * @return array
     */
    public function getCheckboxFacets(
        array $include = null,
        bool $includeDynamic = true
    ) {
        // Grab checkbox facet details using the standard method:
        $facets = parent::getCheckboxFacets($include, $includeDynamic);

        $config = $this->configLoader->get($this->getOptions()->getFacetsIni());
        $filterField = $config->CustomFilters->custom_filter_field ?? 'vufind';

        // Special case -- inverted checkbox facets should always appear, even on
        // the "no results" screen, since setting them actually EXPANDS rather than
        // reduces the result set.
        foreach ($facets as $i => $facet) {
            // Append colon on end to ensure that $customFilter is always set.
            [$field, $customFilter] = explode(':', $facet['filter'] . ':');
            if (
                $field == $filterField
                && isset($config->CustomFilters->inverted_filters[$customFilter])
            ) {
                $facets[$i]['alwaysVisible'] = true;
            }
        }

        // Return modified list:
        return $facets;
    }
}
