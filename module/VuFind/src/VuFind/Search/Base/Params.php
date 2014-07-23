<?php
/**
 * Abstract parameters search model.
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
 * @package  Search_Base
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
namespace VuFind\Search\Base;
use Zend\ServiceManager\ServiceLocatorAwareInterface,
    Zend\ServiceManager\ServiceLocatorInterface;
use VuFindSearch\Backend\Solr\LuceneSyntaxHelper, VuFindSearch\Query\Query;
use VuFind\Search\QueryAdapter;

/**
 * Abstract parameters search model.
 *
 * This abstract class defines the parameters methods for modeling a search in VuFind
 *
 * @category VuFind2
 * @package  Search_Base
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class Params implements ServiceLocatorAwareInterface
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
    protected $selectedShards = array();

    /**
     * View
     *
     * @var string
     */
    protected $view = null;

    /**
     * Search options
     *
     * @var Options
     */
    protected $options;

    /**
     * Recommendation settings
     *
     * @var array
     */
    protected $recommend = array();

    /**
     * Are recommendations turned on?
     *
     * @var bool
     */
    protected $recommendationEnabled = false;

    /**
     * Main facet configuration
     *
     * @var array
     */
    protected $facetConfig = array();

    /**
     * Checkbox facet configuration
     *
     * @var array
     */
    protected $checkboxFacets = array();

    /**
     * Applied filters
     *
     * @var array
     */
    protected $filterList = array();

    /**
     * Facets in "OR" mode
     *
     * @var array
     */
    protected $orFacets = array();

    /**
     * Override Query
     */
    protected $overrideQuery = false;

    /**
     * Service locator
     *
     * @var ServiceLocatorInterface
     */
    protected $serviceLocator;

    /**
     * Are default filters applied?
     *
     * @var bool
     */
    protected $defaultsApplied = false;

    /**
     * Constructor
     *
     * @param \VuFind\Search\Base\Options  $options      Options to use
     * @param \VuFind\Config\PluginManager $configLoader Config loader
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __construct($options, \VuFind\Config\PluginManager $configLoader)
    {
        $this->setOptions($options);

        // Make sure we have some sort of query object:
        $this->query = new Query();
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
     * Copy constructor
     *
     * @return void
     */
    public function __clone()
    {
        if (is_object($this->options)) {
            $this->options = clone($this->options);
        }
        if (is_object($this->query)) {
            $this->query = clone($this->query);
        }
    }

    /**
     * Get the identifier used for naming the various search classes in this family.
     *
     * @return string
     */
    public function getSearchClassId()
    {
        // Parse identifier out of class name of format VuFind\Search\[id]\Params:
        $class = explode('\\', get_class($this));
        return $class[2];
    }

    /**
     * Pull the search parameters
     *
     * @param \Zend\StdLib\Parameters $request Parameter object representing user
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

        // Always initialize recommendations last (since they rely on knowing
        // other search settings that were set above).
        $this->initRecommendations($request);

        // Remember the user's settings for future reference (we only want to do
        // this in initFromRequest, since other code may call the set methods from
        // other contexts!):
        $this->getOptions()->rememberLastLimit($this->getLimit());
        $this->getOptions()->rememberLastSort($this->getSort());
    }

    /**
     * Pull shard parameters from the request or set defaults
     *
     * @param \Zend\StdLib\Parameters $request Parameter object representing user
     * request.
     *
     * @return void
     */
    protected function initShards($request)
    {
        $legalShards = array_keys($this->getOptions()->getShards());
        $requestShards = $request->get('shard', array());
        if (!is_array($requestShards)) {
            $requestShards = array($requestShards);
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
     * @param \Zend\StdLib\Parameters $request Parameter object representing user
     * request.
     *
     * @return void
     */
    protected function initLimit($request)
    {
        // Check for a limit parameter in the url.
        $defaultLimit = $this->getOptions()->getDefaultLimit();
        if (($limit = $request->get('limit')) != $defaultLimit) {
            // make sure the url parameter is a valid limit -- either
            // one of the explicitly allowed values, or at least smaller
            // than the largest allowed. (This leniency is useful in
            // combination with combined search, where it is often useful
            // to reduce the size of result lists without actually enabling
            // the user's ability to select a reduced list size).
            $legalOptions = $this->getOptions()->getLimitOptions();
            if (in_array($limit, $legalOptions)
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
     * @param \Zend\StdLib\Parameters $request Parameter object representing user
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
     * @param \Zend\StdLib\Parameters $request Parameter object representing user
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
     * @param \Zend\StdLib\Parameters $request Parameter object representing user
     * request.
     *
     * @return boolean True if search settings were found, false if not.
     */
    protected function initBasicSearch($request)
    {
        // If no lookfor parameter was found, we have no search terms to
        // add to our array!
        if (is_null($lookfor = $request->get('lookfor'))) {
            return false;
        }

        // If lookfor is an array, we may be dealing with a legacy Advanced
        // Search URL.  If there's only one parameter, we can flatten it,
        // but otherwise we should treat it as an error -- no point in going
        // to great lengths for compatibility.
        if (is_array($lookfor)) {
            if (count($lookfor) > 1) {
                throw new \Exception("Unsupported search URL.");
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
     * Support method for initSearch() -- handle advanced settings.  Advanced
     * searches have numeric subscripts on the lookfor and type parameters --
     * this is how they are distinguished from basic searches.
     *
     * @param \Zend\StdLib\Parameters $request Parameter object representing user
     * request.
     *
     * @return void
     */
    protected function initAdvancedSearch($request)
    {
        $this->query = QueryAdapter::fromRequest(
            $request, $this->getOptions()->getDefaultHandler()
        );

        $this->searchType = $this->query instanceof Query ? 'basic' : 'advanced';

        // If we ended up with a basic search, set the default handler if necessary:
        if ($this->searchType == 'basic' && $this->query->getHandler() === null) {
            $this->query->setHandler($this->getOptions()->getDefaultHandler());
        }
    }

    /**
     * Get the value for which type of sorting to use
     *
     * @param \Zend\StdLib\Parameters $request Parameter object representing user
     * request.
     *
     * @return string
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
     * Get the value for which results view to use
     *
     * @param \Zend\StdLib\Parameters $request Parameter object representing user
     * request.
     *
     * @return string
     */
    protected function initView($request)
    {
        // Check for a view parameter in the url.
        $view = $request->get('view');
        $lastView = $this->getOptions()->getLastView();
        if (!empty($view)) {
            if ($view == 'rss') {
                // we don't want to store rss in the Session
                $this->setView('rss');
            } else {
                // store non-rss views in Session for persistence
                $validViews = $this->getOptions()->getViewOptions();
                // make sure the url parameter is a valid view
                if (in_array($view, array_keys($validViews))) {
                    $this->setView($view);
                    $this->getOptions()->rememberLastView($view);
                } else {
                    $this->setView($this->getOptions()->getDefaultView());
                }
            }
        } else if (!empty($lastView)) {
            // if there is nothing in the URL, check the Session
            $this->setView($lastView);
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
        if (!empty($sort) && in_array($sort, $valid)) {
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
        return is_null($this->view)
            ? $this->getOptions()->getDefaultView() : $this->view;
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
        $translate = array($this, 'translate');
        $showField = array($this->getOptions(), 'getHumanReadableFieldName');

        // Build display query:
        return QueryAdapter::display($this->getQuery(), $translate, $showField);
    }

    /**
     * Get an array of recommendation objects for augmenting the results display.
     *
     * @param string $location Name of location to use as a filter (null to get
     * associative array of all locations); legal non-null values: 'top', 'side'
     *
     * @return array
     */
    public function getRecommendations($location = 'top')
    {
        if (!$this->recommendationsEnabled()) {
            return array();
        }
        if (is_null($location)) {
            return $this->recommend;
        }
        return isset($this->recommend[$location])
            ? $this->recommend[$location] : array();
    }

    /**
     * Set the enabled status of recommendation modules -- it is often useful to turn
     * off recommendations when retrieving results in a context other than standard
     * display of results.
     *
     * @param bool $bool True to enable, false to disable (null to leave unchanged)
     *
     * @return bool      Current state of recommendations
     */
    public function recommendationsEnabled($bool = null)
    {
        if (!is_null($bool)) {
            $this->recommendationEnabled = $bool;
        }
        return $this->recommendationEnabled;
    }

    /**
     * Load all recommendation settings from the relevant ini file.  Returns an
     * associative array where the key is the location of the recommendations (top
     * or side) and the value is the settings found in the file (which may be either
     * a single string or an array of strings).
     *
     * @return array associative: location (top/side) => search settings
     */
    protected function getRecommendationSettings()
    {
        // Bypass settings if recommendations are disabled.
        if (!$this->recommendationsEnabled()) {
            return array();
        }

        // Load the necessary settings to determine the appropriate recommendations
        // module:
        $searchSettings = $this->getServiceLocator()->get('VuFind\Config')
            ->get($this->getOptions()->getSearchIni());

        // If we have a search type set, save it so we can try to load a
        // type-specific recommendations module:
        $handler = $this->getSearchHandler();

        // Load a type-specific recommendations setting if possible, or the default
        // otherwise:
        $recommend = array();
        if (!is_null($handler)
            && isset($searchSettings->TopRecommendations->$handler)
        ) {
            $recommend['top'] = $searchSettings->TopRecommendations
                ->$handler->toArray();
        } else {
            $recommend['top']
                = isset($searchSettings->General->default_top_recommend)
                ? $searchSettings->General->default_top_recommend->toArray()
                : false;
        }
        if (!is_null($handler)
            && isset($searchSettings->SideRecommendations->$handler)
        ) {
            $recommend['side'] = $searchSettings->SideRecommendations
                ->$handler->toArray();
        } else {
            $recommend['side']
                = isset($searchSettings->General->default_side_recommend)
                ? $searchSettings->General->default_side_recommend->toArray()
                : false;
        }
        if (!is_null($handler)
            && isset($searchSettings->NoResultsRecommendations->$handler)
        ) {
            $recommend['noresults'] = $searchSettings->NoResultsRecommendations
                ->$handler->toArray();
        } else {
            $recommend['noresults']
                = isset($searchSettings->General->default_noresults_recommend)
                ? $searchSettings->General->default_noresults_recommend->toArray()
                : false;
        }

        return $recommend;
    }

    /**
     * Initialize the recommendations modules.
     *
     * @param \Zend\StdLib\Parameters $request Parameter object representing user
     * request.
     *
     * @return void
     */
    protected function initRecommendations($request)
    {
        // If no settings were found, quit now:
        $settings = $this->getRecommendationSettings();
        if (empty($settings)) {
            return;
        }

        // Get the plugin manager (skip recommendations if it is unavailable):
        $sm = $this->getServiceLocator();
        if (!is_object($sm) || !$sm->has('VuFind\RecommendPluginManager')) {
            return;
        }
        $manager = $sm->get('VuFind\RecommendPluginManager');

        // Process recommendations for each location:
        $this->recommend = array(
            'top' => array(), 'side' => array(), 'noresults' => array(),
            'bottom' => array(),
        );
        foreach ($settings as $location => $currentSet) {
            // If the current location is disabled, skip processing!
            if (empty($currentSet)) {
                continue;
            }
            // Make sure the current location's set of recommendations is an array;
            // if it's a single string, this normalization will simplify processing.
            if (!is_array($currentSet)) {
                $currentSet = array($currentSet);
            }
            // Now loop through all recommendation settings for the location.
            foreach ($currentSet as $current) {
                // Break apart the setting into module name and extra parameters:
                $current = explode(':', $current);
                $module = array_shift($current);
                $params = implode(':', $current);
                if (!$manager->has($module)) {
                    throw new \Exception(
                        'Could not load recommendation module: ' . $module
                    );
                }

                // Build a recommendation module with the provided settings.
                $obj = $manager->get($module);
                $obj->setConfig($params);
                $obj->init($this, $request);
                $this->recommend[$location][] = $obj;
            }
        }
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
        // Split the string and assign the parts to $field and $value
        $temp = explode(':', $filter, 2);
        $field = array_shift($temp);
        $value = count($temp) > 0 ? $temp[0] : '';

        // Remove quotes from the value if there are any
        if (substr($value, 0, 1)  == '"') {
            $value = substr($value, 1);
        }
        if (substr($value, -1, 1) == '"') {
            $value = substr($value, 0, -1);
        }
        // One last little clean on whitespace
        $value = trim($value);

        // Send back the results:
        return array($field, $value);
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
        list($field, $value) = $this->parseFilter($filter);

        if (isset($this->filterList[$field])
            && in_array($value, $this->filterList[$field])
        ) {
            return true;
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
        // Extract field and value from URL string:
        list($field, $value) = $this->parseFilter($newFilter);

        // Check for duplicates -- if it's not in the array, we can add it
        if (!$this->hasFilter($newFilter)) {
            $this->filterList[$field][] = $value;
        }
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
        list($field, $value) = $this->parseFilter($oldFilter);

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
            $this->filterList = array();
        } else {
            $this->filterList[$field] = array();
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
     * Add a checkbox facet.  When the checkbox is checked, the specified filter
     * will be applied to the search.  When the checkbox is not checked, no filter
     * will be applied.
     *
     * @param string $filter [field]:[value] pair to associate with checkbox
     * @param string $desc   Description to associate with the checkbox
     *
     * @return void
     */
    public function addCheckboxFacet($filter, $desc)
    {
        // Extract the facet field name from the filter, then add the
        // relevant information to the array.
        list($fieldName) = explode(':', $filter);
        $this->checkboxFacets[$fieldName]
            = array('desc' => $desc, 'filter' => $filter);
    }

    /**
     * Get a user-friendly string to describe the provided facet field.
     *
     * @param string $field Facet field name.
     *
     * @return string       Human-readable description of field.
     */
    public function getFacetLabel($field)
    {
        return isset($this->facetConfig[$field])
            ? $this->facetConfig[$field] : "Other";
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
        $this->facetConfig = array();
    }

    /**
     * Get the raw filter list.
     *
     * @return array
     */
    public function getFilters()
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
        // Get a list of checkbox filters to skip if necessary:
        $skipList = $excludeCheckboxFilters
            ? $this->getCheckboxFacetValues() : array();

        $list = array();
        // Loop through all the current filter fields
        foreach ($this->filterList as $field => $values) {
            list($operator, $field) = $this->parseOperatorAndFieldName($field);
            $translate
                = in_array($field, $this->getOptions()->getTranslatedFacets());
            // and each value currently used for that field
            foreach ($values as $value) {
                // Add to the list unless it's in the list of fields to skip:
                if (!isset($skipList[$field])
                    || !in_array($value, $skipList[$field])
                ) {
                    $facetLabel = $this->getFacetLabel($field);
                    $list[$facetLabel][] = $this->formatFilterListEntry(
                        $field, $value, $operator, $translate
                    );
                }
            }
        }
        return $list;
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
        return array(
            'value'       => $value,
            'displayText' => $translate ? $this->translate($value) : $value,
            'field'       => $field,
            'operator'    => $operator,
        );
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
        } else if ($firstChar == '~') {
            $operator = 'OR';
            $field = substr($field, 1);
        } else {
            $operator = 'AND';
        }
        return array($operator, $field);
    }

    /**
     * Get a formatted list of checkbox filter values ($field => array of values).
     *
     * @return array
     */
    protected function getCheckboxFacetValues()
    {
        $list = array();
        foreach ($this->checkboxFacets as $current) {
            list($field, $value) = $this->parseFilter($current['filter']);
            if (!isset($list[$field])) {
                $list[$field] = array();
            }
            $list[$field][] = $value;
        }
        return $list;
    }

    /**
     * Get information on the current state of the boolean checkbox facets.
     *
     * @return array
     */
    public function getCheckboxFacets()
    {
        // Build up an array of checkbox facets with status booleans and
        // toggle URLs.
        $facets = array();
        foreach ($this->checkboxFacets as $field => $details) {
            $facets[$field] = $details;
            if ($this->hasFilter($details['filter'])) {
                $facets[$field]['selected'] = true;
            } else {
                $facets[$field]['selected'] = false;
            }
            // Is this checkbox always visible, even if non-selected on the
            // "no results" screen?  By default, no (may be overridden by
            // child classes).
            $facets[$field]['alwaysVisible'] = false;
        }
        return $facets;
    }

    /**
     * Initialize all range filters.
     *
     * @param \Zend\StdLib\Parameters $request Parameter object representing user
     * request.
     *
     * @return void
     */
    protected function initRangeFilters($request)
    {
        $this->initDateFilters($request);
        $this->initGenericRangeFilters($request);
        $this->initNumericRangeFilters($request);
    }

    /**
     * Support method for initDateFilters() -- normalize a year for use in a date
     * range.
     *
     * @param string $year Value to check for valid year.
     *
     * @return string      Formatted year.
     */
    protected function formatYearForDateRange($year)
    {
        // Make sure parameter is set and numeric; default to wildcard otherwise:
        $year = preg_match('/\d{2,4}/', $year) ? $year : '*';

        // Pad to four digits:
        if (strlen($year) == 2) {
            $year = '19' . $year;
        } else if (strlen($year) == 3) {
            $year = '0' . $year;
        }

        return $year;
    }

    /**
     * Support method for initNumericRangeFilters() -- normalize a year for use in
     * a date range.
     *
     * @param string $num Value to format into a number.
     *
     * @return string     Formatted number.
     */
    protected function formatValueForNumericRange($num)
    {
        // empty strings are always wildcards:
        if ($num == '') {
            return '*';
        }

        // it's a string by default so this will kick it into interpreting it as a
        // number
        $num = $num + 0;
        return $num = !is_float($num) && !is_int($num) ? '*' : $num;
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
     * Support method for initFilters() -- initialize range filters.  Factored
     * out as a separate method so that it can be more easily overridden by child
     * classes.
     *
     * @param \Zend\StdLib\Parameters $request         Parameter object representing
     * user request.
     * @param string                  $requestParam    Name of parameter containing
     * names of range filter fields.
     * @param Callable                $valueFilter     Optional callback to process
     * values in the range.
     * @param Callable                $filterGenerator Optional callback to create
     * a filter query from the range values.
     *
     * @return void
     */
    protected function initGenericRangeFilters($request,
        $requestParam = 'genericrange', $valueFilter = null, $filterGenerator = null
    ) {
        $rangeFacets = $request->get($requestParam);
        if (!empty($rangeFacets)) {
            $ranges = is_array($rangeFacets) ? $rangeFacets : array($rangeFacets);
            foreach ($ranges as $range) {
                // Load start and end of range:
                $from = $request->get($range . 'from');
                $to = $request->get($range . 'to');

                // Apply filtering/validation if necessary:
                if (is_callable($valueFilter)) {
                    $from = call_user_func($valueFilter, $from);
                    $to = call_user_func($valueFilter, $to);
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
        if ($to != '*' && $from!= '*' && $to < $from) {
            $tmp = $to;
            $to = $from;
            $from = $tmp;
        }

        return $this->buildGenericRangeFilter($field, $from, $to);
    }

    /**
     * Support method for initDateFilters() -- build a filter query based on a range
     * of dates.
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
     * Support method for initFilters() -- initialize date-related filters.  Factored
     * out as a separate method so that it can be more easily overridden by child
     * classes.
     *
     * @param \Zend\StdLib\Parameters $request Parameter object representing user
     * request.
     *
     * @return void
     */
    protected function initDateFilters($request)
    {
        return $this->initGenericRangeFilters(
            $request, 'daterange', array($this, 'formatYearForDateRange'),
            array($this, 'buildDateRangeFilter')
        );
    }

    /**
     * Support method for initFilters() -- initialize numeric range filters. Factored
     * out as a separate method so that it can be more easily overridden by child
     * classes.
     *
     * @param \Zend\StdLib\Parameters $request Parameter object representing user
     * request.
     *
     * @return void
     */
    protected function initNumericRangeFilters($request)
    {
        return $this->initGenericRangeFilters(
            $request, 'numericrange', array($this, 'formatValueForNumericRange'),
            array($this, 'buildNumericRangeFilter')
        );
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
        $oldTerms = clone($this->query);
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
        $list = array();
        foreach ($this->getOptions()->getViewOptions() as $key => $value) {
            $list[$key] = array(
                'desc' => $value,
                'selected' => ($key == $this->getView())
            );
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
        $list = array();
        foreach ($valid as $limit) {
            $list[$limit] = array(
                'desc' => $limit,
                'selected' => ($limit == $this->getLimit())
            );
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
        $list = array();
        foreach ($valid as $sort => $desc) {
            $list[$sort] = array(
                'desc' => $desc,
                'selected' => ($sort == $this->getSort())
            );
        }
        return $list;
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
        $this->filterList   = $minified->f;
        $this->searchType   = $minified->ty;

        // Deminified searches will always have defaults already applied;
        // we don't want to accidentally manipulate them further.
        $defaults = $this->getOptions()->getDefaultFilters();
        if (!empty($defaults)) {
            $this->defaultsApplied = true;
        }

        // Search terms, we need to expand keys
        $this->query = QueryAdapter::deminify($minified->t);
    }

    /**
     * Load all available facet settings.  This is mainly useful for showing
     * appropriate labels when an existing search has multiple filters associated
     * with it.
     *
     * @param string $preferredSection Section to favor when loading settings;
     * if multiple sections contain the same facet, this section's description
     * will be favored.
     *
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function activateAllFacets($preferredSection = false)
    {
        // By default, there is only 1 set of facet settings, so this function isn't
        // really necessary.  However, in the Search History screen, we need to
        // use this for Solr-based Search Objects, so we need this dummy method to
        // allow other types of Search Objects to co-exist with Solr-based ones.
        // See the Solr Search Object for details of how this works if you need to
        // implement context-sensitive facet settings in another module.
    }

    /**
     * Override the normal search behavior with an explicit array of IDs that must
     * be retrieved.
     *
     * @param array $ids Record IDs to load
     *
     * @return void
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
     * Get an array of the names of all selected shards.  These should correspond
     * with keys in the array returned by the option class's getShards() method.
     *
     * @return array
     */
    public function getSelectedShards()
    {
        return $this->selectedShards;
    }

    /**
     * Sleep magic method -- the service locator can't be serialized, so we need to
     * exclude it from serialization.  Since we can't obtain a new locator in the
     * __wakeup() method, it needs to be re-injected from outside.
     *
     * @return array
     */
    public function __sleep()
    {
        $vars = get_object_vars($this);
        unset($vars['serviceLocator']);
        $vars = array_keys($vars);
        return $vars;
    }

    /**
     * Set the service locator.
     *
     * @param ServiceLocatorInterface $serviceLocator Locator to register
     *
     * @return Params
     */
    public function setServiceLocator(ServiceLocatorInterface $serviceLocator)
    {
        // If this isn't the top-level manager, get its parent:
        if ($serviceLocator instanceof ServiceLocatorAwareInterface) {
            $serviceLocator = $serviceLocator->getServiceLocator();
        }
        $this->serviceLocator = $serviceLocator;
        return $this;
    }

    /**
     * Get the service locator.
     *
     * @return \Zend\ServiceManager\ServiceLocatorInterface
     */
    public function getServiceLocator()
    {
        return $this->serviceLocator;
    }

    /**
     * Get a database table object.
     *
     * @param string $table Name of table to retrieve
     *
     * @return \VuFind\Db\Table\Gateway
     */
    public function getTable($table)
    {
        return $this->getServiceLocator()->get('VuFind\DbTablePluginManager')
            ->get($table);
    }

    /**
     * Translate a string if a translator is available.
     *
     * @param string $msg Message to translate
     *
     * @return string
     */
    public function translate($msg)
    {
        return $this->getOptions()->translate($msg);
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
     */
    public function getQuery()
    {
        if ($this->overrideQuery) {
            return new Query($this->overrideQuery);
        }
        return $this->query;
    }

    /**
     * Initialize facet settings for the specified configuration sections.
     *
     * @param string $facetList     Config section containing fields to activate
     * @param string $facetSettings Config section containing related settings
     * @param string $cfgFile       Name of configuration to load
     *
     * @return bool                 True if facets set, false if no settings found
     */
    protected function initFacetList($facetList, $facetSettings, $cfgFile = 'facets')
    {
        $config = $this->getServiceLocator()->get('VuFind\Config')->get($cfgFile);
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
}