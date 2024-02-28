<?php

/**
 * Solr aspect of the Search Multi-class (Results)
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2011, 2022.
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
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Search\Solr;

use VuFind\Search\Solr\AbstractErrorListener as ErrorListener;
use VuFindSearch\Command\SearchCommand;
use VuFindSearch\Query\AbstractQuery;
use VuFindSearch\Query\QueryGroup;

use function count;

/**
 * Solr Search Parameters
 *
 * @category VuFind
 * @package  Search_Solr
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class Results extends \VuFind\Search\Base\Results
{
    /**
     * Field facets.
     *
     * @var array
     */
    protected $responseFacets = null;

    /**
     * Query facets.
     *
     * @var array
     */
    protected $responseQueryFacets = null;

    /**
     * Pivot facets.
     *
     * @var array
     */
    protected $responsePivotFacets = null;

    /**
     * Counts of filtered-out facet values, indexed by field name.
     */
    protected $filteredFacetCounts = null;

    /**
     * Search backend identifier.
     *
     * @var string
     */
    protected $backendId = 'Solr';

    /**
     * Currently used spelling query, if any.
     *
     * @var string
     */
    protected $spellingQuery = '';

    /**
     * Class to process spelling.
     *
     * @var SpellingProcessor
     */
    protected $spellingProcessor = null;

    /**
     * CursorMark used for deep paging (e.g. OAI-PMH Server).
     * Set to '*' to start paging a request and use the new value returned from the
     * search request for the next request.
     *
     * @var null|string
     */
    protected $cursorMark = null;

    /**
     * Highest relevance of all the results
     *
     * @var null|float
     */
    protected $maxScore = null;

    /**
     * Get spelling processor.
     *
     * @return SpellingProcessor
     */
    public function getSpellingProcessor()
    {
        if (null === $this->spellingProcessor) {
            $this->spellingProcessor = new SpellingProcessor();
        }
        return $this->spellingProcessor;
    }

    /**
     * Set spelling processor.
     *
     * @param SpellingProcessor $processor Spelling processor
     *
     * @return void
     */
    public function setSpellingProcessor(SpellingProcessor $processor)
    {
        $this->spellingProcessor = $processor;
    }

    /**
     * Get cursorMark.
     *
     * @return null|string
     */
    public function getCursorMark()
    {
        return $this->cursorMark;
    }

    /**
     * Set cursorMark.
     *
     * @param null|string $cursorMark New cursor mark
     *
     * @return void
     */
    public function setCursorMark($cursorMark)
    {
        $this->cursorMark = $cursorMark;
    }

    /**
     * Get the scores of the results
     *
     * @return array
     */
    public function getScores()
    {
        $scoreMap = [];
        foreach ($this->results as $record) {
            $data = $record->getRawData();
            if ($data['score'] ?? false) {
                $scoreMap[$record->getUniqueId()] = $data['score'];
            }
        }
        return $scoreMap;
    }

    /**
     * Getting the highest relevance of all the results
     *
     * @return null|float
     */
    public function getMaxScore()
    {
        return $this->maxScore;
    }

    /**
     * Support method for performAndProcessSearch -- perform a search based on the
     * parameters passed to the object.
     *
     * @return void
     */
    protected function performSearch()
    {
        $query  = $this->getParams()->getQuery();
        $limit  = $this->getParams()->getLimit();
        $offset = $this->getStartRecord() - 1;
        $params = $this->getParams()->getBackendParameters();
        $searchService = $this->getSearchService();
        $cursorMark = $this->getCursorMark();
        if (null !== $cursorMark) {
            $params->set('cursorMark', '' === $cursorMark ? '*' : $cursorMark);
            // Override any default timeAllowed since it cannot be used with
            // cursorMark
            $params->set('timeAllowed', -1);
        }

        try {
            $command = new SearchCommand(
                $this->backendId,
                $query,
                $offset,
                $limit,
                $params
            );

            $collection = $searchService->invoke($command)->getResult();
        } catch (\VuFindSearch\Backend\Exception\BackendException $e) {
            // If the query caused a parser error, see if we can clean it up:
            if (
                $e->hasTag(ErrorListener::TAG_PARSER_ERROR)
                && $newQuery = $this->fixBadQuery($query)
            ) {
                // We need to get a fresh set of $params, since the previous one was
                // manipulated by the previous search() call.
                $params = $this->getParams()->getBackendParameters();
                $command = new SearchCommand(
                    $this->backendId,
                    $newQuery,
                    $offset,
                    $limit,
                    $params
                );
                $collection = $searchService->invoke($command)->getResult();
            } else {
                throw $e;
            }
        }

        $this->extraSearchBackendDetails = $command->getExtraRequestDetails();

        $this->responseFacets = $collection->getFacets();
        $this->filteredFacetCounts = $collection->getFilteredFacetCounts();
        $this->responseQueryFacets = $collection->getQueryFacets();
        $this->responsePivotFacets = $collection->getPivotFacets();
        $this->resultTotal = $collection->getTotal();
        $this->maxScore = $collection->getMaxScore();

        // Process spelling suggestions
        $spellcheck = $collection->getSpellcheck();
        $this->spellingQuery = $spellcheck->getQuery();
        $this->suggestions = $this->getSpellingProcessor()
            ->getSuggestions($spellcheck, $this->getParams()->getQuery());

        // Update current cursorMark
        if (null !== $cursorMark) {
            $this->setCursorMark($collection->getCursorMark());
        }

        // Construct record drivers for all the items in the response:
        $this->results = $collection->getRecords();

        // Store any errors:
        $this->errors = $collection->getErrors();
    }

    /**
     * Try to fix a query that caused a parser error.
     *
     * @param AbstractQuery $query Bad query
     *
     * @return bool|AbstractQuery  Fixed query, or false if no solution is found.
     */
    protected function fixBadQuery(AbstractQuery $query)
    {
        if ($query instanceof QueryGroup) {
            return $this->fixBadQueryGroup($query);
        } else {
            // Single query? Can we fix it on its own?
            $oldString = $string = $query->getString();

            // Are there any unescaped colons in the string?
            $string = str_replace(':', '\\:', str_replace('\\:', ':', $string));

            // Did we change anything? If so, we should replace the query:
            if ($oldString != $string) {
                $query->setString($string);
                return $query;
            }
        }
        return false;
    }

    /**
     * Support method for fixBadQuery().
     *
     * @param QueryGroup $query Query to fix
     *
     * @return bool|QueryGroup  Fixed query, or false if no solution is found.
     */
    protected function fixBadQueryGroup(QueryGroup $query)
    {
        $newQueries = [];
        $fixed = false;

        // Try to fix each query in the group; replace any query that needs to
        // be changed.
        foreach ($query->getQueries() as $current) {
            $fixedQuery = $this->fixBadQuery($current);
            if ($fixedQuery) {
                $fixed = true;
                $newQueries[] = $fixedQuery;
            } else {
                $newQueries[] = $current;
            }
        }

        // If any of the queries in the group was fixed, we'll treat the whole
        // group as being fixed.
        if ($fixed) {
            $query->setQueries($newQueries);
            return $query;
        }

        // If we got this far, nothing was changed -- report failure:
        return false;
    }

    /**
     * Turn the list of spelling suggestions into an array of urls
     *   for on-screen use to implement the suggestions.
     *
     * @return array Spelling suggestion data arrays
     */
    public function getSpellingSuggestions()
    {
        return $this->getSpellingProcessor()->processSuggestions(
            $this->getRawSuggestions(),
            $this->spellingQuery,
            $this->getParams()
        );
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
        if (null === $this->responseFacets) {
            $this->performAndProcessSearch();
        }
        return $this->buildFacetList($this->responseFacets, $filter);
    }

    /**
     * Get counts of facet values filtered out by the HideFacetValueListener,
     * indexed by field name.
     *
     * @return array
     */
    public function getFilteredFacetCounts(): array
    {
        if (null === $this->filteredFacetCounts) {
            $this->performAndProcessSearch();
        }
        return $this->filteredFacetCounts;
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
     * @param int    $page         1 based. Offsets results by limit.
     * @param bool   $ored         Whether or not facet is an OR facet or not
     *
     * @return array list facet values for each index field with label and more bool
     */
    public function getPartialFieldFacets(
        $facetfields,
        $removeFilter = true,
        $limit = -1,
        $facetSort = null,
        $page = null,
        $ored = false
    ) {
        $clone = clone $this;
        $params = $clone->getParams();

        // Manipulate facet settings temporarily:
        $params->resetFacetConfig();
        $params->setFacetLimit($limit);
        // Clear field-specific limits, as they can interfere with retrieval:
        $params->setFacetLimitByField([]);
        if (null !== $page && $limit != -1) {
            $offset = ($page - 1) * $limit;
            $params->setFacetOffset($offset);
            // Return limit plus one so we know there's another page
            $params->setFacetLimit($limit + 1);
        }
        if (null !== $facetSort) {
            $params->setFacetSort($facetSort);
        }
        foreach ($facetfields as $facetName) {
            $params->addFacet($facetName, null, $ored);

            // Clear existing filters for the selected field if necessary:
            if ($removeFilter) {
                $params->removeAllFilters($facetName);
            }
        }

        // Don't waste time on spellcheck:
        $params->getOptions()->spellcheckEnabled(false);

        // Don't fetch any records:
        $params->setLimit(0);

        // Disable highlighting:
        $params->getOptions()->disableHighlighting();

        // Disable sort:
        $params->setSort('', true);

        // Do search
        $result = $clone->getFacetList();
        $filteredCounts = $clone->getFilteredFacetCounts();

        // Reformat into a hash:
        foreach ($result as $key => $value) {
            // Detect next page and crop results if necessary
            $more = false;
            if (
                isset($page) && count($value['list']) > 0
                && (count($value['list']) + ($filteredCounts[$key] ?? 0)) == $limit + 1
            ) {
                $more = true;
                array_pop($value['list']);
            }
            $result[$key] = ['more' => $more, 'data' => $value];
        }

        // Send back data:
        return $result;
    }

    /**
     * Returns data on pivot facets for the last search
     *
     * @return ArrayObject        Flare-formatted object
     */
    public function getPivotFacetList()
    {
        // Make sure we have processed the search before proceeding:
        if (null === $this->responseFacets) {
            $this->performAndProcessSearch();
        }

        // Start building the flare object:
        $flare = new \stdClass();
        $flare->name = 'flare';
        $flare->total = $this->resultTotal;
        $flare->children = $this->responsePivotFacets;
        return $flare;
    }
}
