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
use VuFind\Exception\RecordMissing as RecordMissingException,
    VuFind\Search\Base\Results as BaseResults;

use VuFindSearch\Query\AbstractQuery;
use VuFindSearch\Query\QueryGroup;
use VuFindSearch\Query\Query;

use VuFindSearch\ParamBag;
use VuFindSearch\Backend\Solr\Response\Json\Spellcheck;

/**
 * Solr Search Parameters
 *
 * @category VuFind2
 * @package  Search_Solr
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class Results extends BaseResults
{
    /**
     * Facet details:
     *
     * @var array
     */
    protected $responseFacets = null;

    /**
     * Search backend identifiers.
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
        $params = $this->createBackendParameters($query, $this->getParams());
        $collection = $this->getSearchService()
            ->search($this->backendId, $query, $offset, $limit, $params);

        $this->responseFacets = $collection->getFacets();
        $this->resultTotal = $collection->getTotal();

        // Process spelling suggestions
        $this->processSpelling($collection->getSpellcheck());

        // Construct record drivers for all the items in the response:
        $this->results = $collection->getRecords();
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
        static $table = array(
            'year' => array('field' => 'publishDateSort', 'order' => 'desc'),
            'publishDateSort' =>
                array('field' => 'publishDateSort', 'order' => 'desc'),
            'author' => array('field' => 'authorStr', 'order' => 'asc'),
            'title' => array('field' => 'title_sort', 'order' => 'asc'),
            'relevance' => array('field' => 'score', 'order' => 'desc'),
            'callnumber' => array('field' => 'callnumber', 'order' => 'asc'),
        );
        $normalized = array();
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
            } else {
                $normalized[] = sprintf(
                    '%s %s',
                    $field,
                    $order ?: 'asc'
                );
            }
        }
        return implode(',', $normalized);
    }

    /**
     * Create search backend parameters for advanced features.
     *
     * @param AbstractQuery $query  Current search query
     * @param Params        $params Search parameters
     *
     * @return ParamBag
     */
    protected function createBackendParameters(AbstractQuery $query, Params $params)
    {
        $backendParams = new ParamBag();

        // Spellcheck
        $backendParams->set(
            'spellcheck',
            $params->getOptions()->spellcheckEnabled() ? 'true' : 'false'
        );

        // Facets
        $facets = $params->getFacetSettings();
        if (!empty($facets)) {
            $backendParams->add('facet', 'true');
            foreach ($facets as $key => $value) {
                $backendParams->add("facet.{$key}", $value);
            }
            $backendParams->add('facet.mincount', 1);
        }

        // Filters
        $filters = $params->getFilterSettings();
        foreach ($filters as $filter) {
            $backendParams->add('fq', $filter);
        }

        // Shards
        $allShards = $params->getOptions()->getShards();
        $shards = $params->getSelectedShards();
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
            $backendParams->add('shards', implode(',', $selectedShards));
        }

        // Sort
        $sort = $params->getSort();
        if ($sort) {
            $backendParams->add('sort', $this->normalizeSort($sort));
        }

        // Highlighting -- on by default, but we should disable if necessary:
        if (!$params->getOptions()->highlightEnabled()) {
            $backendParams->add('hl', 'false');
        }

        return $backendParams;
    }

    /**
     * Process SOLR spelling suggestions.
     *
     * @param Spellcheck $spellcheck Spellcheck information
     *
     * @return void
     */
    protected function processSpelling(Spellcheck $spellcheck)
    {
        $this->spellingQuery = $spellcheck->getQuery();
        $this->suggestions = array();
        foreach ($spellcheck as $term => $info) {
            // TODO: Avoid reference to Options
            if ($this->getOptions()->shouldSkipNumericSpelling()
                && is_numeric($term)
            ) {
                continue;
            }
            // Term is not part of the query
            if (!$this->getParams()->getQuery()->containsTerm($term)) {
                continue;
            }
            // Filter out suggestions that are already part of the query
            // TODO: Avoid reference to Options
            $suggestionLimit = $this->getOptions()->getSpellingLimit();
            $suggestions     = array();
            foreach ($info['suggestion'] as $suggestion) {
                if (count($suggestions) >= $suggestionLimit) {
                    break;
                }
                $word = $suggestion['word'];
                if (!$this->getParams()->getQuery()->containsTerm($word)) {
                    // TODO: Avoid reference to Options
                    // Note: !a || !b eq !(a && b)
                    if (!is_numeric($word)
                        || !$this->getOptions()->shouldSkipNumericSpelling()
                    ) {
                        $suggestions[$word] = $suggestion['freq'];
                    }
                }
            }
            if ($suggestions) {
                $this->suggestions[$term] = array(
                    'freq' => $info['origFreq'],
                    'suggestions' => $suggestions
                );
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
        $tokens = $this->spellingTokens($this->spellingQuery);

        foreach ($suggestions as $term => $details) {
            // Find out if our suggestion is part of a token
            $inToken = false;
            $targetTerm = "";
            foreach ($tokens as $token) {
                // TODO - Do we need stricter matching here, similar to that in
                // \VuFindSearch\Query\Query::replaceTerm()?
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
        $config = $this->getServiceLocator()->get('VuFind\Config')->get('config');

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
                $label = $this->getParams()->getDisplayQueryWithReplacedTerm(
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
        if (null === $this->responseFacets) {
            $this->performAndProcessSearch();
        }

        // If there is no filter, we'll use all facets as the filter:
        if (is_null($filter)) {
            $filter = $this->getParams()->getFacetConfig();
        }

        // Start building the facet list:
        $list = array();

        // Loop through every field returned by the result set
        $fieldFacets = $this->responseFacets->getFieldFacets();
        foreach (array_keys($filter) as $field) {
            $data = isset($fieldFacets[$field]) ? $fieldFacets[$field] : array();
            // Skip empty arrays:
            if (count($data) < 1) {
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
            foreach ($data as $value => $count) {
                // Initialize the array of data about the current facet:
                $currentSettings = array();
                $currentSettings['value'] = $value;
                $currentSettings['displayText']
                    = $translate ? $this->translate($value) : $value;
                $currentSettings['count'] = $count;
                $currentSettings['isApplied']
                    = $this->getParams()->hasFilter("$field:".$value);

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
     *
     * @return array an array with the facet values for each index field
     */
    public function getFullFieldFacets($facetfields, $removeFilter = true,
        $limit = -1, $facetSort = null
    ) {
        $clone = clone($this);
        $params = $clone->getParams();

        // Manipulate facet settings temporarily:
        $params->resetFacetConfig();
        $params->setFacetLimit($limit);
        if (null !== $facetSort) {
            $params->setFacetSort($facetSort);
        }
        foreach ($facetfields as $facetName) {
            $params->addFacet($facetName);

            // Clear existing filters for the selected field if necessary:
            if ($removeFilter) {
                $params->removeAllFilters($facetName);
            }
        }

        // Do search
        $result = $clone->getFacetList();

        // Reformat into a hash:
        foreach ($result as $key => $value) {
            unset($result[$key]);
            $result[$key]['data'] = $value;
        }

        // Send back data:
        return $result;
    }
}