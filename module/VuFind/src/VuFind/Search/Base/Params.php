<?php

/**
 * Abstract parameters search model.
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
 * @package  Search_Base
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Search\Base;

use VuFind\I18n\TranslatableString;
use VuFind\Search\Minified;
use VuFind\Search\QueryAdapter;
use VuFind\Search\QueryAdapterInterface;
use VuFind\Solr\Utils as SolrUtils;
use VuFindSearch\Backend\Solr\LuceneSyntaxHelper;
use VuFindSearch\Query\AbstractQuery;
use VuFindSearch\Query\Query;
use VuFindSearch\Query\QueryGroup;

use function call_user_func;
use function count;
use function get_class;
use function in_array;
use function intval;
use function is_array;
use function is_callable;
use function is_object;
use function strlen;

/**
 * Abstract parameters search model.
 *
 * This abstract class defines the parameters methods for modeling a search in VuFind
 *
 * @category VuFind
 * @package  Search_Base
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class Params
{
    /**
     * Internal representation of user query.
     *
     * @var Query
     */
    protected $query;

    /**
     * Page number
     *
     * @var int
     */
    protected $page = 1;

    /**
     * Sort setting
     *
     * @var string
     */
    protected $sort = null;

    /**
     * Override special RSS sort feature?
     *
     * @var bool
     */
    protected $skipRssSort = false;

    /**
     * Result limit
     *
     * @var int
     */
    protected $limit = 20;

    /**
     * Search type (basic or advanced)
     *
     * @var string
     */
    protected $searchType  = 'basic';

    /**
     * Shards
     *
     * @var array
     */
    protected $selectedShards = [];

    /**
     * View
     *
     * @var string
     */
    protected $view = null;

    /**
     * Previously-used view (loaded in from session)
     *
     * @var string
     */
    protected $lastView = null;

    /**
     * Search options
     *
     * @var Options
     */
    protected $options;

    /**
     * Main facet configuration
     *
     * @var array
     */
    protected $facetConfig = [];

    /**
     * Extra facet labels
     *
     * @var array
     */
    protected $extraFacetLabels = [];

    /**
     * Config sections to search for facet labels if no override configuration
     * is set.
     *
     * @var array
     */
    protected $defaultFacetLabelSections = ['ExtraFacetLabels'];

    /**
     * Config sections to search for checkbox facet labels if no override
     * configuration is set.
     *
     * @var array
     */
    protected $defaultFacetLabelCheckboxSections = [];

    /**
     * Checkbox facet configuration
     *
     * @var array
     */
    protected $checkboxFacets = [];

    /**
     * Applied filters
     *
     * @var array
     */
    protected $filterList = [];

    /**
     * Pre-assigned filters
     *
     * @var array
     */
    protected $hiddenFilters = [];

    /**
     * Facets in "OR" mode
     *
     * @var array
     */
    protected $orFacets = [];

    /**
     * Override Query
     */
    protected $overrideQuery = false;

    /**
     * Are default filters applied?
     *
     * @var bool
     */
    protected $defaultsApplied = false;

    /**
     * Map of facet field aliases.
     *
     * @var array
     */
    protected $facetAliases = [];

    /**
     * Search context parameters.
     *
     * @var array
     */
    protected $searchContextParameters = [];

    /**
     * Config loader
     *
     * @var \VuFind\Config\PluginManager
     */
    protected $configLoader;

    /**
     * Query adapter
     *
     * @var ?QueryAdapterInterface
     */
    protected $queryAdapter = null;

    /**
     * Default query adapter class
     *
     * @var string
     */
    protected $queryAdapterClass = QueryAdapter::class;

    /**
     * Is this a specialized search (i.e. a customized scenario like new items,
     * rather than a "normal" backend search)?
     *
     * @var bool
     */
    protected $isSpecializedSearch = false;

    /**
     * Constructor
     *
     * @param \VuFind\Search\Base\Options  $options      Options to use
     * @param \VuFind\Config\PluginManager $configLoader Config loader
     */
    public function __construct($options, \VuFind\Config\PluginManager $configLoader)
    {
        $this->setOptions($options);

        $this->configLoader = $configLoader;

        // Make sure we have some sort of query object:
        $this->query = new Query();

        // Set up facet label settings, to be used as fallbacks if specific facets
        // are not already configured:
        $config = $configLoader->get($options->getFacetsIni());
        $sections = $config->FacetLabels->labelSections
            ?? $this->defaultFacetLabelSections;
        foreach ($sections as $section) {
            foreach ($config->$section ?? [] as $field => $label) {
                $this->extraFacetLabels[$field] = $label;
            }
        }

        // Activate all relevant checkboxes, also important for labeling:
        $checkboxSections = $config->FacetLabels->checkboxSections
            ?? $this->defaultFacetLabelCheckboxSections;
        foreach ($checkboxSections as $checkboxSection) {
            $this->initCheckboxFacets($checkboxSection);
        }
    }

    /**
     * Get the search options object.
     *
     * @return \VuFind\Search\Base\Options
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * Set the search options object.
     *
     * @param \VuFind\Search\Base\Options $options Options to use
     *
     * @return void
     */
    public function setOptions(Options $options)
    {
        $this->options = $options;
    }

    /**
     * Get query adapter
     *
     * @return QueryAdapterInterface
     */
    public function getQueryAdapter(): QueryAdapterInterface
    {
        if (null === $this->queryAdapter) {
            $this->queryAdapter = new ($this->queryAdapterClass)();
        }
        return $this->queryAdapter;
    }

    /**
     * Set query adapter
     *
     * @param QueryAdapterInterface $queryAdapter Query adapter
     *
     * @return void
     */
    public function setQueryAdapter(QueryAdapterInterface $queryAdapter)
    {
        $this->queryAdapter = $queryAdapter;
    }

    /**
     * Copy constructor
     *
     * @return void
     */
    public function __clone()
    {
        if (is_object($this->options)) {
            $this->options = clone $this->options;
        }
        if (is_object($this->query)) {
            $this->query = clone $this->query;
        }
    }

    /**
     * Get the identifier used for naming the various search classes in this family.
     *
     * @return string
     */
    public function getSearchClassId()
    {
        return $this->getOptions()->getSearchClassId();
    }

    /**
     * Pull the search parameters
     *
     * @param \Laminas\Stdlib\Parameters $request Parameter object representing user
     * request.
     *
     * @return void
     */
    public function initFromRequest($request)
    {
        // We should init view first, since RSS view may cause certain variant
        // behaviors:
        $this->initView($request);
        $this->initLimit($request);
        $this->initPage($request);
        $this->initShards($request);
        // We have to initialize sort after search, since the search options may
        // affect the default sort option.
        $this->initSearch($request);
        $this->initSort($request);
        $this->initFilters($request);
        $this->initHiddenFilters($request);
        $this->isSpecializedSearch = $request->get('specializedSearch', false);
    }

    /**
     * Pull shard parameters from the request or set defaults
     *
     * @param \Laminas\Stdlib\Parameters $request Parameter object representing user
     * request.
     *
     * @return void
     */
    protected function initShards($request)
    {
        $legalShards = array_keys($this->getOptions()->getShards());
        $requestShards = $request->get('shard', []);
        if (!is_array($requestShards)) {
            $requestShards = [$requestShards];
        }

        // If a shard selection list is found as an incoming parameter,
        // we should save valid values for future reference:
        foreach ($requestShards as $current) {
            if (in_array($current, $legalShards)) {
                $this->selectedShards[] = $current;
            }
        }

        // If we got this far and still have no selections established, revert to
        // defaults:
        if (empty($this->selectedShards)) {
            $this->selectedShards = $this->getOptions()->getDefaultSelectedShards();
        }
    }

    /**
     * Pull the page size parameter or set to default
     *
     * @param \Laminas\Stdlib\Parameters $request Parameter object representing user
     * request.
     *
     * @return void
     */
    protected function initLimit($request)
    {
        // Check for a limit parameter in the url.
        $defaultLimit = $this->getOptions()->getDefaultLimit();
        if (($limit = intval($request->get('limit'))) != $defaultLimit) {
            // make sure the url parameter is a valid limit -- either
            // one of the explicitly allowed values, or at least smaller
            // than the largest allowed. (This leniency is useful in
            // combination with combined search, where it is often useful
            // to reduce the size of result lists without actually enabling
            // the user's ability to select a reduced list size).
            $legalOptions = $this->getOptions()->getLimitOptions();
            if (
                in_array($limit, $legalOptions)
                || ($limit > 0 && $limit < max($legalOptions))
            ) {
                $this->limit = $limit;
                return;
            }
        }

        // Increase default limit for RSS mode:
        if ($this->getView() == 'rss' && $defaultLimit < 50) {
            $defaultLimit = 50;
        }

        // If we got this far, setting was missing or invalid; load the default
        $this->limit = $defaultLimit;
    }

    /**
     * Pull the page parameter
     *
     * @param \Laminas\Stdlib\Parameters $request Parameter object representing user
     * request.
     *
     * @return void
     */
    protected function initPage($request)
    {
        $this->page = intval($request->get('page'));
        if ($this->page < 1) {
            $this->page = 1;
        }
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
        // Try to initialize a basic search; if that fails, try for an advanced
        // search next!
        if (!$this->initBasicSearch($request)) {
            $this->initAdvancedSearch($request);
        }
    }

    /**
     * Support method for initSearch() -- handle basic settings.
     *
     * @param \Laminas\Stdlib\Parameters $request Parameter object representing user
     * request.
     *
     * @return bool True if search settings were found, false if not.
     */
    protected function initBasicSearch($request)
    {
        // If no lookfor parameter was found, we have no search terms to
        // add to our array!
        if (null === ($lookfor = $request->get('lookfor'))) {
            return false;
        }

        // If lookfor is an array, we may be dealing with a legacy Advanced
        // Search URL. If there's only one parameter, we can flatten it,
        // but otherwise we should treat it as an error -- no point in going
        // to great lengths for compatibility.
        if (is_array($lookfor)) {
            if (count($lookfor) > 1) {
                throw new \Exception('Unsupported search URL.');
            }
            $lookfor = $lookfor[0];
        }

        // Flatten type arrays for backward compatibility:
        $handler = $request->get('type');
        if (is_array($handler)) {
            $handler = $handler[0];
        }

        // Set the search:
        $this->setBasicSearch($lookfor, $handler);
        return true;
    }

    /**
     * Set a basic search query:
     *
     * @param string $lookfor The search query
     * @param string $handler The search handler (null for default)
     *
     * @return void
     */
    public function setBasicSearch($lookfor, $handler = null)
    {
        $this->searchType = 'basic';

        if (empty($handler)) {
            $handler = $this->getOptions()->getDefaultHandler();
        }

        $this->query = new Query($lookfor, $handler);
    }

    /**
     * Convert a basic query into an advanced query:
     *
     * @return void
     */
    public function convertToAdvancedSearch()
    {
        if ($this->searchType === 'basic') {
            $this->query = new QueryGroup(
                'AND',
                [new QueryGroup('AND', [$this->query])]
            );
            $this->searchType = 'advanced';
        }
        if ($this->searchType !== 'advanced') {
            throw new \Exception(
                'Unsupported search type: ' . $this->searchType
            );
        }
    }

    /**
     * Support method for initSearch() -- handle advanced settings. Advanced
     * searches have numeric subscripts on the lookfor and type parameters --
     * this is how they are distinguished from basic searches.
     *
     * @param \Laminas\Stdlib\Parameters $request Parameter object representing user
     * request.
     *
     * @return void
     */
    protected function initAdvancedSearch($request)
    {
        $this->query = $this->getQueryAdapter()->fromRequest(
            $request,
            $this->getOptions()->getDefaultHandler()
        );

        $this->searchType = $this->query instanceof Query ? 'basic' : 'advanced';

        // If we ended up with a basic search, it's probably the result of
        // submitting an empty form, and more processing may be needed:
        if ($this->searchType == 'basic') {
            // Set a default handler if necessary:
            if ($this->query->getHandler() === null) {
                $this->query->setHandler($this->getOptions()->getDefaultHandler());
            }
            // If the user submitted the advanced search form, we want to treat
            // the search as advanced even if it evaluated to a basic search.
            if ($request->offsetExists('lookfor0')) {
                $this->convertToAdvancedSearch();
            }
        }
    }

    /**
     * Get the value for which type of sorting to use
     *
     * @param \Laminas\Stdlib\Parameters $request Parameter object representing user
     * request.
     *
     * @return void
     */
    protected function initSort($request)
    {
        // Check for special parameter only relevant in RSS mode:
        if ($request->get('skip_rss_sort', 'unset') != 'unset') {
            $this->skipRssSort = true;
        }
        $this->setSort($request->get('sort'));
    }

    /**
     * Set the last value of the view parameter (if available in session).
     *
     * @param string $view Last valid view parameter value
     *
     * @return void
     */
    public function setLastView($view)
    {
        $this->lastView = $view;
    }

    /**
     * Get the value for which results view to use
     *
     * @param \Laminas\Stdlib\Parameters $request Parameter object representing user
     * request.
     *
     * @return void
     */
    protected function initView($request)
    {
        // Check for a view parameter in the url.
        $view = $request->get('view');
        $validViews = array_keys($this->getOptions()->getViewOptions());
        if ($view == 'rss') {
            // RSS is a special case that does not require config validation
            $this->setView('rss');
        } elseif (!empty($view) && in_array($view, $validViews)) {
            // make sure the url parameter is a valid view
            $this->setView($view);
        } elseif (
            !empty($this->lastView)
            && in_array($this->lastView, $validViews)
        ) {
            // if there is nothing in the URL, see if we had a previous value
            // injected based on session information.
            $this->setView($this->lastView);
        } else {
            // otherwise load the default
            $this->setView($this->getOptions()->getDefaultView());
        }
    }

    /**
     * Return the default sorting value
     *
     * @return string
     */
    public function getDefaultSort()
    {
        return $this->getOptions()
            ->getDefaultSortByHandler($this->getSearchHandler());
    }

    /**
     * Return the current limit value
     *
     * @return int
     */
    public function getLimit()
    {
        return $this->limit;
    }

    /**
     * Change the value of the limit
     *
     * @param int $l New limit value.
     *
     * @return void
     */
    public function setLimit($l)
    {
        $this->limit = $l;
    }

    /**
     * Change the page
     *
     * @param int $p New page value.
     *
     * @return void
     */
    public function setPage($p)
    {
        $this->page = $p;
    }

    /**
     * Get the page value
     *
     * @return int
     */
    public function getPage()
    {
        return $this->page;
    }

    /**
     * Return the sorting value
     *
     * @return string
     */
    public function getSort()
    {
        return $this->sort;
    }

    /**
     * Set the sorting value (note: sort will be set to default if an illegal
     * or empty value is passed in).
     *
     * @param string $sort  New sort value (null for default)
     * @param bool   $force Set sort value without validating it?
     *
     * @return void
     */
    public function setSort($sort, $force = false)
    {
        // Skip validation if requested:
        if ($force) {
            $this->sort = $sort;
            return;
        }

        // Validate and assign the sort value:
        $valid = array_keys($this->getOptions()->getSortOptions());

        $matchedHiddenPatterns = array_filter(
            $this->getOptions()->getHiddenSortOptions(),
            function ($pattern) use ($sort) {
                return preg_match('/' . $pattern . '/', $sort);
            }
        );

        if (!empty($sort) && (in_array($sort, $valid) || count($matchedHiddenPatterns) > 0)) {
            $this->sort = $sort;
        } else {
            $this->sort = $this->getDefaultSort();
        }

        // In RSS mode, we may want to adjust sort settings:
        if (!$this->skipRssSort && $this->getView() == 'rss') {
            $this->sort = $this->getOptions()->getRssSort($this->sort);
        }
    }

    /**
     * Return the selected search handler (null for complex searches which have no
     * single handler)
     *
     * @return string|null
     */
    public function getSearchHandler()
    {
        // We can only definitively name a handler if we have a basic search:
        $q = $this->getQuery();
        return $q instanceof Query ? $q->getHandler() : null;
    }

    /**
     * Return the search type (i.e. basic or advanced)
     *
     * @return string
     */
    public function getSearchType()
    {
        return $this->searchType;
    }

    /**
     * Return the value for which search view we use
     *
     * @return string
     */
    public function getView()
    {
        return $this->view ?? $this->getOptions()->getDefaultView();
    }

    /**
     * Set the value for which search view we use
     *
     * @param String $v New view setting
     *
     * @return void
     */
    public function setView($v)
    {
        $this->view = $v;
    }

    /**
     * Build a string for onscreen display showing the
     *   query used in the search (not the filters).
     *
     * @return string user friendly version of 'query'
     */
    public function getDisplayQuery()
    {
        // Set up callbacks:
        $translate = [$this, 'translate'];
        $showField = [$this->getOptions(), 'getHumanReadableFieldName'];

        // Build display query:
        return $this->getQueryAdapter()->display($this->getQuery(), $translate, $showField);
    }

    /**
     * Parse apart the field and value from a URL filter string.
     *
     * @param string $filter A filter string from url : "field:value"
     *
     * @return array         Array with elements 0 = field, 1 = value.
     */
    public function parseFilter($filter)
    {
        // Special case: complex filters cannot be split into field/value
        // since they have multiple parts (e.g. field1:a OR field2:b). Use
        // a fake "#" field to collect these types of filters.
        if ($this->isAdvancedFilter($filter) == true) {
            return ['#', $filter];
        }

        // Split the string and assign the parts to $field and $value
        $temp = explode(':', $filter, 2);
        $field = array_shift($temp);
        $value = count($temp) > 0 ? $temp[0] : '';

        // Remove quotes from the value if there are any
        if (str_starts_with($value, '"')) {
            $value = substr($value, 1);
        }
        if (str_ends_with($value, '"')) {
            $value = substr($value, 0, -1);
        }
        // One last little clean on whitespace
        $value = trim($value);

        // Send back the results:
        return [$field, $value];
    }

    /**
     * Parse apart any prefix, field and value from a URL filter string.
     *
     * @param string $filter A filter string from url : "field:value"
     *
     * @return array         Array with elements 0 = prefix, 1 = field, 2 = value.
     */
    public function parseFilterAndPrefix($filter)
    {
        [$field, $value] = $this->parseFilter($filter);
        $prefix = substr($field, 0, 1);
        if (in_array($prefix, ['-', '~'])) {
            $field = substr($field, 1);
        } else {
            $prefix = '';
        }
        return [$prefix, $field, $value];
    }

    /**
     * Given a facet field, return an array containing all aliases of that
     * field.
     *
     * @param string $field Field to look up
     *
     * @return array
     */
    public function getAliasesForFacetField($field)
    {
        // Account for field prefixes used for Boolean logic:
        $prefix = substr($field, 0, 1);
        if ($prefix === '-' || $prefix === '~') {
            $rawField = substr($field, 1);
        } else {
            $prefix = '';
            $rawField = $field;
        }
        $fieldsToCheck = [$field];
        foreach ($this->facetAliases as $k => $v) {
            if ($v === $rawField) {
                $fieldsToCheck[] = $prefix . $k;
            }
        }
        return $fieldsToCheck;
    }

    /**
     * Does the object already contain the specified filter?
     *
     * @param string $filter A filter string from url : "field:value"
     *
     * @return bool
     */
    public function hasFilter($filter)
    {
        // Extract field and value from URL string:
        [$field, $value] = $this->parseFilter($filter);

        // Check all of the relevant fields for matches:
        foreach ($this->getAliasesForFacetField($field) as $current) {
            if (
                isset($this->filterList[$current])
                && in_array($value, $this->filterList[$current])
            ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Take a filter string and add it into the protected
     *   array checking for duplicates.
     *
     * @param string $newFilter A filter string from url : "field:value"
     *
     * @return void
     */
    public function addFilter($newFilter)
    {
        // Check for duplicates -- if it's not in the array, we can add it
        if (!$this->hasFilter($newFilter)) {
            // Extract field and value from filter string:
            [$field, $value] = $this->parseFilter($newFilter);
            $this->filterList[$field][] = $value;
        }
    }

    /**
     * Detects if a filter is advanced (true) or simple (false). An advanced
     * filter is currently defined as one surrounded by parentheses (possibly
     * with a leading exclusion operator), while a simple filter is of the form
     * field:value. Advanced filters are used to express more complex queries,
     * such as combining multiple values from multiple fields using boolean
     * operators.
     *
     * @param string $filter A filter string
     *
     * @return bool
     */
    public function isAdvancedFilter($filter)
    {
        return str_starts_with($filter, '(') || str_starts_with($filter, '-(');
    }

    /**
     * Remove a filter from the list.
     *
     * @param string $oldFilter A filter string from url : "field:value"
     *
     * @return void
     */
    public function removeFilter($oldFilter)
    {
        // Extract field and value from URL string:
        [$field, $value] = $this->parseFilter($oldFilter);

        // Make sure the field exists
        if (isset($this->filterList[$field])) {
            // Assume by default that we will not need to rebuild the array:
            $rebuildArray = false;

            // Loop through all filters on the field
            foreach ($this->filterList[$field] as $i => $currentFilter) {
                // Does it contain the value we don't want?
                if ($currentFilter == $value) {
                    // If so remove it.
                    unset($this->filterList[$field][$i]);

                    // Flag that we now need to rebuild the array:
                    $rebuildArray = true;
                }
            }

            // If necessary, rebuild the array to remove gaps in the key sequence:
            if ($rebuildArray) {
                $this->filterList[$field] = array_values($this->filterList[$field]);
                if (!$this->filterList[$field]) {
                    unset($this->filterList[$field]);
                }
            }
        }
    }

    /**
     * Remove all filters from the list.
     *
     * @param string $field Name of field to remove filters from (null to remove
     * all filters from all fields)
     *
     * @return void
     */
    public function removeAllFilters($field = null)
    {
        if ($field == null) {
            $this->filterList = [];
        } else {
            foreach (['', '-', '~'] as $prefix) {
                if (isset($this->filterList[$prefix . $field])) {
                    unset($this->filterList[$prefix . $field]);
                }
            }
        }
    }

    /**
     * Add a field to facet on.
     *
     * @param string $newField Field name
     * @param string $newAlias Optional on-screen display label
     * @param bool   $ored     Should we treat this as an ORed facet?
     *
     * @return void
     */
    public function addFacet($newField, $newAlias = null, $ored = false)
    {
        if ($newAlias == null) {
            $newAlias = $newField;
        }
        $this->facetConfig[$newField] = $newAlias;
        if ($ored) {
            $this->orFacets[] = $newField;
        }
    }

    /**
     * Get facet operator for the specified field
     *
     * @param string $field Field name
     *
     * @return string
     */
    public function getFacetOperator($field)
    {
        return in_array($field, $this->orFacets) ? 'OR' : 'AND';
    }

    /**
     * Add a checkbox facet. When the checkbox is checked, the specified filter
     * will be applied to the search. When the checkbox is not checked, no filter
     * will be applied.
     *
     * @param string $filter  [field]:[value] pair to associate with checkbox
     * @param string $desc    Description to associate with the checkbox
     * @param bool   $dynamic Is this being added dynamically (true) or in response
     * to a user configuration (false)?
     *
     * @return void
     */
    public function addCheckboxFacet($filter, $desc, $dynamic = false)
    {
        // Extract the facet field name from the filter, then add the
        // relevant information to the array.
        [$fieldName] = explode(':', $filter);
        $this->checkboxFacets[$fieldName][$filter]
            = compact('desc', 'filter', 'dynamic');
    }

    /**
     * Get a user-friendly string to describe the provided facet field.
     *
     * @param string $field   Facet field name.
     * @param string $value   Facet value.
     * @param string $default Default field name (null for default behavior).
     *
     * @return string         Human-readable description of field.
     */
    public function getFacetLabel($field, $value = null, $default = null)
    {
        if (
            !isset($this->facetConfig[$field])
            && !isset($this->extraFacetLabels[$field])
            && isset($this->facetAliases[$field])
        ) {
            $field = $this->facetAliases[$field];
        }
        $checkboxFacet = $this->checkboxFacets[$field]["$field:$value"] ?? null;
        if (null !== $checkboxFacet) {
            return $checkboxFacet['desc'];
        }
        if (isset($this->facetConfig[$field])) {
            return $this->facetConfig[$field];
        }
        return $this->extraFacetLabels[$field]
            ?? ($default ?: 'unrecognized_facet_label');
    }

    /**
     * Get the current facet configuration.
     *
     * @return array
     */
    public function getFacetConfig()
    {
        return $this->facetConfig;
    }

    /**
     * Reset the current facet configuration.
     *
     * @return void
     */
    public function resetFacetConfig()
    {
        $this->facetConfig = [];
    }

    /**
     * Get the raw filter list.
     *
     * @return array
     */
    public function getRawFilters()
    {
        return $this->filterList;
    }

    /**
     * Return an array structure containing information about all current filters.
     *
     * @param bool $excludeCheckboxFilters Should we exclude checkbox filters from
     * the list (to be used as a complement to getCheckboxFacets()).
     *
     * @return array                       Field, values and translation status
     */
    public function getFilterList($excludeCheckboxFilters = false)
    {
        // If we don't have any filters, return right away to avoid further
        // processing:
        if (!$this->filterList) {
            return [];
        }

        // Get a list of checkbox filters to skip if necessary:
        $skipList = $excludeCheckboxFilters
            ? $this->getCheckboxFacetValues() : [];

        $list = [];
        $translatedFacets = $this->getOptions()->getTranslatedFacets();
        // Loop through all the current filter fields
        foreach ($this->filterList as $field => $values) {
            [$operator, $field] = $this->parseOperatorAndFieldName($field);
            $translate = in_array($field, $translatedFacets);
            // and each value currently used for that field
            foreach ($values as $value) {
                // Add to the list unless it's in the list of fields to skip:
                if (
                    !isset($skipList[$field])
                    || !in_array($value, $skipList[$field])
                ) {
                    $facetLabel = $this->getFacetLabel($field, $value);
                    $list[$facetLabel][] = $this->formatFilterListEntry(
                        $field,
                        $value,
                        $operator,
                        $translate
                    );
                }
            }
        }
        return $list;
    }

    /**
     * Get the filter list as a query parameter array.
     *
     * Returns an array of strings that parseFilter can parse.
     *
     * @return array
     */
    public function getFiltersAsQueryParams(): array
    {
        return $this->formatFilterArrayAsQueryParams($this->getRawFilters());
    }

    /**
     * Get a display text for a facet field.
     *
     * @param string $field Facet field
     * @param string $value Facet value
     *
     * @return string
     */
    public function getFacetValueRawDisplayText(string $field, string $value): string
    {
        // Check for delimited facets -- if $field is a delimited facet field,
        // process $displayText accordingly:
        $delimitedFacetFields = $this->getOptions()->getDelimitedFacets(true);
        if (isset($delimitedFacetFields[$field])) {
            $parts = explode($delimitedFacetFields[$field], $value);
            return end($parts);
        }

        return $value;
    }

    /**
     * Translate a facet value.
     *
     * @param string                    $field Field name
     * @param string|TranslatableString $text  Field value (processed by
     * getFacetValueRawDisplayText)
     *
     * @return string
     */
    public function translateFacetValue(string $field, $text): string
    {
        $domain = $this->getOptions()->getTextDomainForTranslatedFacet($field);
        $translateFormat = $this->getOptions()->getFormatForTranslatedFacet($field);
        $translated = $this->translate([$domain, $text]);
        return $translateFormat
            ? $this->translate(
                $translateFormat,
                [
                    '%%raw%%' => $text,
                    '%%translated%%' => $translated,
                ]
            ) : $translated;
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
        $rawDisplayText = $this->getFacetValueRawDisplayText($field, $value);
        $displayText = $translate
            ? $this->translateFacetValue($field, $rawDisplayText)
            : $rawDisplayText;

        return compact('value', 'displayText', 'field', 'operator');
    }

    /**
     * Parse the operator and field name from a prefixed field string.
     *
     * @param string $field Prefixed string
     *
     * @return array (0 = operator, 1 = field name)
     */
    protected function parseOperatorAndFieldName($field)
    {
        $firstChar = substr($field, 0, 1);
        if ($firstChar == '-') {
            $operator = 'NOT';
            $field = substr($field, 1);
        } elseif ($firstChar == '~') {
            $operator = 'OR';
            $field = substr($field, 1);
        } else {
            $operator = 'AND';
        }
        return [$operator, $field];
    }

    /**
     * Get a formatted list of checkbox filter values ($field => array of values).
     *
     * @return array
     */
    protected function getCheckboxFacetValues()
    {
        $list = [];
        foreach ($this->getRawCheckboxFacets() as $facets) {
            foreach ($facets as $current) {
                [$field, $value] = $this->parseFilter($current['filter']);
                if (!isset($list[$field])) {
                    $list[$field] = [];
                }
                $list[$field][] = $value;
            }
        }
        return $list;
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
        // Build up an array of checkbox facets with status booleans and
        // toggle URLs.
        $result = [];
        foreach ($this->getRawCheckboxFacets() as $facets) {
            foreach ($facets as $facet) {
                // If the current filter is not on the include list, skip it (but
                // accept everything if the include list is null).
                if (
                    ($include !== null && !in_array($facet['filter'], $include))
                    && !($includeDynamic && $facet['dynamic'])
                ) {
                    continue;
                }
                $facet['selected'] = $this->hasFilter($facet['filter']);
                // Is this checkbox always visible, even if non-selected on the
                // "no results" screen?  By default, no (may be overridden by
                // child classes).
                $facet['alwaysVisible'] = false;
                $result[] = $facet;
            }
        }
        return $result;
    }

    /**
     * Return checkbox facets without any processing
     *
     * @return array
     */
    protected function getRawCheckboxFacets(): array
    {
        return $this->checkboxFacets;
    }

    /**
     * Format a raw filter array as a query parameter array.
     *
     * Returns an array of strings that parseFilter can parse.
     *
     * @param array $filterArray Filter array
     *
     * @return array
     */
    protected function formatFilterArrayAsQueryParams(array $filterArray): array
    {
        $result = [];
        foreach ($filterArray as $field => $values) {
            foreach ($values as $current) {
                $result[] = "$field:\"$current\"";
            }
        }
        return $result;
    }

    /**
     * Initialize all range filters.
     *
     * @param \Laminas\Stdlib\Parameters $request Parameter object representing user
     * request.
     *
     * @return void
     */
    protected function initRangeFilters($request)
    {
        $this->initDateFilters($request);
        $this->initFullDateFilters($request);
        $this->initGenericRangeFilters($request);
        $this->initNumericRangeFilters($request);
    }

    /**
     * Support method for initDateFilters() -- normalize a year for use in a
     * year-based date range.
     *
     * @param ?string $year     Value to check for valid year.
     * @param bool    $rangeEnd Is this the end of a range?
     *
     * @return string      Formatted year.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function formatYearForDateRange($year, $rangeEnd = false)
    {
        // Make sure parameter is set and numeric; default to wildcard otherwise:
        $year = ($year && preg_match('/\d{2,4}/', $year)) ? $year : '*';

        // Pad to four digits:
        if (strlen($year) == 2) {
            $year = '19' . $year;
        } elseif (strlen($year) == 3) {
            $year = '0' . $year;
        }

        return $year;
    }

    /**
     * Support method for initFullDateFilters() -- normalize a date for use in a
     * year/month/day date range.
     *
     * @param ?string $date     Value to check for valid date.
     * @param bool    $rangeEnd Is this the end of a range?
     *
     * @return string      Formatted date.
     */
    protected function formatDateForFullDateRange($date, $rangeEnd = false)
    {
        // Make sure date is valid; default to wildcard otherwise:
        $date = $date ? SolrUtils::sanitizeDate($date, $rangeEnd) : null;
        return $date ?? '*';
    }

    /**
     * Support method for initNumericRangeFilters() -- normalize a number for use in
     * a numeric range.
     *
     * @param ?string $num      Value to format into a number.
     * @param bool    $rangeEnd Is this the end of a range?
     *
     * @return string     Formatted number.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function formatValueForNumericRange($num, $rangeEnd = false)
    {
        // empty strings, null values and non-numeric values are treated as wildcards:
        if ($num === '' || $num === null || !is_numeric($num)) {
            return '*';
        }
        // If we got this far, it's a number!
        return $num;
    }

    /**
     * Support method for initGenericRangeFilters() -- build a filter query based on
     * a range of values.
     *
     * @param string $field field to use for filtering.
     * @param string $from  start of range.
     * @param string $to    end of range.
     * @param bool   $cs    Should ranges be case-sensitive?
     *
     * @return string       filter query.
     */
    protected function buildGenericRangeFilter($field, $from, $to, $cs = true)
    {
        // Assume Solr syntax -- this should be overridden in child classes where
        // other indexing methodologies are used.
        $range = "{$field}:[{$from} TO {$to}]";
        if (!$cs) {
            // Flip values if out of order:
            if (strcmp(strtolower($from), strtolower($to)) > 0) {
                $range = "{$field}:[{$to} TO {$from}]";
            }
            $helper = new LuceneSyntaxHelper(false, false);
            $range = $helper->capitalizeRanges($range);
        }
        return $range;
    }

    /**
     * Support method for initFilters() -- initialize range filters. Factored
     * out as a separate method so that it can be more easily overridden by child
     * classes.
     *
     * @param \Laminas\Stdlib\Parameters $request         Parameter object
     * representing user request.
     * @param string                     $requestParam    Name of parameter
     * containing names of range filter fields.
     * @param callable                   $valueFilter     Optional callback to
     * process values in the range.
     * @param callable                   $filterGenerator Optional callback to create
     * a filter query from the range values.
     *
     * @return void
     */
    protected function initGenericRangeFilters(
        $request,
        $requestParam = 'genericrange',
        $valueFilter = null,
        $filterGenerator = null
    ) {
        $rangeFacets = $request->get($requestParam);
        if (!empty($rangeFacets)) {
            $ranges = is_array($rangeFacets) ? $rangeFacets : [$rangeFacets];
            foreach ($ranges as $range) {
                // Load start and end of range:
                $from = $request->get($range . 'from');
                $to = $request->get($range . 'to');

                // Apply filtering/validation if necessary:
                if (is_callable($valueFilter)) {
                    $from = call_user_func($valueFilter, $from, false);
                    $to = call_user_func($valueFilter, $to, true);
                }

                // Build filter only if necessary:
                if (!empty($range) && ($from != '*' || $to != '*')) {
                    $rangeFacet = is_callable($filterGenerator)
                        ? call_user_func($filterGenerator, $range, $from, $to)
                        : $this->buildGenericRangeFilter($range, $from, $to, false);
                    $this->addFilter($rangeFacet);
                }
            }
        }
    }

    /**
     * Support method for initNumericRangeFilters() -- build a filter query based on
     * a range of numbers.
     *
     * @param string $field field to use for filtering.
     * @param string $from  number for start of range.
     * @param string $to    number for end of range.
     *
     * @return string       filter query.
     */
    protected function buildNumericRangeFilter($field, $from, $to)
    {
        // Make sure that $to is less than $from:
        if ($to != '*' && $from != '*' && $to < $from) {
            $tmp = $to;
            $to = $from;
            $from = $tmp;
        }

        return $this->buildGenericRangeFilter($field, $from, $to);
    }

    /**
     * Support method for initDateFilters() -- build a filter query based on a range
     * of 4-digit years.
     *
     * @param string $field field to use for filtering.
     * @param string $from  year for start of range.
     * @param string $to    year for end of range.
     *
     * @return string       filter query.
     */
    protected function buildDateRangeFilter($field, $from, $to)
    {
        // Dates work just like numbers:
        return $this->buildNumericRangeFilter($field, $from, $to);
    }

    /**
     * Support method for initFullDateFilters() -- build a filter query based on a
     * range of dates.
     *
     * @param string $field field to use for filtering.
     * @param string $from  year for start of range.
     * @param string $to    year for end of range.
     *
     * @return string       filter query.
     */
    protected function buildFullDateRangeFilter($field, $from, $to)
    {
        // Make sure that $to is less than $from:
        if ($to != '*' && $from != '*' && strtotime($to) < strtotime($from)) {
            $tmp = $to;
            $to = $from;
            $from = $tmp;
        }

        return $this->buildGenericRangeFilter($field, $from, $to);
    }

    /**
     * Support method for initFilters() -- initialize year-based date filters.
     * Factored out as a separate method so that it can be more easily overridden
     * by child classes.
     *
     * @param \Laminas\Stdlib\Parameters $request Parameter object representing user
     * request.
     *
     * @return void
     */
    protected function initDateFilters($request)
    {
        $this->initGenericRangeFilters(
            $request,
            'daterange',
            [$this, 'formatYearForDateRange'],
            [$this, 'buildDateRangeFilter']
        );
    }

    /**
     * Support method for initFilters() -- initialize year/month/day-based date
     * filters. Factored out as a separate method so that it can be more easily
     * overridden by child classes.
     *
     * @param \Laminas\Stdlib\Parameters $request Parameter object representing user
     * request.
     *
     * @return void
     */
    protected function initFullDateFilters($request)
    {
        $this->initGenericRangeFilters(
            $request,
            'fulldaterange',
            [$this, 'formatDateForFullDateRange'],
            [$this, 'buildFullDateRangeFilter']
        );
    }

    /**
     * Support method for initFilters() -- initialize numeric range filters. Factored
     * out as a separate method so that it can be more easily overridden by child
     * classes.
     *
     * @param \Laminas\Stdlib\Parameters $request Parameter object representing user
     * request.
     *
     * @return void
     */
    protected function initNumericRangeFilters($request)
    {
        $this->initGenericRangeFilters(
            $request,
            'numericrange',
            [$this, 'formatValueForNumericRange'],
            [$this, 'buildNumericRangeFilter']
        );
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
        // Handle standard filters:
        $filter = $request->get('filter');
        if (!empty($filter)) {
            if (is_array($filter)) {
                foreach ($filter as $current) {
                    $this->addFilter($current);
                }
            } else {
                $this->addFilter($filter);
            }
        }

        // If we don't have the special flag indicating that defaults have
        // been applied, and if we do have defaults, apply them:
        if ($request->get('dfApplied')) {
            $this->defaultsApplied = true;
        } else {
            $defaults = $this->getOptions()->getDefaultFilters();
            if (!empty($defaults)) {
                foreach ($defaults as $current) {
                    $this->addFilter($current);
                }
                $this->defaultsApplied = true;
            }
        }

        // Handle range filters:
        $this->initRangeFilters($request);
    }

    /**
     * Add hidden filters to the object based on values found in the request object.
     *
     * @param \Laminas\Stdlib\Parameters $request Parameter object representing user
     * request.
     *
     * @return void
     */
    protected function initHiddenFilters($request)
    {
        $hiddenFilters = $request->get('hiddenFilters');
        if (!empty($hiddenFilters) && is_array($hiddenFilters)) {
            foreach ($hiddenFilters as $current) {
                $this->addHiddenFilter($current);
            }
        }
    }

    /**
     * Get hidden filters grouped by field like normal filters.
     *
     * @return array
     */
    public function getHiddenFilters()
    {
        return $this->hiddenFilters;
    }

    /**
     * Get the hidden filter list as a query parameter array.
     *
     * Returns an array of strings that parseFilter can parse.
     *
     * @return array
     */
    public function getHiddenFiltersAsQueryParams(): array
    {
        return $this->formatFilterArrayAsQueryParams($this->getHiddenFilters());
    }

    /**
     * Does the object already contain the specified hidden filter?
     *
     * @param string $filter A filter string from url : "field:value"
     *
     * @return bool
     */
    public function hasHiddenFilter($filter)
    {
        // Extract field and value from URL string:
        [$field, $value] = $this->parseFilter($filter);

        if (
            isset($this->hiddenFilters[$field])
            && in_array($value, $this->hiddenFilters[$field])
        ) {
            return true;
        }
        return false;
    }

    /**
     * Take a filter string and add it into the protected hidden filters
     *   array checking for duplicates.
     *
     * @param string $newFilter A filter string from url : "field:value"
     *
     * @return void
     */
    public function addHiddenFilter($newFilter)
    {
        // Check for duplicates -- if it's not in the array, we can add it
        if (!$this->hasHiddenFilter($newFilter)) {
            // Extract field and value from filter string:
            [$field, $value] = $this->parseFilter($newFilter);
            if (!empty($field) && '' !== $value) {
                $this->hiddenFilters[$field][] = $value;
            }
        }
    }

    /**
     * Take a filter string and add it into the protected hidden filters
     *   array checking for duplicates.
     *
     * @param string $field Field
     * @param string $value Filter value
     *
     * @return void
     */
    public function addHiddenFilterForField(string $field, string $value): void
    {
        $this->addHiddenFilter("$field:\"$value\"");
    }

    /**
     * Return a query string for the current search with a search term replaced.
     *
     * @param string $oldTerm The old term to replace
     * @param string $newTerm The new term to search
     *
     * @return string         query string
     */
    public function getDisplayQueryWithReplacedTerm($oldTerm, $newTerm)
    {
        // Stash our old data for a minute
        $oldTerms = clone $this->query;
        // Replace the search term
        $this->query->replaceTerm($oldTerm, $newTerm);
        // Get the new query string
        $query = $this->getDisplayQuery();
        // Restore the old data
        $this->query = $oldTerms;
        // Return the query string
        return $query;
    }

    /**
     * Basic 'getter' for list of available view options.
     *
     * @return array
     */
    public function getViewList()
    {
        $list = [];
        foreach ($this->getOptions()->getViewOptions() as $key => $value) {
            $list[$key] = [
                'desc' => $value,
                'selected' => ($key == $this->getView()),
            ];
        }
        return $list;
    }

    /**
     * Return a list of urls for possible limits, along with which option
     *    should be currently selected.
     *
     * @return array Limit urls, descriptions and selected flags
     */
    public function getLimitList()
    {
        // Loop through all the current limits
        $valid = $this->getOptions()->getLimitOptions();
        $defaultLimit = $this->getOptions()->getDefaultLimit();
        $list = [];
        foreach ($valid as $limit) {
            $list[$limit] = [
                'desc' => $limit,
                'selected' => ($limit == $this->getLimit()),
                'default' => $limit == $defaultLimit,
            ];
        }
        return $list;
    }

    /**
     * Return a list of urls for sorting, along with which option
     *    should be currently selected.
     *
     * @return array Sort urls, descriptions and selected flags
     */
    public function getSortList()
    {
        // Loop through all the current filter fields
        $valid = $this->getOptions()->getSortOptions();
        $defaultSort = $this->getDefaultSort();
        $list = [];
        $currentSort = $this->getSort();
        foreach ($valid as $sort => $desc) {
            $list[$sort] = [
                'desc' => $desc,
                'selected' => ($sort == $currentSort),
                'default' => $sort == $defaultSort,
            ];
        }
        if (!isset($list[$currentSort])) {
            // Add selected sort with a generic description so that we display it:
            $list[$currentSort] = [
                'desc' => 'unrecognized_sort_option',
                'selected' => true,
                'default' => false,
            ];
        }
        return $list;
    }

    /**
     * Store settings to a minified object
     *
     * @param Minified $minified Minified Search Object
     *
     * @return void
     */
    public function minify(Minified &$minified): void
    {
        $minified->ty = $this->getSearchType();
        $minified->cl = $this->getSearchClassId();

        // Search terms, we'll shorten keys
        $query = $this->getQuery();
        $minified->t = $this->getQueryAdapter()->minify($query);

        // It would be nice to shorten filter fields too, but
        //      it would be a nightmare to maintain.
        $minified->f = $this->getRawFilters();
        $minified->hf = $this->getHiddenFilters();

        $minified->scp = [
            'page' => $this->getPage(),
            'limit' => $this->getLimit(),
        ];
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
        // Some values will transfer without changes
        $this->filterList = $minified->f;
        $this->hiddenFilters = $minified->hf;
        $this->searchType = $minified->ty;
        $this->searchContextParameters = $minified->scp;

        // Deminified searches will always have defaults already applied;
        // we don't want to accidentally manipulate them further.
        $defaults = $this->getOptions()->getDefaultFilters();
        if (!empty($defaults)) {
            $this->defaultsApplied = true;
        }

        // Search terms, we need to expand keys
        $this->query = $this->getQueryAdapter()->deminify($minified->t);
    }

    /**
     * Get remembered search context parameters from saved search. We track these separately since
     * in some contexts we want to use them (e.g. linking back to a search in breadcrumbs), but in
     * other contexts we want to ignore them (e.g. comparing two searches to see if they represent
     * the same query -- because page 1 and page 2 still represent the same overall search).
     *
     * @return array
     */
    public function getSavedSearchContextParameters(): array
    {
        return $this->searchContextParameters;
    }

    /**
     * Override the normal search behavior with an explicit array of IDs that must
     * be retrieved.
     *
     * @param array $ids Record IDs to load
     *
     * @return void
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function setQueryIDs($ids)
    {
        // This needs to be defined in child classes:
        throw new \Exception(get_class($this) . ' does not support setQueryIDs().');
    }

    /**
     * Get the maximum number of IDs that may be sent to setQueryIDs (-1 for no
     * limit).
     *
     * @return int
     */
    public function getQueryIDLimit()
    {
        return -1;
    }

    /**
     * Get an array of the names of all selected shards. These should correspond
     * with keys in the array returned by the option class's getShards() method.
     *
     * @return array
     */
    public function getSelectedShards()
    {
        return $this->selectedShards;
    }

    /**
     * Translate a string (or string-castable object)
     *
     * @param string|object|array $target  String to translate or an array of text
     * domain and string to translate
     * @param array               $tokens  Tokens to inject into the translated
     * string
     * @param string              $default Default value to use if no translation is
     * found (null for no default).
     *
     * @return string
     */
    public function translate($target, $tokens = [], $default = null)
    {
        return $this->getOptions()->translate($target, $tokens, $default);
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
     * @return AbstractQuery
     */
    public function getQuery()
    {
        if ($this->overrideQuery) {
            return new Query($this->overrideQuery);
        }
        return $this->query;
    }

    /**
     * Set search query object.
     *
     * @param AbstractQuery $query Query
     *
     * @return void
     */
    public function setQuery(AbstractQuery $query): void
    {
        if ($this->overrideQuery) {
            $this->overrideQuery = false;
        }
        $this->query = $query;
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
        if (!isset($config->$facetList)) {
            return false;
        }
        if (isset($config->$facetSettings->orFacets)) {
            $orFields
                = array_map('trim', explode(',', $config->$facetSettings->orFacets));
        } else {
            $orFields = [];
        }
        foreach ($config->$facetList as $key => $value) {
            $useOr = (isset($orFields[0]) && $orFields[0] == '*')
                || in_array($key, $orFields);
            $this->addFacet($key, $value, $useOr);
        }

        return true;
    }

    /**
     * Are default filters applied?
     *
     * @return bool
     */
    public function hasDefaultsApplied()
    {
        return $this->defaultsApplied;
    }

    /**
     * Initialize checkbox facet settings for the specified configuration sections.
     *
     * @param string $facetList Config section containing fields to activate
     * @param string $cfgFile   Name of configuration to load (null to load
     * default facets configuration).
     *
     * @return bool             True if facets set, false if no settings found
     */
    protected function initCheckboxFacets(
        $facetList = 'CheckboxFacets',
        $cfgFile = null
    ) {
        $config = $this->configLoader
            ->get($cfgFile ?? $this->getOptions()->getFacetsIni());
        $retVal = false;
        // If the section is in reverse order, the tilde will flag this:
        if (str_starts_with($facetList, '~')) {
            foreach ($config->{substr($facetList, 1)} ?? [] as $value => $key) {
                $this->addCheckboxFacet($key, $value);
                $retVal = true;
            }
        } else {
            foreach ($config->$facetList ?? [] as $key => $value) {
                $this->addCheckboxFacet($key, $value);
                $retVal = true;
            }
        }
        return $retVal;
    }

    /**
     * Check whether a specific facet supports filtering
     *
     * @param string $facet The facet to check
     *
     * @return bool
     */
    public function supportsFacetFiltering($facet)
    {
        $translatedFacets = $this->getOptions()->getTranslatedFacets();
        return method_exists($this, 'setFacetContains') && !in_array($facet, $translatedFacets);
    }

    /**
     * Is this a specialized search (i.e. a customized scenario like new items,
     * rather than a "normal" backend search)?
     *
     * @return bool
     */
    public function isSpecializedSearch(): bool
    {
        return $this->isSpecializedSearch;
    }
}
