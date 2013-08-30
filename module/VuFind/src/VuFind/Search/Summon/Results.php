<?php
/**
 * Summon Search Results
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
 * @package  Search_Summon
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
namespace VuFind\Search\Summon;

/**
 * Summon Search Parameters
 *
 * @category VuFind2
 * @package  Search_Summon
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
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
        $collection = $this->getSearchService()->search(
            'Summon', $query, $offset, $limit, $params
        );

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

        // Add fake date facets if flagged earlier; this is necessary in order
        // to display the date range facet control in the interface.
        $dateFacets = $this->getParams()->getDateFacetSettings();
        if (!empty($dateFacets)) {
            foreach ($dateFacets as $dateFacet) {
                $this->responseFacets[] = array(
                    'fieldName' => 'PublicationDate',
                    'displayName' => 'PublicationDate',
                    'counts' => array()
                );
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
        // If there is no filter, we'll use all facets as the filter:
        if (is_null($filter)) {
            $filter = $this->getParams()->getFacetConfig();
        } else {
            // If there is a filter, make sure the field names are properly
            // stripped of extra parameters:
            $oldFilter = $filter;
            $filter = array();
            foreach ($oldFilter as $key => $value) {
                $key = explode(',', $key);
                $key = trim($key[0]);
                $filter[$key] = $value;
            }
        }

        // We want to sort the facets to match the order in the .ini file.  Let's
        // create a lookup array to determine order:
        $i = 0;
        $order = array();
        foreach ($filter as $key => $value) {
            $order[$key] = $i++;
        }

        // Loop through the facets returned by Summon.
        $facetResult = array();
        if (is_array($this->responseFacets)) {
            // Get the filter list -- we'll need to check it below:
            $filterList = $this->getParams()->getFilters();

            foreach ($this->responseFacets as $current) {
                // The "displayName" value is actually the name of the field on
                // Summon's side -- we'll probably need to translate this to a
                // different value for actual display!
                $field = $current['displayName'];

                // Is this one of the fields we want to display?  If so, do work...
                if (isset($filter[$field])) {
                    // Should we translate values for the current facet?
                    $translate = in_array(
                        $field, $this->getOptions()->getTranslatedFacets()
                    );

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
                        // value is an applied filter.  First, is the current field
                        // present in the filter list?  Second, is the current value
                        // an active filter for the current field?
                        $isApplied = in_array($field, array_keys($filterList))
                            && in_array(
                                $facetDetails['value'], $filterList[$field]
                            );

                        // Inject "applied" value into Summon results:
                        $current['counts'][$facetIndex]['isApplied'] = $isApplied;

                        // Create display value:
                        $current['counts'][$facetIndex]['displayText'] = $translate
                            ? $this->translate($facetDetails['value'])
                            : $facetDetails['value'];
                    }

                    // Put the current facet cluster in order based on the .ini
                    // settings, then override the display name again using .ini
                    // settings.
                    $i = $order[$field];
                    $current['label'] = $filter[$field];

                    // Create a reference to counts called list for consistency with
                    // Solr output format -- this allows the facet recommendations
                    // modules to be shared between the Search and Summon modules.
                    $current['list'] = & $current['counts'];
                    $facetResult[$i] = $current;
                }
            }
        }
        ksort($facetResult);

        // Rewrite the sorted array with appropriate keys:
        $finalResult = array();
        foreach ($facetResult as $current) {
            $finalResult[$current['displayName']] = $current;
        }

        return $finalResult;
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
        $this->suggestions = array();
        foreach ($spelling as $current) {
            if (!isset($this->suggestions[$current['originalQuery']])) {
                $this->suggestions[$current['originalQuery']] = array(
                    'suggestions' => array()
                );
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
        $retVal = array();
        foreach ($this->getRawSuggestions() as $term => $details) {
            foreach ($details['suggestions'] as $word) {
                // Strip escaped characters in the search term (for example, "\:")
                $term = stripcslashes($term);
                $word = stripcslashes($word);
                $retVal[$term]['suggestions'][$word] = array('new_term' => $word);
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
}