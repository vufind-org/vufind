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
 * @package  SearchObject
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
namespace VuFind\Search\Base;
use VuFind\Config\Reader as ConfigReader, VuFind\Search\Options as SearchOptions,
    VuFind\Translator;

/**
 * Abstract parameters search model.
 *
 * This abstract class defines the parameters methods for modeling a search in VuFind
 *
 * @category VuFind2
 * @package  SearchObject
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class Params
{
    // Search terms
    protected $searchTerms = array();
    // Page number
    protected $page = 1;
    // Sort settings
    protected $sort = null;
    protected $skipRssSort = false;
    // Result limit
    protected $limit = 20;
    protected $searchType  = 'basic';
    // Shards
    protected $selectedShards = array();
    // View
    protected $view = null;
    // VF_Search_Base_Options subclass
    protected $options;
    // Recommendation settings
    protected $recommend = array();
    protected $recommendationEnabled = false;
    // Facet settings
    protected $facetConfig = array();
    protected $checkboxFacets = array();
    protected $filterList = array();

    /**
     * Constructor
     *
     * @param VF_Search_Base_Options $options Options to use (null to load defaults)
     */
    public function __construct($options = null)
    {
        if (is_null($options)) {
            // Create a copy of the default configuration:
            $this->options = clone(
                SearchOptions::getInstance($this->getSearchClassId())
            );
        } else {
            $this->options = $options;
        }
    }

    /**
     * Copy constructor
     *
     * @return void
     */
    public function __clone()
    {
        $this->options = clone($this->options);
    }

    /**
     * Get the identifier used for naming the various search classes in this family.
     *
     * @return string
     */
    public function getSearchClassId()
    {
        return SearchOptions::extractSearchClassId(get_class($this));
    }

    /**
     * External method caller (proxies unrecognized methods to options object)
     *
     * @param string $methodName Name of function trying to be called
     * @param Array  $params     Parameters to be sent to the function
     *
     * @return mixed
     */
    public function __call($methodName, $params)
    {
        // Proxy undefined methods to the options object:
        $method = array($this->options, $methodName);
        if (!is_callable($method)) {
            throw new Exception($methodName . ' cannot be called.');
        }
        return call_user_func_array($method, $params);
    }


    /**
     * Pull the search parameters
     *
     * @param Zend_Controller_Request_Abstract $request the search object
     *
     * @return string
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
        $this->rememberLastLimit($this->getLimit());
        $this->rememberLastSort($this->getSort());
    }

    /**
     * Pull shard parameters from the request or set defaults
     *
     * @param Zend_Controller_Request_Abstract $request the search object
     *
     * @return void
     */
    protected function initShards($request)
    {
        $legalShards = array_keys($this->options->getShards());
        $requestShards = $request->getParam('shard', array());
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
            $this->selectedShards = $this->options->getDefaultSelectedShards();
        }
    }

    /**
     * Pull the page size parameter or set to default
     *
     * @param Zend_Controller_Request_Abstract $request the search object
     *
     * @return void
     */
    protected function initLimit($request)
    {
        // Check for a limit parameter in the url.
        $defaultLimit = $this->options->getDefaultLimit();
        if (($limit = $request->getParam('limit')) != $defaultLimit) {
            // make sure the url parameter is a valid limit
            if (in_array($limit, $this->options->getLimitOptions())) {
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
     * @param Zend_Controller_Request_Abstract $request the search object
     *
     * @return void
     */
    protected function initPage($request)
    {
        $this->page = intval($request->getParam('page'));
        if ($this->page < 1) {
            $this->page = 1;
        }
    }

    /**
     * Initialize the object's search settings from a request object.
     *
     * @param Zend_Controller_Request_Abstract $request A Zend request object.
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

        // If no search parameters were found, default to an "all records" search:
        if (count($this->searchTerms) == 0) {
            // Treat it as an empty basic search
            $this->setBasicSearch('');
        }
    }

    /**
     * Support method for initSearch() -- handle basic settings.
     *
     * @param Zend_Controller_Request_Abstract $request A Zend request object.
     *
     * @return boolean True if search settings were found, false if not.
     */
    protected function initBasicSearch($request)
    {
        // If no lookfor parameter was found, we have no search terms to
        // add to our array!
        if (is_null($lookfor = $request->getParam('lookfor'))) {
            return false;
        }

        // If lookfor is an array, we may be dealing with a legacy Advanced
        // Search URL.  If there's only one parameter, we can flatten it,
        // but otherwise we should treat it as an error -- no point in going
        // to great lengths for compatibility.
        if (is_array($lookfor)) {
            if (count($lookfor) > 1) {
                throw new Exception("Unsupported search URL.");
            }
            $lookfor = $lookfor[0];
        }

        // Flatten type arrays for backward compatibility:
        $handler = $request->getParam('type');
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
            $handler = $this->options->getDefaultHandler();
        }

        $this->searchTerms = array(
            array(
                'index'   => $handler,
                'lookfor' => $lookfor
            )
        );
    }

    /**
     * Support method for initSearch() -- handle advanced settings.  Advanced
     * searches have numeric subscripts on the lookfor and type parameters --
     * this is how they are distinguished from basic searches.
     *
     * @param Zend_Controller_Request_Abstract $request A Zend request object.
     *
     * @return void
     */
    protected function initAdvancedSearch($request)
    {
        $this->searchType = 'advanced';

        $groupCount = 0;
        // Loop through each search group
        while (!is_null($lookfor = $request->getParam('lookfor' . $groupCount))) {
            $group = array();
            // Loop through each term inside the group
            for ($i = 0; $i < count($lookfor); $i++) {
                // Ignore advanced search fields with no lookup
                if ($lookfor[$i] != '') {
                    // Use default fields if not set
                    $typeArr = $request->getParam('type' . $groupCount);
                    if (isset($typeArr[$i]) && !empty($typeArr[$i])) {
                        $handler = $typeArr[$i];
                    } else {
                        $handler = $this->options->getDefaultHandler();
                    }

                    // Add term to this group
                    $boolArr = $request->getParam('bool' . $groupCount);
                    $group[] = array(
                        'field'   => $handler,
                        'lookfor' => $lookfor[$i],
                        'bool'    => isset($boolArr[0]) ? $boolArr[0] : null
                    );
                }
            }

            // Make sure we aren't adding groups that had no terms
            if (count($group) > 0) {
                // Add the completed group to the list
                $this->searchTerms[] = array(
                    'group' => $group,
                    'join'  => $request->getParam('join')
                );
            }

            // Increment
            $groupCount++;
        }
    }

    /**
     * Get the value for which type of sorting to use
     *
     * @param Zend_Controller_Request_Abstract $request A Zend request object.
     *
     * @return string
     */
    protected function initSort($request)
    {
        // Check for special parameter only relevant in RSS mode:
        if ($request->getParam('skip_rss_sort', 'unset') != 'unset') {
            $this->skipRssSort = true;
        }
        $this->setSort($request->getParam('sort'));
    }

    /**
     * Get the value for which results view to use
     *
     * @param Zend_Controller_Request_Abstract $request A Zend request object.
     *
     * @return string
     */
    protected function initView($request)
    {
        // Check for a view parameter in the url.
        $view = $request->getParam('view');
        $lastView = $this->getLastView();
        if (!empty($view)) {
            if ($view == 'rss') {
                // we don't want to store rss in the Session
                $this->setView('rss');
            } else {
                // store non-rss views in Session for persistence
                $validViews = $this->getViewOptions();
                // make sure the url parameter is a valid view
                if (in_array($view, array_keys($validViews))) {
                    $this->setView($view);
                    $this->rememberLastView($view);
                } else {
                    $this->setView($this->getDefaultView());
                }
            }
        } else if (!empty($lastView)) {
            // if there is nothing in the URL, check the Session
            $this->setView($lastView);
        } else {
            // otherwise load the default
            $this->setView($this->getDefaultView());
        }
    }

    /**
     * Return the default sorting value
     *
     * @return string
     */
    public function getDefaultSort()
    {
        return $this->getDefaultSortByHandler($this->getSearchHandler());
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
     * @return int
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
        $valid = array_keys($this->getSortOptions());
        if (!empty($sort) && in_array($sort, $valid)) {
            $this->sort = $sort;
        } else {
            $this->sort = $this->getDefaultSort();
        }

        // In RSS mode, we may want to adjust sort settings:
        if (!$this->skipRssSort && $this->getView() == 'rss') {
            $this->sort = $this->getRssSort($this->sort);
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
        if (count($this->searchTerms) == 1
            && isset($this->searchTerms[0]['index'])
        ) {
            return $this->searchTerms[0]['index'];
        }
        return null;
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
     * Return the search terms
     *
     * @return string
     */
    public function getSearchTerms()
    {
        return $this->searchTerms;
    }

    /**
     * Return the value for which search view we use
     *
     * @return string
     */
    public function getView()
    {
        return is_null($this->view) ? $this->getDefaultView() : $this->view;
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
     * Get a human-readable presentation version of the advanced search query
     * stored in the object.  This will not work if $this->searchType is not
     * 'advanced.'
     *
     * @return string
     */
    protected function buildAdvancedDisplayQuery()
    {
        // Groups and exclusions. This mirrors some logic in Solr.php
        $groups   = array();
        $excludes = array();

        foreach ($this->searchTerms as $search) {
            $thisGroup = array();
            // Process each search group
            if (isset($search['group'])) {
                foreach ($search['group'] as $group) {
                    // Build this group individually as a basic search
                    $thisGroup[] = $this->getHumanReadableFieldName($group['field'])
                        . ":{$group['lookfor']}";
                }
            }
            // Is this an exclusion (NOT) group or a normal group?
            if (isset($search['group'][0]['bool'])
                && $search['group'][0]['bool'] == 'NOT'
            ) {
                $excludes[]
                    = join(' ' . Translator::translate('OR') . ' ', $thisGroup);
            } else if (isset($search['group'][0]['bool'])) {
                $groups[] = join(
                    " " . Translator::translate($search['group'][0]['bool'])." ",
                    $thisGroup
                );
            }
        }

        // Base 'advanced' query
        $output = '';
        if (isset($this->searchTerms[0]['join'])) {
            $output .= "(" .
                join(
                    ") " .
                    Translator::translate($this->searchTerms[0]['join']) . " (",
                    $groups
                ) .
                ")";
        }

        // Concatenate exclusion after that
        if (count($excludes) > 0) {
            $output .= ' ' .
                Translator::translate('NOT') . ' ((' .
                join(') ' . Translator::translate('OR') . ' (', $excludes) . "))";
        }

        return $output;
    }

    /**
     * Build a string for onscreen display showing the
     *   query used in the search (not the filters).
     *
     * @return string user friendly version of 'query'
     */
    public function getDisplayQuery()
    {
        if ($this->searchType == 'advanced') {
            return $this->buildAdvancedDisplayQuery();
        }

        // Default -- Basic search:
        return isset($this->searchTerms[0]['lookfor'])
            ? $this->searchTerms[0]['lookfor'] : '';
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
        $searchSettings = ConfigReader::getConfig($this->getSearchIni());

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
     * @param Zend_Controller_Request_Abstract $request Zend request object
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

        // Process recommendations for each location:
        $this->recommend = array(
            'top' => array(), 'side' => array(), 'noresults' => array()
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
                $class = 'VuFind\\Recommend\\' . $module;
                $params = implode(':', $current);

                // Build a recommendation module with the provided settings.
                if (@class_exists($class)) {
                    $obj = new $class($params);
                    $obj->init($this, $request);
                    $this->recommend[$location][] = $obj;
                } else {
                    throw new Exception(
                        'Could not load recommendation module: ' . $class
                    );
                }
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
            for ($i = 0; $i < count($this->filterList[$field]); $i++) {
                // Does it contain the value we don't want?
                if ($this->filterList[$field][$i] == $value) {
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
     *
     * @return void
     */
    public function addFacet($newField, $newAlias = null)
    {
        if ($newAlias == null) {
            $newAlias = $newField;
        }
        $this->facetConfig[$newField] = $newAlias;
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
     * Return an array structure containing all current filters
     *    and urls to remove them.
     *
     * @param bool $excludeCheckboxFilters Should we exclude checkbox filters from
     * the list (to be used as a complement to getCheckboxFacets()).
     *
     * @return array                       Field, values and translation status
     */
    public function getFilterList($excludeCheckboxFilters = false)
    {
        // Get a list of checkbox filters to skip if necessary:
        $skipList = array();
        if ($excludeCheckboxFilters) {
            foreach ($this->checkboxFacets as $current) {
                list($field, $value) = $this->parseFilter($current['filter']);
                if (!isset($skipList[$field])) {
                    $skipList[$field] = array();
                }
                $skipList[$field][] = $value;
            }
        }

        $list = array();
        // Loop through all the current filter fields
        foreach ($this->filterList as $field => $values) {
            // and each value currently used for that field
            $translate = in_array($field, $this->getTranslatedFacets());
            foreach ($values as $value) {
                // Add to the list unless it's in the list of fields to skip:
                if (!isset($skipList[$field])
                    || !in_array($value, $skipList[$field])
                ) {
                    $facetLabel = $this->getFacetLabel($field);
                    $list[$facetLabel][] = array(
                        'value'       => $value,
                        'displayText' =>
                            $translate ? Translator::translate($value) : $value,
                        'field'       => $field
                    );
                }
            }
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
        // Make sure that $to is less than $from:
        if ($to != '*' && $from!= '*' && $to < $from) {
            $tmp = $to;
            $to = $from;
            $from = $tmp;
        }

        // Assume Solr syntax -- this should be overridden in child classes where
        // other indexing methodologies are used.
        return "{$field}:[{$from} TO {$to}]";
    }

    /**
     * Support method for initFilters() -- initialize date-related filters.  Factored
     * out as a separate method so that it can be more easily overridden by child
     * classes.
     *
     * @param Zend_Controller_Request_Abstract $request A Zend request object.
     *
     * @return void
     */
    protected function initDateFilters($request)
    {
        $daterange = $request->getParam('daterange');
        if (!empty($daterange)) {
            $ranges = is_array($daterange) ? $daterange : array($daterange);
            foreach ($ranges as $range) {
                // Validate start and end of range:
                $yearFrom = $this->formatYearForDateRange(
                    $request->getParam($range . 'from')
                );
                $yearTo = $this->formatYearForDateRange(
                    $request->getParam($range . 'to')
                );

                // Build filter only if necessary:
                if (!empty($range) && ($yearFrom != '*' || $yearTo != '*')) {
                    $dateFilter
                        = $this->buildDateRangeFilter($range, $yearFrom, $yearTo);
                    $this->addFilter($dateFilter);
                }
            }
        }
    }

    /**
     * Add filters to the object based on values found in the request object.
     *
     * @param Zend_Controller_Request_Abstract $request A Zend request object.
     *
     * @return void
     */
    protected function initFilters($request)
    {
        // Handle standard filters:
        $filter = $request->getParam('filter');
        if (!empty($filter)) {
            if (is_array($filter)) {
                foreach ($filter as $current) {
                    $this->addFilter($current);
                }
            } else {
                $this->addFilter($filter);
            }
        }

        // Handle date range filters:
        $this->initDateFilters($request);
    }

    /**
     * Replace a search term in the query
     *
     * @param string $from Search term to find
     * @param string $to   Search term to insert
     *
     * @return void
     */
    public function replaceSearchTerm($from, $to)
    {
        // Escape $from so it is regular expression safe (just in case it
        // includes any weird punctuation -- unlikely but possible):
        $from = addcslashes($from, '\^$.[]|()?*+{}/');

        // If our "from" pattern contains non-word characters, we can't use word
        // boundaries for matching.  We want to try to use word boundaries when
        // possible, however, to avoid the replacement from affecting unexpected
        // parts of the search query.
        if (!preg_match('/.*[^\w].*/', $from)) {
            $pattern = "/\b$from\b/i";
        } else {
            $pattern = "/$from/i";
        }

        // Advanced search
        if (isset($this->searchTerms[0]['group'])) {
            for ($i = 0; $i < count($this->searchTerms); $i++) {
                for ($j = 0; $j < count($this->searchTerms[$i]['group']); $j++) {
                    $this->searchTerms[$i]['group'][$j]['lookfor']
                        = preg_replace(
                            $pattern, $to,
                            $this->searchTerms[$i]['group'][$j]['lookfor']
                        );
                }
            }
        } else {
            // Basic search
            for ($i = 0; $i < count($this->searchTerms); $i++) {
                // Perform the replacement:
                $this->searchTerms[$i]['lookfor'] = preg_replace(
                    $pattern, $to, $this->searchTerms[$i]['lookfor']
                );
            }
        }
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
        $oldTerms = $this->searchTerms;
        // Replace the search term
        $this->replaceSearchTerm($oldTerm, $newTerm);
        // Get the new query string
        $query = $this->getDisplayQuery();
        // Restore the old data
        $this->searchTerms = $oldTerms;
        // Return the query string
        return $query;
    }

    /**
     * Find a word amongst the current search terms
     *
     * @param string $needle Search term to find
     *
     * @return bool          True/False if the word was found
     */
    public function findSearchTerm($needle)
    {
        // Escape slashes in $needle to avoid regular expression errors:
        $needle = str_replace('/', '\/', $needle);

        // Advanced search
        if (isset($this->searchTerms[0]['group'])) {
            foreach ($this->searchTerms as $group) {
                foreach ($group['group'] as $search) {
                    if (preg_match("/\b$needle\b/", $search['lookfor'])) {
                        return true;
                    }
                }
            }
        } else {
            // Basic search
            foreach ($this->searchTerms as $haystack) {
                if (preg_match("/\b$needle\b/", $haystack['lookfor'])) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Extract all the keywords from the advanced search as a string.
     *
     * @return string
     */
    public function extractAdvancedTerms()
    {
        $terms = array();
        foreach ($this->searchTerms as $current) {
            if (isset($current['lookfor'])) {
                $terms[] = $current['lookfor'];
            } else if (isset($current['group']) && is_array($current['group'])) {
                foreach ($current['group'] as $subCurrent) {
                    if (isset($subCurrent['lookfor'])) {
                        $terms[] = $subCurrent['lookfor'];
                    }
                }
            }
        }
        return implode(' ', $terms);
    }

    /**
     * Basic 'getter' for list of available view options.
     *
     * @return array
     */
    public function getViewList()
    {
        $list = array();
        foreach ($this->getViewOptions() as $key => $value) {
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
        $valid = $this->getLimitOptions();
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
        $valid = $this->getSortOptions();
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
     * @param VF_MS $minified Minified Search Object
     *
     * @return void
     */
    public function deminify($minified)
    {
        // Some values will transfer without changes
        $this->filterList   = $minified->f;
        $this->searchType   = $minified->ty;

        // Search terms, we need to expand keys
        $tempTerms = $minified->t;
        foreach ($tempTerms as $term) {
            $newTerm = array();
            foreach ($term as $k => $v) {
                switch ($k) {
                case 'j':
                    $newTerm['join']    = $v;
                    break;
                case 'i':
                    $newTerm['index']   = $v;
                    break;
                case 'l':
                    $newTerm['lookfor'] = $v;
                    break;
                case 'g':
                    $newTerm['group'] = array();
                    foreach ($v as $line) {
                        $search = array();
                        foreach ($line as $k2 => $v2) {
                            switch ($k2) {
                            case 'b':
                                $search['bool']    = $v2;
                                break;
                            case 'f':
                                $search['field']   = $v2;
                                break;
                            case 'l':
                                $search['lookfor'] = $v2;
                                break;
                            }
                        }
                        $newTerm['group'][] = $search;
                    }
                    break;
                }
            }
            $this->searchTerms[] = $newTerm;
        }
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
     */
    public function activateAllFacets($preferredSection = false)
    {
        // By default, there is only set of facet settings, so this function isn't
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
     */
    public function setQueryIDs($ids)
    {
        // This needs to be defined in child classes:
        throw new Exception(get_class($this) . ' does not support setQueryIDs().');
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
}