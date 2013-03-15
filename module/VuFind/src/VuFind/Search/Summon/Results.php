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
use SerialsSolutions_Summon_Query as SummonQuery,
    VuFind\Exception\RecordMissing as RecordMissingException,
    VuFind\Search\Base\Results as BaseResults,
    VuFind\Solr\Utils as SolrUtils,
    VuFindSearch\ParamBag;

/**
 * Summon Search Parameters
 *
 * @category VuFind2
 * @package  Search_Summon
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class Results extends BaseResults
{
    /**
     * Raw search response:
     */
    protected $rawResponse = null;

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
        $params = $this->createBackendParameters($this->getParams());
        $collection = $this->getSearchService()->search(
            'Summon', $query, $offset, $limit, $params
        );

        $this->rawResponse = $collection->getRawResponse();
        $this->resultTotal = $collection->getTotal();

        // Process spelling suggestions if enabled (note that we need this
        // check here because sometimes the Summon API returns suggestions
        // even when the spelling parameter is set to false).
        if ($this->getOptions()->spellcheckEnabled()) {
            $spellcheck = $collection->getSpellcheck();
            $this->processSpelling($spellcheck);
        }

        // Add fake date facets if flagged earlier; this is necessary in order
        // to display the date range facet control in the interface.
        $dateFacets = $this->getParams()->getDateFacetSettings();
        if (!empty($dateFacets)) {
            if (!isset($this->rawResponse['facetFields'])) {
                $this->rawResponse['facetFields'] = array();
            }
            foreach ($dateFacets as $dateFacet) {
                $this->rawResponse['facetFields'][] = array(
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
     * Method to retrieve a record by ID.  Returns a record driver object.
     *
     * @param string $id Unique identifier of record
     *
     * @throws RecordMissingException
     * @return \VuFind\RecordDriver\Base
     */
    public function getRecord($id)
    {
        $collection = $this->getSearchService()->retrieve('Summon', $id);

        if (count($collection) == 0) {
            throw new RecordMissingException(
                'Record ' . $id . ' does not exist.'
            );
        }

        return current($collection->getRecords());
    }

    /**
     * Create search backend parameters for advanced features.
     *
     * @param Params $params Search parameters
     *
     * @return ParamBag
     * @tag NEW SEARCH
     */
    protected function createBackendParameters (Params $params)
    {
        $backendParams = new ParamBag();

        $options = $params->getOptions();

        // The "relevance" sort option is a VuFind reserved word; we need to make
        // this null in order to achieve the desired effect with Summon:
        $sort = $params->getSort();
        $finalSort = ($sort == 'relevance') ? null : $sort;
        $backendParams->set('sort', $finalSort);

        $backendParams->set('didYouMean', $options->spellcheckEnabled());

        if ($options->highlightEnabled()) {
            $backendParams->set('highlight', true);
            $backendParams->set('highlightStart', '{{{{START_HILITE}}}}');
            $backendParams->set('highlightEnd', '{{{{END_HILITE}}}}');
        }
        $backendParams->set(
            'facets',
            $this->createBackendFacetParameters($params->getFullFacetSettings())
        );
        $this->createBackendFilterParameters(
            $backendParams, $params->getFilterList()
        );

        return $backendParams;
    }

    /**
     * Set up facets based on VuFind settings.
     *
     * @param array $facets Facet settings
     *
     * @return array
     */
    protected function createBackendFacetParameters ($facets)
    {
        $config = $this->getServiceLocator()->get('VuFind\Config')->get('Summon');
        $defaultFacetLimit = isset($config->Facet_Settings->facet_limit)
            ? $config->Facet_Settings->facet_limit : 30;

        $finalFacets = array();
        foreach ($facets as $facet) {
            // See if parameters are included as part of the facet name;
            // if not, override them with defaults.
            $parts = explode(',', $facet);
            $facetName = $parts[0];
            $facetMode = isset($parts[1]) ? $parts[1] : 'and';
            $facetPage = isset($parts[2]) ? $parts[2] : 1;
            $facetLimit = isset($parts[3]) ? $parts[3] : $defaultFacetLimit;
            $facetParams = "{$facetMode},{$facetPage},{$facetLimit}";
            $finalFacets[] = "{$facetName},{$facetParams}";
        }
        return $finalFacets;
    }

    /**
     * Set up filters based on VuFind settings.
     *
     * @param ParamBag $params     Parameter collection to update
     * @param array    $filterList Filter settings
     *
     * @return void
     */
    public function createBackendFilterParameters (ParamBag $params, $filterList)
    {
        // Which filters should be applied to our query?
        if (!empty($filterList)) {
            // Loop through all filters and add appropriate values to request:
            foreach ($filterList as $filterArray) {
                foreach ($filterArray as $filt) {
                    $safeValue = SummonQuery::escapeParam($filt['value']);
                    // Special case -- "holdings only" is a separate parameter from
                    // other facets.
                    if ($filt['field'] == 'holdingsOnly') {
                        $params->set('holdings', strtolower(trim($safeValue)) == 'true');
                    } else if ($filt['field'] == 'excludeNewspapers') {
                        // Special case -- support a checkbox for excluding
                        // newspapers:
                        $params->add('filters', "ContentType,Newspaper Article,true");
                    } else if ($range = SolrUtils::parseRange($filt['value'])) {
                        // Special case -- range query (translate [x TO y] syntax):
                        $from = SummonQuery::escapeParam($range['from']);
                        $to = SummonQuery::escapeParam($range['to']);
                        $params->add('rangeFilters', "{$filt['field']},{$from}:{$to}");
                    } else {
                        // Standard case:
                        $params->add('filters', "{$filt['field']},{$safeValue}");
                    }
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
        if (isset($this->rawResponse['facetFields'])
            && is_array($this->rawResponse['facetFields'])
        ) {
            // Get the filter list -- we'll need to check it below:
            $filterList = $this->getParams()->getFilters();

            foreach ($this->rawResponse['facetFields'] as $current) {
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
     * Get database recommendations from Summon, if any.
     *
     * @return array|bool false if no recommendations, detailed array otherwise.
     */
    public function getDatabaseRecommendations()
    {
        return isset($this->rawResponse['recommendationLists']['database']) ?
            $this->rawResponse['recommendationLists']['database'] : false;
    }
}