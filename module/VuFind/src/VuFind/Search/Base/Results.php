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
 * @package  SearchObject
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
namespace VuFind\Search\Base;
use VuFind\Search\UrlHelper;

/**
 * Abstract results search model.
 *
 * This abstract class defines the results methods for modeling a search in VuFind.
 *
 * @category VuFind2
 * @package  SearchObject
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
abstract class Results
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
     * Constructor
     *
     * @param VF_Search_Base_Params $params Object representing user search
     * parameters.
     */
    public function __construct(Params $params)
    {
        // Save the parameters, then perform the search:
        $this->params = $params;
    }

    /**
     * Copy constructor
     *
     * @return void
     */
    public function __clone()
    {
        $this->params = clone($this->params);
    }

    /**
     * Get the URL helper for this object.
     *
     * @return VF_Search_UrlHelper
     */
    public function getUrl()
    {
        // Set up URL helper:
        if (!isset($this->helpers['url'])) {
            $this->helpers['url'] = new UrlHelper($this);
        }
        return $this->helpers['url'];
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
        $recommendations = $this->params->getRecommendations(null);
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
     * Static method to retrieve a record by ID.  Returns a record driver object.
     *
     * @param string $id Unique identifier of record
     *
     * @return VF_RecordDriver_Base
     */
    public static function getRecord($id)
    {
        // This needs to be defined in subclasses:
        throw new \Exception('getRecord needs to be defined.');
    }

    /**
     * Static method to retrieve an array of records by ID.
     *
     * @param array $ids Array of unique record identifiers.
     *
     * @return array
     */
    public static function getRecords($ids)
    {
        // This is the default, dumb behavior for retrieving multiple records --
        // just call getRecord() repeatedly.  For efficiency, this method should
        // be overridden in child classes when possible to reduce API calls, etc.
        $retVal = array();
        foreach ($ids as $id) {
            try {
                $retVal[] = static::getRecord($id);
            } catch (\Exception $e) {
                // Just omit missing records from the return array; calling code
                // in the VF_Record::loadBatch() method will deal with this.
            }
        }
        return $retVal;
    }

    /**
     * Allow Results object to proxy methods of Params object.
     *
     * @param string $methodName Method to call
     * @param array  $params     Method parameters
     *
     * @return mixed
     */
    public function __call($methodName, $params)
    {
        // Proxy undefined methods to the parameter object:
        $method = array($this->params, $methodName);
        if (!is_callable($method)) {
            throw new \Exception($methodName . ' cannot be called.');
        }
        return call_user_func_array($method, $params);
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
        return (($this->getPage() - 1) * $this->getLimit()) + 1;
    }

    /**
     * Get record number for end of range represented by current result set.
     *
     * @return int
     */
    public function getEndRecord()
    {
        $total = $this->getResultTotal();
        $limit = $this->getLimit();
        $page = $this->getPage();
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
        // This data is not available until VuFind_Model_Db_Search::saveSearch()
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
     * @param Zend_Db_Table_Row $row Relevant database row.
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
     * @return Zend_Paginator
     */
    public function getPaginator()
    {
        // If there is a limit on how many pages are accessible,
        // apply that limit now:
        $max = $this->getVisibleSearchResultLimit();
        $total = $this->getResultTotal();
        if ($max > 0 && $total > $max) {
            $total = $max;
        }

        // Build the standard paginator control:
        return Zend_Paginator::factory($total)
            ->setCurrentPageNumber($this->getPage())
            ->setItemCountPerPage($this->getLimit())
            ->setPageRange(11);
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
     * @param VF_MS $minified Minified Search Object
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
}