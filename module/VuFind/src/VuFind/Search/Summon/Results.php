<?php

/**
 * Summon Search Results
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
 * @package  Search_Summon
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Search\Summon;

use VuFindSearch\Command\SearchCommand;

use function in_array;
use function is_array;

/**
 * Summon Search Parameters
 *
 * @category VuFind
 * @package  Search_Summon
 * @author   Demian Katz <demian.katz@villanova.edu>
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
     * Best bets
     *
     * @var array|bool
     */
    protected $bestBets = false;

    /**
     * Database recommendations
     *
     * @var array|bool
     */
    protected $databaseRecommendations = false;

    /**
     * Topic recommendations
     *
     * @var array|bool
     */
    protected $topicRecommendations = false;

    /**
     * Search backend identifier.
     *
     * @var string
     */
    protected $backendId = 'Summon';

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
        $command = new SearchCommand(
            $this->backendId,
            $query,
            $offset,
            $limit,
            $params
        );
        $collection = $this->getSearchService()
            ->invoke($command)->getResult();
        $this->responseFacets = $collection->getFacets();
        $this->resultTotal = $collection->getTotal();

        // Process spelling suggestions if enabled (note that we need this
        // check here because sometimes the Summon API returns suggestions
        // even when the spelling parameter is set to false).
        if ($this->getOptions()->spellcheckEnabled()) {
            $spellcheck = $collection->getSpellcheck();
            $this->processSpelling($spellcheck);
        }

        // Get best bets and database recommendations.
        $this->bestBets = $collection->getBestBets();
        $this->databaseRecommendations = $collection->getDatabaseRecommendations();
        $this->topicRecommendations = $collection->getTopicRecommendations();

        // Add fake date facets if flagged earlier; this is necessary in order
        // to display the date range facet control in the interface.
        $dateFacets = $this->getParams()->getDateFacetSettings();
        if (!empty($dateFacets)) {
            foreach ($dateFacets as $dateFacet) {
                $this->responseFacets[] = [
                    'fieldName' => $dateFacet,
                    'displayName' => $dateFacet,
                    'counts' => [],
                ];
            }
        }

        // Construct record drivers for all the items in the response:
        $this->results = $collection->getRecords();
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
        $filter = null === $filter
            ? $this->getParams()->getFacetConfig()
            : $this->stripFilterParameters($filter);

        // We want to sort the facets to match the order in the .ini file. Let's
        // create a lookup array to determine order:
        $order = array_flip(array_keys($filter));

        // Loop through the facets returned by Summon.
        $facetResult = [];
        if (is_array($this->responseFacets)) {
            foreach ($this->responseFacets as $current) {
                // The "displayName" value is actually the name of the field on
                // Summon's side -- we'll probably need to translate this to a
                // different value for actual display!
                $field = $current['displayName'];

                // Is this one of the fields we want to display?  If so, do work...
                if (isset($filter[$field])) {
                    // Basic reformatting of the data:
                    $current = $this->formatFacetData($current);

                    // Inject label from configuration:
                    $current['label'] = $filter[$field];

                    // Put the current facet cluster in order based on the .ini
                    // settings, then override the display name again using .ini
                    // settings.
                    $facetResult[$order[$field]] = $current;
                }
            }
        }
        ksort($facetResult);

        // Rewrite the sorted array with appropriate keys:
        $finalResult = [];
        foreach ($facetResult as $current) {
            $finalResult[$current['displayName']] = $current;
        }

        return $finalResult;
    }

    /**
     * Support method for getFacetList() -- strip extra parameters from field names.
     *
     * @param array $rawFilter Raw filter list
     *
     * @return array           Processed filter list
     */
    protected function stripFilterParameters($rawFilter)
    {
        $filter = [];
        foreach ($rawFilter as $key => $value) {
            $key = explode(',', $key);
            $key = trim($key[0]);
            $filter[$key] = $value;
        }
        return $filter;
    }

    /**
     * Support method for getFacetList() -- format a single facet field.
     *
     * @param array $current Facet data to format
     *
     * @return array         Formatted data
     */
    protected function formatFacetData($current)
    {
        // We'll need this in the loop below:
        $filterList = $this->getParams()->getRawFilters();

        // Should we translate values for the current facet?
        $field = $current['displayName'];
        $translate = in_array(
            $field,
            $this->getOptions()->getTranslatedFacets()
        );
        if ($translate) {
            $transTextDomain = $this->getOptions()
                ->getTextDomainForTranslatedFacet($field);
        }

        // Loop through all the facet values to see if any are applied.
        foreach ($current['counts'] as $facetIndex => $facetDetails) {
            // Is the current field negated?  If so, we don't want to
            // show it -- this is currently used only for the special
            // "exclude newspapers" facet:
            if ($facetDetails['isNegated']) {
                unset($current['counts'][$facetIndex]);
                continue;
            }

            // We need to check two things to determine if the current
            // value is an applied filter. First, is the current field
            // present in the filter list?  Second, is the current value
            // an active filter for the current field?
            $orField = '~' . $field;
            $itemsToCheck = $filterList[$field] ?? [];
            if (isset($filterList[$orField])) {
                $itemsToCheck += $filterList[$orField];
            }
            $isApplied = in_array($facetDetails['value'], $itemsToCheck);

            // Inject "applied" value into Summon results:
            $current['counts'][$facetIndex]['isApplied'] = $isApplied;

            // Set operator:
            $current['counts'][$facetIndex]['operator']
                = $this->getParams()->getFacetOperator($field);

            // Create display value:
            $current['counts'][$facetIndex]['displayText'] = $translate
                ? $this->translate("$transTextDomain::{$facetDetails['value']}")
                : $facetDetails['value'];
        }

        // Create a reference to counts called list for consistency with
        // Solr output format -- this allows the facet recommendations
        // modules to be shared between the Search and Summon modules.
        $current['list'] = & $current['counts'];

        return $current;
    }

    /**
     * Process spelling suggestions from the results object
     *
     * @param array $spelling Suggestions from Summon
     *
     * @return void
     */
    protected function processSpelling($spelling)
    {
        $this->suggestions = [];
        foreach ($spelling as $current) {
            $current = $current['suggestion'];
            if (!isset($this->suggestions[$current['originalQuery']])) {
                $this->suggestions[$current['originalQuery']] = [
                    'suggestions' => [],
                ];
            }
            $this->suggestions[$current['originalQuery']]['suggestions'][]
                = $current['suggestedQuery'];
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
        $retVal = [];
        foreach ($this->getRawSuggestions() as $term => $details) {
            foreach ($details['suggestions'] as $word) {
                // Strip escaped characters in the search term (for example, "\:")
                $term = stripcslashes($term);
                $word = stripcslashes($word);
                $retVal[$term]['suggestions'][$word] = ['new_term' => $word];
            }
        }
        return $retVal;
    }

    /**
     * Get best bets from Summon, if any.
     *
     * @return array|bool false if no recommendations, detailed array otherwise.
     */
    public function getBestBets()
    {
        return $this->bestBets;
    }

    /**
     * Get database recommendations from Summon, if any.
     *
     * @return array|bool false if no recommendations, detailed array otherwise.
     */
    public function getDatabaseRecommendations()
    {
        return $this->databaseRecommendations;
    }

    /**
     * Get topic recommendations from Summon, if any.
     *
     * @return array|bool false if no recommendations, detailed array otherwise.
     */
    public function getTopicRecommendations()
    {
        return $this->topicRecommendations;
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
     *
     * @return array an array with the facet values for each index field
     */
    public function getPartialFieldFacets(
        $facetfields,
        $removeFilter = true,
        $limit = -1,
        $facetSort = null,
        $page = null
    ) {
        $params = $this->getParams();
        $query  = $params->getQuery();
        // No limit not implemented with Summon: cause page loop
        if ($limit == -1) {
            if ($page === null) {
                $page = 1;
            }
            $limit = 50;
        }
        $params->resetFacetConfig();
        if (null !== $facetSort && 'count' !== $facetSort) {
            throw new \Exception("$facetSort facet sort not supported by Summon.");
        }
        foreach ($facetfields as $facet) {
            $mode = $params->getFacetOperator($facet) === 'OR' ? 'or' : 'and';
            $params->addFacet("$facet,$mode,$page,$limit");

            // Clear existing filters for the selected field if necessary:
            if ($removeFilter) {
                $params->removeAllFilters($facet);
            }
        }
        $params = $params->getBackendParameters();
        $command = new SearchCommand(
            $this->backendId,
            $query,
            0,
            0,
            $params
        );
        $collection = $this->getSearchService()->invoke($command)
            ->getResult();
        $facets = $collection->getFacets();
        $ret = [];
        foreach ($facets as $data) {
            if (in_array($data['displayName'], $facetfields)) {
                $formatted = $this->formatFacetData($data);
                $list = $formatted['counts'];
                $ret[$data['displayName']] = [
                    'data' => [
                        'label' => $data['displayName'],
                        'list' => $list,
                    ],
                    'more' => null,
                ];
            }
        }

        // Send back data:
        return $ret;
    }
}
