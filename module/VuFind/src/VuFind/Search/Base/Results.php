<?php
/**
 * Abstract results search model.
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
use VuFind\Search\UrlQueryHelper, Zend\Paginator\Paginator,
    Zend\ServiceManager\ServiceLocatorAwareInterface,
    Zend\ServiceManager\ServiceLocatorInterface;

/**
 * Abstract results search model.
 *
 * This abstract class defines the results methods for modeling a search in VuFind.
 *
 * @category VuFind2
 * @package  Search_Base
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
abstract class Results implements ServiceLocatorAwareInterface
{
    protected $params;
    // Total number of results available
    protected $resultTotal = null;
    // Override (only for use in very rare cases)
    protected $startRecordOverride = null;
    // Array of results (represented as Record Driver objects) retrieved on latest
    // search:
    protected $results = null;
    // An ID number for saving/retrieving search
    protected $searchId = null;
    protected $savedSearch = null;
    // STATS
    protected $queryStartTime = null;
    protected $queryEndTime = null;
    protected $queryTime = null;
    // Helper objects
    protected $helpers = array();
    // Spelling
    protected $suggestions = null;

    /**
     * Service locator
     *
     * @var ServiceLocatorInterface
     */
    protected $serviceLocator;

    /**
     * Constructor
     *
     * @param \VuFind\Search\Base\Params $params Object representing user search
     * parameters.
     */
    public function __construct(Params $params)
    {
        // Save the parameters, then perform the search:
        $this->setParams($params);
    }

    /**
     * Copy constructor
     *
     * @return void
     */
    public function __clone()
    {
        if (is_object($this->params)) {
            $this->params = clone($this->params);
        }
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
     * Get the URL helper for this object.
     *
     * @return UrlHelper
     */
    public function getUrlQuery()
    {
        // Set up URL helper:
        if (!isset($this->helpers['urlQuery'])) {
            $this->helpers['urlQuery'] = new UrlQueryHelper($this);
        }
        return $this->helpers['urlQuery'];
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
        $this->resultTotal = 0;
        $this->results = array();
        $this->suggestions = array();

        // Run the search:
        $this->startQueryTimer();
        $this->performSearch();
        $this->stopQueryTimer();

        // Process recommendations:
        $recommendations = $this->getParams()->getRecommendations(null);
        if (is_array($recommendations)) {
            foreach ($recommendations as $currentSet) {
                foreach ($currentSet as $current) {
                    $current->process($this);
                }
            }
        }
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
     * on the parameters passed to the object.  This method is responsible for
     * filling in all of the key class properties: _results, _resultTotal, etc.
     *
     * @return void
     */
    abstract protected function performSearch();

    /**
     * Method to retrieve a record by ID.  Returns a record driver object.
     *
     * @param string $id Unique identifier of record
     *
     * @return \VuFind\RecordDriver\AbstractBase
     */
    public function getRecord($id)
    {
        // This needs to be defined in subclasses:
        throw new \Exception('getRecord needs to be defined.');
    }

    /**
     * Method to retrieve an array of records by ID.
     *
     * @param array $ids Array of unique record identifiers.
     *
     * @return array
     */
    public function getRecords($ids)
    {
        // This is the default, dumb behavior for retrieving multiple records --
        // just call getRecord() repeatedly.  For efficiency, this method should
        // be overridden in child classes when possible to reduce API calls, etc.
        $retVal = array();
        foreach ($ids as $id) {
            try {
                $retVal[] = $this->getRecord($id);
            } catch (\Exception $e) {
                // Just omit missing records from the return array; calling code in
                // \VuFind\Record\Loader::loadBatch() will deal with this.
            }
        }
        return $retVal;
    }

    /**
     * Get spelling suggestion information.
     *
     * @return array
     */
    public function getSpellingSuggestions()
    {
        // Not supported by default:
        return array();
    }

    /**
     * Get total count of records in the result set (not just current page).
     *
     * @return int
     */
    public function getResultTotal()
    {
        if (is_null($this->resultTotal)) {
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
        if (!is_null($this->startRecordOverride)) {
            return $this->startRecordOverride;
        }
        $params = $this->getParams();
        return (($params->getPage() - 1) * $params->getLimit()) + 1;
    }

    /**
     * Get record number for end of range represented by current result set.
     *
     * @return int
     */
    public function getEndRecord()
    {
        $total = $this->getResultTotal();
        $limit = $this->getParams()->getLimit();
        $page = $this->getParams()->getPage();
        if ($page * $limit > $total) {
            // The end of the current page runs past the last record, use total
            // results
            return $total;
        } else {
            // Otherwise use the last record on this page
            return $page * $limit;
        }
    }

    /**
     * Basic 'getter' for search results.
     *
     * @return array
     */
    public function getResults()
    {
        if (is_null($this->results)) {
            $this->performAndProcessSearch();
        }
        return $this->results;
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
        // This data is not available until \VuFind\Db\Table\Search::saveSearch()
        // is called...  blow up if somebody tries to get data that is not yet
        // available.
        if (is_null($this->savedSearch)) {
            throw new \Exception(
                'Cannot retrieve save status before updateSaveStatus is called.'
            );
        }
        return $this->savedSearch;
    }

    /**
     * Given a database row corresponding to the current search object,
     * mark whether this search is saved and what its database ID is.
     *
     * @param \VuFind\Db\Row\Search $row Relevant database row.
     *
     * @return void
     */
    public function updateSaveStatus($row)
    {
        $this->searchId = $row->id;
        $this->savedSearch = ($row->saved == true);
    }

    /**
     * Start the timer to figure out how long a query takes.  Complements
     * stopQueryTimer().
     *
     * @return void
     */
    protected function startQueryTimer()
    {
        // Get time before the query
        $time = explode(" ", microtime());
        $this->queryStartTime = $time[1] + $time[0];
    }

    /**
     * End the timer to figure out how long a query takes.  Complements
     * startQueryTimer().
     *
     * @return void
     */
    protected function stopQueryTimer()
    {
        $time = explode(" ", microtime());
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
        if (is_null($this->queryTime)) {
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
        if (is_null($this->queryStartTime)) {
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
        $nullAdapter = "Zend\Paginator\Adapter\Null";
        $paginator = new Paginator(new $nullAdapter($total));
        $paginator->setCurrentPageNumber($this->getParams()->getPage())
            ->setItemCountPerPage($this->getParams()->getLimit())
            ->setPageRange(11);
        return $paginator;
    }

    /**
     * Input Tokenizer - Specifically for spelling purposes
     *
     * Because of its focus on spelling, these tokens are unsuitable
     * for actual searching. They are stripping important search data
     * such as joins and groups, simply because they don't need to be
     * spellchecked.
     *
     * @param string $input Query to tokenize
     *
     * @return array        Tokenized array
     */
    public function spellingTokens($input)
    {
        $joins = array("AND", "OR", "NOT");
        $paren = array("(" => "", ")" => "");

        // Base of this algorithm comes straight from
        // PHP doco examples & benighted at gmail dot com
        // http://php.net/manual/en/function.strtok.php
        $tokens = array();
        $token = strtok($input, ' ');
        while ($token) {
            // find bracketed tokens
            if ($token{0}=='(') {
                $token .= ' '.strtok(')').')';
            }
            // find double quoted tokens
            if ($token{0}=='"') {
                $token .= ' '.strtok('"').'"';
            }
            // find single quoted tokens
            if ($token{0}=="'") {
                $token .= ' '.strtok("'")."'";
            }
            $tokens[] = $token;
            $token = strtok(' ');
        }
        // Some cleaning of tokens that are just boolean joins
        //  and removal of brackets
        $return = array();
        foreach ($tokens as $token) {
            // Ignore join
            if (!in_array($token, $joins)) {
                // And strip parentheses
                $final = trim(strtr($token, $paren));
                if ($final != "") {
                    $return[] = $final;
                }
            }
        }
        return $return;
    }

    /**
     * Basic 'getter' for suggestion list.
     *
     * @return array
     */
    public function getRawSuggestions()
    {
        if (is_null($this->suggestions)) {
            $this->performAndProcessSearch();
        }
        return $this->suggestions;
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
        // Proxy the params object's getRecommendations call -- we need to set up
        // the recommendations in the params object since they need to be
        // query-aware, but from a caller's perspective, it makes more sense to
        // pull them from the results object.
        return $this->getParams()->getRecommendations($location);
    }

    /**
     * Set the service locator.
     *
     * @param ServiceLocatorInterface $serviceLocator Locator to register
     *
     * @return Results
     */
    public function setServiceLocator(ServiceLocatorInterface $serviceLocator)
    {
        $this->serviceLocator = $serviceLocator;
        return $this;
    }

    /**
     * Restore the service locator (a cascading version of setServiceLocator()).
     *
     * @param ServiceLocatorInterface $serviceLocator Locator to register
     *
     * @return Results
     */
    public function restoreServiceLocator(ServiceLocatorInterface $serviceLocator)
    {
        $this->setServiceLocator($serviceLocator);
        $params = $this->getParams();
        if (method_exists($params, 'setServiceLocator')) {
            $params->setServiceLocator($serviceLocator);
        }
        $options = $this->getOptions();
        if (method_exists($options, 'setServiceLocator')) {
            $params->setServiceLocator($serviceLocator);
        }
        return $this;
    }

    /**
     * Unset the service locator.
     *
     * @return Results
     */
    public function unsetServiceLocator()
    {
        $this->serviceLocator = null;
        $params = $this->getParams();
        if (method_exists($params, 'unsetServiceLocator')) {
            $params->unsetServiceLocator();
        }
        $options = $this->getOptions();
        if (method_exists($options, 'unsetServiceLocator')) {
            $params->unsetServiceLocator();
        }
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
     * Pull the search manager from the service locator.
     *
     * @return \VuFind\Search\Manager
     */
    protected function getSearchManager()
    {
        $sl = $this->getServiceLocator();
        if (!is_object($sl)) {
            throw new \Exception('Could not find service locator');
        }
        return $sl->get('SearchManager');
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
        return $this->getServiceLocator()->get('DbTablePluginManager')->get($table);
    }
}