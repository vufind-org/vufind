<?php
/**
 * Solr aspect of the Search Multi-class (Results)
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
use VuFind\Config\Reader as ConfigReader,
    VuFind\Connection\Manager as ConnectionManager,
    VuFind\Exception\RecordMissing as RecordMissingException,
    VuFind\Search\Base\Results as BaseResults,
    VuFind\Search\Options as SearchOptions,
    VuFind\Translator\Translator;

/**
 * Solr Search Parameters
 *
 * @category VuFind2
 * @package  Search_Solr
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class Results extends BaseResults
{
    // Raw Solr search response:
    protected $rawResponse = null;

    /**
     * Get a connection to the Solr index.
     *
     * @param null|array $shards Selected shards to use (null for defaults)
     * @param string     $index  ID of index/search classes to use (this assumes
     * that \VuFind\Search\$index\Options and \VuFind\Connection\$index are both
     * valid classes)
     *
     * @return \VuFind\Connection\Solr
     */
    public static function getSolrConnection($shards = null, $index = 'Solr')
    {
        // Turn on all shards by default if none are specified (since we may get
        // called in a static context by getRecord(), we need to be sure that any
        // given ID will yield results, even if not all shards are on by default).
        $options = SearchOptions::getInstance($index);
        $allShards = $options->getShards();
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
        }

        // Connect to Solr and set up shards:
        $solr = ConnectionManager::connectToIndex($index);
        $solr->setShards($shards, $options->getSolrShardsFieldsToStrip());
        return $solr;
    }

    /**
     * Support method for performAndProcessSearch -- perform a search based on the
     * parameters passed to the object.
     *
     * @return void
     */
    protected function performSearch()
    {
        $solr = static::getSolrConnection($this->getSelectedShards());

        // Collect the search parameters:
        $overrideQuery = $this->getOverrideQuery();
        $params = array(
            'query' => !empty($overrideQuery)
                ? $overrideQuery : $solr->buildQuery($this->getSearchTerms()),
            'handler' => $this->getSearchHandler(),
            // Account for reserved VuFind word 'relevance' (which really means
            // "no sort parameter in Solr"):
            'sort' => $this->getSort() == 'relevance' ? null : $this->getSort(),
            'start' => $this->getStartRecord() - 1,
            'limit' => $this->getLimit(),
            'facet' => $this->getParams()->getFacetSettings(),
            'filter' => $this->getParams()->getFilterSettings(),
            'spell' => $this->getParams()->getSpellingQuery(),
            'dictionary' => $this->getOptions()->getSpellingDictionary(),
            'highlight' => $this->getOptions()->highlightEnabled()
        );

        // Perform the search:
        $this->rawResponse = $solr->search($params);

        // How many results were there?
        $this->resultTotal = isset($this->rawResponse['response']['numFound'])
            ? $this->rawResponse['response']['numFound'] : 0;

        // Process spelling suggestions if no index error resulted from the query
        if ($this->getOptions()->spellcheckEnabled()) {
            // Shingle dictionary
            $this->processSpelling();
            // Make sure we don't endlessly loop
            if ($this->getOptions()->getSpellingDictionary() == 'default') {
                // Expand against the basic dictionary
                $this->basicSpelling();
            }
        }

        // Construct record drivers for all the items in the response:
        $this->results = array();
        for ($x = 0; $x < count($this->rawResponse['response']['docs']); $x++) {
            $this->results[] = static::initRecordDriver(
                $this->rawResponse['response']['docs'][$x]
            );
        }
    }

    /**
     * Process spelling suggestions from the results object
     *
     * @return void
     */
    protected function processSpelling()
    {
        // Do nothing if there are no suggestions
        $suggestions = isset($this->rawResponse['spellcheck']['suggestions']) ?
            $this->rawResponse['spellcheck']['suggestions'] : array();
        if (count($suggestions) == 0) {
            return;
        }

        // Loop through the array of search terms we have suggestions for
        $suggestionList = array();
        foreach ($suggestions as $suggestion) {
            $ourTerm = $suggestion[0];

            // Skip numeric terms if numeric suggestions are disabled
            if ($this->getOptions()->shouldSkipNumericSpelling()
                && is_numeric($ourTerm)
            ) {
                continue;
            }

            $ourHit  = $suggestion[1]['origFreq'];
            $count   = $suggestion[1]['numFound'];
            $newList = $suggestion[1]['suggestion'];

            $validTerm = true;

            // Make sure the suggestion is for a valid search term.
            // Sometimes shingling will have bridged two search fields (in
            // an advanced search) or skipped over a stopword.
            if (!$this->findSearchTerm($ourTerm)) {
                $validTerm = false;
            }

            // Unless this term had no hits
            if ($ourHit != 0) {
                // Filter out suggestions we are already using
                $newList = $this->filterSpellingTerms($newList);
            }

            // Make sure it has suggestions and is valid
            if (count($newList) > 0 && $validTerm) {
                // Did we get more suggestions then our limit?
                if ($count > $this->getOptions()->getSpellingLimit()) {
                    // Cut the list at the limit
                    array_splice($newList, $this->getOptions()->getSpellingLimit());
                }
                $suggestionList[$ourTerm]['freq'] = $ourHit;
                // Format the list nicely
                foreach ($newList as $item) {
                    if (is_array($item)) {
                        $suggestionList[$ourTerm]['suggestions'][$item['word']]
                            = $item['freq'];
                    } else {
                        $suggestionList[$ourTerm]['suggestions'][$item] = 0;
                    }
                }
            }
        }
        $this->suggestions = $suggestionList;
    }

    /**
     * Filter a list of spelling suggestions to remove suggestions
     *   we are already searching for
     *
     * @param array $termList List of suggestions
     *
     * @return array          Filtered list
     */
    protected function filterSpellingTerms($termList)
    {
        $newList = array();
        if (count($termList) == 0) {
            return $newList;
        }

        foreach ($termList as $term) {
            if (!$this->findSearchTerm($term['word'])) {
                $newList[] = $term;
            }
        }
        return $newList;
    }

    /**
     * Try running spelling against the basic dictionary.
     *   This function should ensure it doesn't return
     *   single word suggestions that have been accounted
     *   for in the shingle suggestions above.
     *
     * @return array Suggestions array
     */
    protected function basicSpelling()
    {
        // TODO: There might be a way to run the
        //   search against both dictionaries from
        //   inside solr. Investigate. Currently
        //   submitting a second search for this.

        // Create a new search object
        $myClass = get_class($this);
        $newParams = clone($this->getParams());
        $newParams->getOptions()->useBasicDictionary();

        // Don't waste time loading facets or highlighting/retrieving results:
        $newParams->resetFacetConfig();
        $newParams->getOptions()->disableHighlighting();
        $newParams->setLimit(0);
        $newParams->recommendationsEnabled(false);

        $newSearch = new $myClass($newParams);

        // Get the spelling results
        $newList = $newSearch->getRawSuggestions();

        // If there were no shingle suggestions
        if (count($this->suggestions) == 0) {
            // Just use the basic ones as provided
            $this->suggestions = $newList;
        } else {
            // Otherwise...
            // For all the new suggestions
            foreach ($newList as $word => $data) {
                // Check the old suggestions
                $found = false;
                foreach ($this->suggestions as $k => $v) {
                    // Make sure it wasn't part of a shingle
                    //   which has been suggested at a higher
                    //   level.
                    $found = preg_match("/\b$word\b/", $k) ? true : $found;
                }
                if (!$found) {
                    $this->suggestions[$word] = $data;
                }
            }
        }
    }

    /**
     * Turn the list of spelling suggestions into an array of urls
     *   for on-screen use to implement the suggestions.
     *
     * @return array Spelling suggestion data arrays
     */
    public function getSpellingSuggestions()
    {
        $returnArray = array();
        $suggestions = $this->getRawSuggestions();
        $tokens = $this->spellingTokens($this->getParams()->getSpellingQuery());

        foreach ($suggestions as $term => $details) {
            // Find out if our suggestion is part of a token
            $inToken = false;
            $targetTerm = "";
            foreach ($tokens as $token) {
                // TODO - Do we need stricter matching here?
                //   Similar to that in replaceSearchTerm()?
                if (stripos($token, $term) !== false) {
                    $inToken = true;
                    // We need to replace the whole token
                    $targetTerm = $token;
                    // Go and replace this token
                    $returnArray = $this->doSpellingReplace(
                        $term, $targetTerm, $inToken, $details, $returnArray
                    );
                }
            }
            // If no tokens were found, just look for the suggestion 'as is'
            if ($targetTerm == "") {
                $targetTerm = $term;
                $returnArray = $this->doSpellingReplace(
                    $term, $targetTerm, $inToken, $details, $returnArray
                );
            }
        }
        return $returnArray;
    }

    /**
     * Process one instance of a spelling replacement and modify the return
     *   data structure with the details of what was done.
     *
     * @param string $term        The actually term we're replacing
     * @param string $targetTerm  The term above, or the token it is inside
     * @param bool   $inToken     Flag for whether the token or term is used
     * @param array  $details     The spelling suggestions
     * @param array  $returnArray Return data structure so far
     *
     * @return array              $returnArray modified
     */
    protected function doSpellingReplace($term, $targetTerm, $inToken, $details,
        $returnArray
    ) {
        $config = ConfigReader::getConfig();

        $returnArray[$targetTerm]['freq'] = $details['freq'];
        foreach ($details['suggestions'] as $word => $freq) {
            // If the suggested word is part of a token
            if ($inToken) {
                // We need to make sure we replace the whole token
                $replacement = str_replace($term, $word, $targetTerm);
            } else {
                $replacement = $word;
            }
            //  Do we need to show the whole, modified query?
            if ($config->Spelling->phrase) {
                $label = $this->getDisplayQueryWithReplacedTerm(
                    $targetTerm, $replacement
                );
            } else {
                $label = $replacement;
            }
            // Basic spelling suggestion data
            $returnArray[$targetTerm]['suggestions'][$label] = array(
                'freq' => $freq,
                'new_term' => $replacement
            );

            // Only generate expansions if enabled in config
            if ($config->Spelling->expand) {
                // Parentheses differ for shingles
                if (strstr($targetTerm, " ") !== false) {
                    $replacement = "(($targetTerm) OR ($replacement))";
                } else {
                    $replacement = "($targetTerm OR $replacement)";
                }
                $returnArray[$targetTerm]['suggestions'][$label]['expand_term']
                    = $replacement;
            }
        }

        return $returnArray;
    }

    /**
     * Returns the stored list of facets for the last search
     *
     * @param array $filter Array of field => on-screen description listing
     * all of the desired facet fields; set to null to get all configured values.
     *
     * @return array        Facets data arrays
     */
    public function getFacetList($filter = null)
    {
        // Make sure we have processed the search before proceeding:
        if (is_null($this->rawResponse)) {
            $this->performAndProcessSearch();
        }

        // If there is no filter, we'll use all facets as the filter:
        if (is_null($filter)) {
            $filter = $this->getParams()->getFacetConfig();
        }

        // Start building the facet list:
        $list = array();

        // If we have no facets to process, give up now
        if (!isset($this->rawResponse['facet_counts']['facet_fields'])
            || !is_array($this->rawResponse['facet_counts']['facet_fields'])
        ) {
            return $list;
        }

        // Loop through every field returned by the result set
        $validFields = array_keys($filter);
        foreach ($this->rawResponse['facet_counts']['facet_fields']
                 as $field => $data) {
            // Skip filtered fields and empty arrays:
            if (!in_array($field, $validFields) || count($data) < 1) {
                continue;
            }
            // Initialize the settings for the current field
            $list[$field] = array();
            // Add the on-screen label
            $list[$field]['label'] = $filter[$field];
            // Build our array of values for this field
            $list[$field]['list']  = array();
            // Should we translate values for the current facet?
            $translate
                = in_array($field, $this->getOptions()->getTranslatedFacets());
            // Loop through values:
            foreach ($data as $facet) {
                // Initialize the array of data about the current facet:
                $currentSettings = array();
                $currentSettings['value'] = $facet[0];
                $currentSettings['displayText']
                    = $translate ? Translator::translate($facet[0]) : $facet[0];
                $currentSettings['count'] = $facet[1];
                $currentSettings['isApplied']
                    = $this->getParams()->hasFilter("$field:".$facet[0]);

                // Store the collected values:
                $list[$field]['list'][] = $currentSettings;
            }
        }
        return $list;
    }

    /**
     * Static method to retrieve a record by ID.  Returns a record driver object.
     *
     * @param string $id Unique identifier of record
     *
     * @throws RecordMissingException
     * @return \VuFind\RecordDriver\Base
     */
    public static function getRecord($id)
    {
        $solr = static::getSolrConnection();

        // Check if we need to apply hidden filters:
        $options = SearchOptions::getInstance(
            SearchOptions::extractSearchClassId(get_called_class())
        );
        $filters = $options->getHiddenFilters();
        $extras = empty($filters) ? array() : array('fq' => $filters);

        $record = $solr->getRecord($id, $extras);
        if (empty($record)) {
            throw new RecordMissingException(
                'Record ' . $id . ' does not exist.'
            );
        }
        return static::initRecordDriver($record);
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
        // Figure out how many records to retrieve at the same time --
        // we'll use either 100 or the ID request limit, whichever is smaller.
        $params = new Params();
        $pageSize = $params->getQueryIDLimit();
        if ($pageSize < 1 || $pageSize > 100) {
            $pageSize = 100;
        }

        // Retrieve records a page at a time:
        $retVal = array();
        while (count($ids) > 0) {
            $currentPage = array_splice($ids, 0, $pageSize, array());
            $params->setQueryIDs($currentPage);
            $params->setLimit($pageSize);
            $results = new Results($params);
            $retVal = array_merge($retVal, $results->getResults());
        }

        return $retVal;
    }

    /**
     * Method to retrieve records similar to the provided ID.  Returns an
     * array of record driver objects.
     *
     * @param string $id Unique identifier of record
     *
     * @return array
     */
    public function getSimilarRecords($id)
    {
        $solr = static::getSolrConnection($this->getSelectedShards());
        $filters = $this->getOptions()->getHiddenFilters();
        $extras = empty($filters) ? array() : array('fq' => $filters);
        $rawResponse = $solr->getMoreLikeThis($id, $extras);
        $results = array();
        for ($x = 0; $x < count($rawResponse['response']['docs']); $x++) {
            $results[] = static::initRecordDriver(
                $rawResponse['response']['docs'][$x]
            );
        }
        return $results;
    }

    /**
     * Support method for _performSearch(): given an array of Solr response data,
     * construct an appropriate record driver object.
     *
     * @param array $data Solr data
     *
     * @return \VuFind\RecordDriver\Base
     */
    protected static function initRecordDriver($data)
    {
        // Remember bad classes to prevent unnecessary file accesses.
        static $badClasses = array();

        // Determine driver path based on record type:
        $driver = 'VuFind\RecordDriver\Solr' . ucwords($data['recordtype']);

        // If we can't load the driver, fall back to the default, index-based one:
        if (isset($badClasses[$driver]) || !@class_exists($driver)) {
            $badClasses[$driver] = 1;
            $driver = 'VuFind\RecordDriver\SolrDefault';
        }

        // Build the object:
        if (class_exists($driver)) {
            return new $driver($data);
        }

        throw new \Exception('Cannot find record driver -- ' . $driver);
    }
    
    /**
     * Get complete facet counts for several index fields
     *
     * @param array $facetfields  name of the Solr fields to return facets for
     * @param bool  $removeFilter Clear existing filters from selected fields (true)
     * or retain them (false)?
     *
     * @return array an array with the facet values for each index field
     */
    public function getFullFieldFacets($facetfields, $removeFilter = true)
    {
        $clone = clone($this);

        // Manipulate facet settings temporarily:
        $clone->resetFacetConfig();
        $clone->setFacetLimit(-1);
        foreach ($facetfields as $facetName) {
            $clone->addFacet($facetName);

            // Clear existing filters for the selected field if necessary:
            if ($removeFilter) {
                $clone->removeAllFilters($facetName);
            }
        }

        // Do search
        $result = $clone->getFacetList();

        // Reformat into a hash:
        //$returnFacets = $result['facet_counts']['facet_fields'];
        foreach ($result as $key => $value) {
            unset($result[$key]);
            $result[$key]['data'] = $value;
        }

        // Send back data:
        return $result;
    }
}