<?php
/**
 * Solr aspect of the Search Multi-class (Results)
 *
 * PHP version 7
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
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
namespace VuFind\Search\Solr;

use VuFind\Search\Solr\AbstractErrorListener as ErrorListener;
use VuFindSearch\Query\AbstractQuery;
use VuFindSearch\Query\QueryGroup;

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
     * Facet details:
     *
     * @var array
     */
    protected $responseFacets = null;

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
     * Hierarchical facet helper
     *
     * @var HierarchicalFacetHelper
     */
    protected $hierarchicalFacetHelper = null;

    /**
     * Set hierarchical facet helper
     *
     * @param HierarchicalFacetHelper $helper Hierarchical facet helper
     *
     * @return void
     */
    public function setHierarchicalFacetHelper(HierarchicalFacetHelper $helper)
    {
        $this->hierarchicalFacetHelper = $helper;
    }

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
            $collection = $searchService
                ->search($this->backendId, $query, $offset, $limit, $params);
        } catch (\VuFindSearch\Backend\Exception\BackendException $e) {
            // If the query caused a parser error, see if we can clean it up:
            if ($e->hasTag(ErrorListener::TAG_PARSER_ERROR)
                && $newQuery = $this->fixBadQuery($query)
            ) {
                // We need to get a fresh set of $params, since the previous one was
                // manipulated by the previous search() call.
                $params = $this->getParams()->getBackendParameters();
                $collection = $searchService
                    ->search($this->backendId, $newQuery, $offset, $limit, $params);
            } else {
                throw $e;
            }
        }

        $this->responseFacets = $collection->getFacets();
        $this->resultTotal = $collection->getTotal();

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
        // Make sure we have processed the search before proceeding:
        if (null === $this->responseFacets) {
            $this->performAndProcessSearch();
        }

        // If there is no filter, we'll use all facets as the filter:
        if (null === $filter) {
            $filter = $this->getParams()->getFacetConfig();
        }

        // Start building the facet list:
        $list = [];

        // Loop through every field returned by the result set
        $fieldFacets = $this->responseFacets->getFieldFacets();
        $translatedFacets = $this->getOptions()->getTranslatedFacets();
        $hierarchicalFacets = $this->getOptions()->getHierarchicalFacets();
        foreach (array_keys($filter) as $field) {
            $data = $fieldFacets[$field] ?? [];
            // Skip empty arrays:
            if (count($data) < 1) {
                continue;
            }
            // Initialize the settings for the current field
            $list[$field] = [];
            // Add the on-screen label
            $list[$field]['label'] = $filter[$field];
            // Build our array of values for this field
            $list[$field]['list']  = [];
            // Should we translate values for the current facet?
            $translateTextDomain = '';
            $translateFormat = '';
            $translate = in_array($field, $translatedFacets);
            if ($translate) {
                $translateTextDomain = $this->getOptions()
                    ->getTextDomainForTranslatedFacet($field);
                $translateFormat = $this->getOptions()
                    ->getFormatForTranslatedFacet($field);
            }
            $hierarchical = in_array($field, $hierarchicalFacets);
            // Loop through values:
            foreach ($data as $value => $count) {
                // Initialize the array of data about the current facet:
                $currentSettings = [];
                $currentSettings['value'] = $value;

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

                if ($translate) {
                    $translated = $this->translate(
                        [$translateTextDomain, $displayText]
                    );
                    // Apply a format to the translation (if available)
                    if ($translateFormat) {
                        $translated = $this->translate(
                            $translateFormat,
                            ['%%raw%%' => $displayText,
                            '%%translated%%' => $translated]
                        );
                    }
                    $currentSettings['displayText'] = $translated;
                } else {
                    $currentSettings['displayText'] = $displayText;
                }

                $currentSettings['count'] = $count;
                $currentSettings['operator']
                    = $this->getParams()->getFacetOperator($field);
                $currentSettings['isApplied']
                    = $this->getParams()->hasFilter("$field:" . $value)
                    || $this->getParams()->hasFilter("~$field:" . $value);

                // Store the collected values:
                $list[$field]['list'][] = $currentSettings;
            }
        }
        return $list;
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

        // Reformat into a hash:
        foreach ($result as $key => $value) {
            // Detect next page and crop results if necessary
            $more = false;
            if (isset($page) && count($value['list']) > 0
                && count($value['list']) == $limit + 1
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
        $flare->name = "flare";
        $flare->total = $this->resultTotal;
        $visualFacets = $this->responseFacets->getPivotFacets();
        $flare->children = $visualFacets;
        return $flare;
    }
}
