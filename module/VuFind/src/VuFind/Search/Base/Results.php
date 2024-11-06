<?php

/**
 * Abstract results search model.
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
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Search\Base;

use Laminas\Paginator\Paginator;
use VuFind\Record\Loader;
use VuFind\Search\Factory\UrlQueryHelperFactory;
use VuFindSearch\Service as SearchService;

use function call_user_func_array;
use function count;
use function func_get_args;
use function get_class;
use function in_array;
use function is_callable;
use function is_object;

/**
 * Abstract results search model.
 *
 * This abstract class defines the results methods for modeling a search in VuFind.
 *
 * @category VuFind
 * @package  Search_Base
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
abstract class Results
{
    /**
     * Search parameters
     *
     * @var Params
     */
    protected $params;

    /**
     * Total number of results available
     *
     * @var int
     */
    protected $resultTotal = null;

    /**
     * Search backend identifier.
     *
     * @var string
     */
    protected $backendId;

    /**
     * Override (only for use in very rare cases)
     *
     * @var int
     */
    protected $startRecordOverride = null;

    /**
     * Array of results (represented as Record Driver objects) retrieved on latest
     * search
     *
     * @var array
     */
    protected $results = null;

    /**
     * Any errors reported by the search backend
     *
     * @var array
     */
    protected $errors = null;

    /**
     * An ID number for saving/retrieving search
     *
     * @var int
     */
    protected $searchId = null;

    /**
     * Is this a user-saved search?
     *
     * @var bool
     */
    protected $savedSearch = null;

    /**
     * How frequently will a user be notified about this search (0 = never)?
     *
     * @var int
     */
    protected $notificationFrequency = null;

    /**
     * Query start time
     *
     * @var float
     */
    protected $queryStartTime = null;

    /**
     * Query end time
     *
     * @var float
     */
    protected $queryEndTime = null;

    /**
     * Query time (total)
     *
     * @var float
     */
    protected $queryTime = null;

    /**
     * Helper objects
     *
     * @var array
     */
    protected $helpers = [];

    /**
     * Spelling suggestions
     *
     * @var array
     */
    protected $suggestions = null;

    /**
     * Recommendations
     *
     * @var array
     */
    protected $recommend = [];

    /**
     * Search service.
     *
     * @var SearchService
     */
    protected $searchService;

    /**
     * Record loader
     *
     * @var Loader
     */
    protected $recordLoader;

    /**
     * URL query helper factory
     *
     * @var UrlQueryHelperFactory
     */
    protected $urlQueryHelperFactory = null;

    /**
     * Hierarchical facet helper
     *
     * @var HierarchicalFacetHelperInterface
     */
    protected $hierarchicalFacetHelper = null;

    /**
     * Extra search details.
     *
     * @var ?array
     */
    protected $extraSearchBackendDetails = null;

    /**
     * Constructor
     *
     * @param \VuFind\Search\Base\Params $params        Object representing user
     * search parameters.
     * @param SearchService              $searchService Search service
     * @param Loader                     $recordLoader  Record loader
     */
    public function __construct(
        Params $params,
        SearchService $searchService,
        Loader $recordLoader
    ) {
        $this->setParams($params);
        $this->searchService = $searchService;
        $this->recordLoader = $recordLoader;
    }

    /**
     * Copy constructor
     *
     * @return void
     */
    public function __clone()
    {
        if (is_object($this->params)) {
            $this->params = clone $this->params;
        }
        $this->helpers = [];
    }

    /**
     * Get the search parameters object.
     *
     * @return \VuFind\Search\Base\Params
     */
    public function getParams()
    {
        return $this->params;
    }

    /**
     * Set the search parameters object.
     *
     * @param \VuFind\Search\Base\Params $params Parameters to set
     *
     * @return void
     */
    public function setParams($params)
    {
        $this->params = $params;
    }

    /**
     * Get the search options object.
     *
     * @return \VuFind\Search\Base\Options
     */
    public function getOptions()
    {
        return $this->getParams()->getOptions();
    }

    /**
     * Options for UrlQueryHelper
     *
     * @return array
     */
    protected function getUrlQueryHelperOptions()
    {
        return [];
    }

    /**
     * Get the URL helper for this object.
     *
     * @return \VuFind\Search\UrlQueryHelper
     */
    public function getUrlQuery()
    {
        // Set up URL helper:
        if (!isset($this->helpers['urlQuery'])) {
            $factory = $this->getUrlQueryHelperFactory();
            $this->helpers['urlQuery'] = $factory->fromParams(
                $this->getParams(),
                $this->getUrlQueryHelperOptions()
            );
        }
        return $this->helpers['urlQuery'];
    }

    /**
     * Override a helper object.
     *
     * @param string $key   Name of helper to set
     * @param object $value Helper object
     *
     * @return void
     */
    public function setHelper($key, $value)
    {
        $this->helpers[$key] = $value;
    }

    /**
     * Actually execute the search.
     *
     * @return void
     */
    public function performAndProcessSearch()
    {
        // Initialize variables to defaults (to ensure they don't stay null
        // and cause unnecessary repeat processing):
        // The value of -1 indicates that resultTotal is not available.
        $this->resultTotal = -1;
        $this->results = [];
        $this->suggestions = [];
        $this->errors = [];

        // Run the search:
        $this->startQueryTimer();
        $this->performSearch();
        $this->stopQueryTimer();
    }

    /**
     * Returns the stored list of facets for the last search
     *
     * @param array $filter Array of field => on-screen description listing
     * all of the desired facet fields; set to null to get all configured values.
     *
     * @return array        Facets data arrays
     */
    abstract public function getFacetList($filter = null);

    /**
     * Abstract support method for performAndProcessSearch -- perform a search based
     * on the parameters passed to the object. This method is responsible for
     * filling in all of the key class properties: results, resultTotal, etc.
     *
     * @return void
     */
    abstract protected function performSearch();

    /**
     * Get spelling suggestion information.
     *
     * @return array
     */
    public function getSpellingSuggestions()
    {
        // Not supported by default:
        return [];
    }

    /**
     * Get total count of records in the result set (not just current page).
     *
     * @return int
     */
    public function getResultTotal()
    {
        if (null === $this->resultTotal) {
            $this->performAndProcessSearch();
        }
        return $this->resultTotal;
    }

    /**
     * Manually override the start record number.
     *
     * @param int $rec Record number to use.
     *
     * @return void
     */
    public function overrideStartRecord($rec)
    {
        $this->startRecordOverride = $rec;
    }

    /**
     * Get record number for start of range represented by current result set.
     *
     * @return int
     */
    public function getStartRecord()
    {
        if (null !== $this->startRecordOverride) {
            return $this->startRecordOverride;
        }
        $params = $this->getParams();
        $page = $params->getPage();
        $pageLimit = $params->getLimit();
        $resultLimit = $this->getOptions()->getVisibleSearchResultLimit();
        if ($resultLimit > -1 && $resultLimit < $page * $pageLimit) {
            $page = ceil($resultLimit / $pageLimit);
        }
        return (($page - 1) * $pageLimit) + 1;
    }

    /**
     * Get record number for end of range represented by current result set.
     *
     * @return int
     */
    public function getEndRecord()
    {
        $total = $this->getResultTotal();
        $params = $this->getParams();
        $page = $params->getPage();
        $pageLimit = $params->getLimit();
        $resultLimit = $this->getOptions()->getVisibleSearchResultLimit();

        if ($resultLimit > -1 && $resultLimit < ($page * $pageLimit)) {
            $record = $resultLimit;
        } else {
            $record = $page * $pageLimit;
        }
        // If the end of the current page runs past the last record, use total
        // results; otherwise use the last record on this page:
        return ($record > $total) ? $total : $record;
    }

    /**
     * Basic 'getter' for search results.
     *
     * @return array
     */
    public function getResults()
    {
        if (null === $this->results) {
            $this->performAndProcessSearch();
        }
        return $this->results;
    }

    /**
     * Basic 'getter' for errors.
     *
     * @return array
     */
    public function getErrors()
    {
        if (null === $this->errors) {
            $this->performAndProcessSearch();
        }
        return $this->errors;
    }

    /**
     * Basic 'getter' of search backend identifier.
     *
     * @return string
     */
    public function getBackendId()
    {
        return $this->backendId;
    }

    /**
     * Basic 'getter' for ID of saved search.
     *
     * @return int
     */
    public function getSearchId()
    {
        return $this->searchId;
    }

    /**
     * Is the current search saved in the database?
     *
     * @return bool
     */
    public function isSavedSearch()
    {
        // This data is not available until the search has been saved; blow up if somebody
        // tries to get data that is not yet available.
        if (null === $this->savedSearch) {
            throw new \Exception(
                'Cannot retrieve save status before updateSaveStatus is called.'
            );
        }
        return $this->savedSearch;
    }

    /**
     * How frequently (in days) will the current user be notified about updates to
     * these search results (0 = never)?
     *
     * @return int
     * @throws \Exception
     */
    public function getNotificationFrequency(): int
    {
        // This data is not available until the search has been saved; blow up if somebody
        // tries to get data that is not yet available.
        if (null === $this->notificationFrequency) {
            throw new \Exception(
                'Cannot retrieve notification frequency before updateSaveStatus is called.'
            );
        }
        return $this->notificationFrequency;
    }

    /**
     * Given a database row corresponding to the current search object,
     * mark whether this search is saved and what its database ID is.
     *
     * @param SearchEntityInterface $row Relevant database row.
     *
     * @return void
     */
    public function updateSaveStatus($row)
    {
        $this->searchId = $row->getId();
        foreach ($this->results as $driver) {
            $driver->setExtraDetail('searchId', $this->searchId);
        }
        $this->savedSearch = $row->getSaved();
        $this->notificationFrequency = $this->savedSearch ? $row->getNotificationFrequency() : 0;
    }

    /**
     * Start the timer to figure out how long a query takes. Complements
     * stopQueryTimer().
     *
     * @return void
     */
    protected function startQueryTimer()
    {
        // Get time before the query
        $time = explode(' ', microtime());
        $this->queryStartTime = $time[1] + $time[0];
    }

    /**
     * End the timer to figure out how long a query takes. Complements
     * startQueryTimer().
     *
     * @return void
     */
    protected function stopQueryTimer()
    {
        $time = explode(' ', microtime());
        $this->queryEndTime = $time[1] + $time[0];
        $this->queryTime = $this->queryEndTime - $this->queryStartTime;
    }

    /**
     * Basic 'getter' for query speed.
     *
     * @return float
     */
    public function getQuerySpeed()
    {
        if (null === $this->queryTime) {
            $this->performAndProcessSearch();
        }
        return $this->queryTime;
    }

    /**
     * Basic 'getter' for query start time.
     *
     * @return float
     */
    public function getStartTime()
    {
        if (null === $this->queryStartTime) {
            $this->performAndProcessSearch();
        }
        return $this->queryStartTime;
    }

    /**
     * Get a paginator for the result set.
     *
     * @return Paginator
     */
    public function getPaginator()
    {
        // If there is a limit on how many pages are accessible,
        // apply that limit now:
        $max = $this->getOptions()->getVisibleSearchResultLimit();
        $total = $this->getResultTotal();
        if ($max > 0 && $total > $max) {
            $total = $max;
        }

        // Build the standard paginator control:
        $nullAdapter = "Laminas\Paginator\Adapter\NullFill";
        $paginator = new Paginator(new $nullAdapter($total));
        $paginator->setCurrentPageNumber($this->getParams()->getPage())
            ->setItemCountPerPage($this->getParams()->getLimit())
            ->setPageRange(11);
        return $paginator;
    }

    /**
     * Basic 'getter' for suggestion list.
     *
     * @return array
     */
    public function getRawSuggestions()
    {
        if (null === $this->suggestions) {
            $this->performAndProcessSearch();
        }
        return $this->suggestions;
    }

    /**
     * Get the scores of the results
     *
     * @return array
     */
    public function getScores()
    {
        // Not implemented in the base class
        return [];
    }

    /**
     * Getting the highest relevance of all the results
     *
     * @return ?float
     */
    public function getMaxScore()
    {
        // Not implemented in the base class
        return null;
    }

    /**
     * Get extra data for the search.
     *
     * Extra data can be used to store local implementation-specific information.
     * Contents must be serializable. It is recommended to make the array as small
     * as possible.
     *
     * @return array
     */
    public function getExtraData(): array
    {
        // Not implemented in the base class
        return [];
    }

    /**
     * Set extra data for the search.
     *
     * @param array $data Extra data
     *
     * @return void
     */
    public function setExtraData(array $data): void
    {
        // Not implemented in the base class
        if (!empty($data)) {
            error_log(get_class($this) . ': Extra data passed but not handled');
        }
    }

    /**
     * Add settings to a minified object.
     *
     * @param \VuFind\Search\Minified $minified Minified Search Object
     *
     * @return void
     */
    public function minify(&$minified): void
    {
        $minified->id = $this->getSearchId();
        $minified->i  = $this->getStartTime();
        $minified->s  = $this->getQuerySpeed();
        $minified->r  = $this->getResultTotal();
        $minified->ex = $this->getExtraData();

        $this->getParams()->minify($minified);
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
        $this->searchId = $minified->id;
        $this->queryStartTime = $minified->i;
        $this->queryTime = $minified->s;
        $this->resultTotal = $minified->r;
        $this->setExtraData($minified->ex);

        $this->getParams()->deminify($minified);
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
        if (null === $location) {
            return $this->recommend;
        }
        return $this->recommend[$location] ?? [];
    }

    /**
     * Set the recommendation objects (see \VuFind\Search\RecommendListener).
     *
     * @param array $recommend Recommendations
     *
     * @return void
     */
    public function setRecommendations($recommend)
    {
        $this->recommend = $recommend;
    }

    /**
     * Return search service.
     *
     * @return SearchService
     *
     * @todo May better error handling, throw a custom exception if search service
     * not present
     */
    protected function getSearchService()
    {
        return $this->searchService;
    }

    /**
     * Translate a string if a translator is available (proxies method in Options).
     *
     * @return string
     */
    public function translate()
    {
        return call_user_func_array(
            [$this->getOptions(), 'translate'],
            func_get_args()
        );
    }

    /**
     * Get URL query helper factory
     *
     * @return UrlQueryHelperFactory
     */
    protected function getUrlQueryHelperFactory()
    {
        if (null === $this->urlQueryHelperFactory) {
            $this->urlQueryHelperFactory = new UrlQueryHelperFactory();
        }
        return $this->urlQueryHelperFactory;
    }

    /**
     * Set URL query helper factory
     *
     * @param UrlQueryHelperFactory $factory UrlQueryHelperFactory object
     *
     * @return void
     */
    public function setUrlQueryHelperFactory(UrlQueryHelperFactory $factory)
    {
        $this->urlQueryHelperFactory = $factory;
    }

    /**
     * Set hierarchical facet helper
     *
     * @param HierarchicalFacetHelperInterface $helper Hierarchical facet helper
     *
     * @return void
     */
    public function setHierarchicalFacetHelper(
        HierarchicalFacetHelperInterface $helper
    ) {
        $this->hierarchicalFacetHelper = $helper;
    }

    /**
     * Get complete facet counts for several index fields
     *
     * @param array  $facetfields  name of the Solr fields to return facets for
     * @param bool   $removeFilter Clear existing filters from selected fields (true)
     * or retain them (false)?
     * @param int    $limit        A limit for the number of facets returned, this
     * may be useful for very large amounts of facets that can break the JSON parse
     * method because of PHP out of memory exceptions (default = -1, no limit).
     * @param string $facetSort    A facet sort value to use (null to retain current)
     *
     * @return array an array with the facet values for each index field
     */
    public function getFullFieldFacets(
        $facetfields,
        $removeFilter = true,
        $limit = -1,
        $facetSort = null
    ) {
        if (!method_exists($this, 'getPartialFieldFacets')) {
            throw new \Exception('getPartialFieldFacets not implemented');
        }
        $page = 1;
        $facets = [];
        do {
            $facetpage = $this->getPartialFieldFacets(
                $facetfields,
                $removeFilter,
                $limit,
                $facetSort,
                $page
            );
            $nextfields = [];
            foreach ($facetfields as $field) {
                if (!empty($facetpage[$field]['data']['list'])) {
                    if (!isset($facets[$field])) {
                        $facets[$field] = $facetpage[$field];
                        $facets[$field]['more'] = false;
                    } else {
                        $facets[$field]['data']['list'] = array_merge(
                            $facets[$field]['data']['list'],
                            $facetpage[$field]['data']['list']
                        );
                    }
                    if ($facetpage[$field]['more'] !== false) {
                        $nextfields[] = $field;
                    }
                }
            }
            $facetfields = $nextfields;
            $page++;
        } while ($limit == -1 && !empty($facetfields));
        return $facets;
    }

    /**
     * Get the extra search details
     *
     * @return ?array
     */
    public function getExtraSearchBackendDetails()
    {
        return $this->extraSearchBackendDetails;
    }

    /**
     * A helper method that converts the list of facets for the last search from
     * RecordCollection's facet list.
     *
     * @param array $facetList Facet list
     * @param array $filter    Array of field => on-screen description listing
     * all of the desired facet fields; set to null to get all configured values.
     *
     * @return array Facets data arrays
     */
    protected function buildFacetList(array $facetList, array $filter = null): array
    {
        // If there is no filter, we'll use all facets as the filter:
        if (null === $filter) {
            $filter = $this->getParams()->getFacetConfig();
        }

        // Start building the facet list:
        $result = [];

        // Loop through every field returned by the result set
        $translatedFacets = $this->getOptions()->getTranslatedFacets();
        $hierarchicalFacets
            = is_callable([$this->getOptions(), 'getHierarchicalFacets'])
            ? $this->getOptions()->getHierarchicalFacets()
            : [];
        $hierarchicalFacetSortSettings
            = is_callable([$this->getOptions(), 'getHierarchicalFacetSortSettings'])
            ? $this->getOptions()->getHierarchicalFacetSortSettings()
            : [];

        foreach (array_keys($filter) as $field) {
            $data = $facetList[$field] ?? [];
            // Skip empty arrays:
            if (count($data) < 1) {
                continue;
            }
            // Initialize the settings for the current field
            $result[$field] = [
                'label' => $filter[$field],
                'list' => [],
            ];
            // Should we translate values for the current facet?
            $translate = in_array($field, $translatedFacets);
            $hierarchical = in_array($field, $hierarchicalFacets);
            $operator = $this->getParams()->getFacetOperator($field);
            $resultList = [];
            // Loop through values:
            foreach ($data as $value => $count) {
                $displayText = $this->getParams()
                    ->getFacetValueRawDisplayText($field, $value);
                if ($hierarchical) {
                    if (!$this->hierarchicalFacetHelper) {
                        throw new \Exception(
                            get_class($this)
                            . ': hierarchical facet helper unavailable'
                        );
                    }
                    $displayText = $this->hierarchicalFacetHelper
                        ->formatDisplayText($displayText);
                }
                $displayText = $translate
                    ? $this->getParams()->translateFacetValue($field, $displayText)
                    : $displayText;
                $isApplied = $this->getParams()->hasFilter("$field:" . $value)
                    || $this->getParams()->hasFilter("~$field:" . $value);

                // Store the collected values:
                $resultList[] = compact(
                    'value',
                    'displayText',
                    'count',
                    'operator',
                    'isApplied'
                );
            }

            if ($hierarchical) {
                $sort = $hierarchicalFacetSortSettings[$field]
                    ?? $hierarchicalFacetSortSettings['*'] ?? 'count';
                $this->hierarchicalFacetHelper->sortFacetList($resultList, $sort);

                $resultList
                    = $this->hierarchicalFacetHelper->buildFacetArray($field, $resultList);
            }

            $result[$field]['list'] = $resultList;
        }
        return $result;
    }
}
