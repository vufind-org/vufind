<?php
/**
 * EDS API Results
 *
 * PHP version 7
 *
 * Copyright (C) EBSCO Industries 2013
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
 * @package  EBSCO
 * @author   Michelle Milton <mmilton@epnet.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
namespace VuFind\Search\EDS;

/**
 * EDS API Results
 *
 * @category VuFind
 * @package  EBSCO
 * @author   Michelle Milton <mmilton@epnet.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class Results extends \VuFind\Search\Base\Results
{
    /**
     * Search backend identifier.
     *
     * @var string
     */
    protected $backendId = 'EDS';

    /**
     * Facet list
     *
     * @var array
     */
    protected $responseFacets;

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
            $this->backendId,
            $query,
            $offset,
            $limit,
            $params
        );
        if (null != $collection) {
            $this->responseFacets = $collection->getFacets();
            $this->resultTotal = $collection->getTotal();

            // Add a publication date facet
            $this->responseFacets[] = [
                'fieldName' => 'PublicationDate',
                'displayName' => 'PublicationDate',
                'displayText' => 'Publication Date',
                'counts' => []
            ];

            // Construct record drivers for all the items in the response:
            $this->results = $collection->getRecords();
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
        if (null === $filter) {
            $filter = $this->getParams()->getFacetConfig();
        }
        $filterFields = array_keys($filter);

        // Loop through the facets returned by EDS
        $facetResult = [];
        if (is_array($this->responseFacets)) {
            // Get the filter list -- we'll need to check it below:
            $filterList = $this->getParams()->getRawFilters();
            $translatedFacets = $this->getOptions()->getTranslatedFacets();
            foreach ($this->responseFacets as $current) {
                // The "displayName" value is actually the name of the field on
                // EBSCO's side -- we'll probably need to translate this to a
                // different value for actual display!
                $field = $current['displayName'];

                // If we are filtering out the field, skip it!
                $currentFilterKey = array_search($field, $filterFields);
                if ($currentFilterKey === false) {
                    continue;
                }

                // Should we translate values for the current facet?
                if ($translate = in_array($field, $translatedFacets)) {
                    $transTextDomain = $this->getOptions()
                        ->getTextDomainForTranslatedFacet($field);
                }

                // Loop through all the facet values to see if any are applied.
                foreach ($current['counts'] as $facetIndex => $facetDetails) {
                    // We need to check two things to determine if the current
                    // value is an applied filter.  First, is the current field
                    // present in the filter list?  Second, is the current value
                    // an active filter for the current field?
                    $orField = '~' . $field;
                    $itemsToCheck = $filterList[$field] ?? [];
                    if (isset($filterList[$orField])) {
                        $itemsToCheck += $filterList[$orField];
                    }
                    $isApplied = in_array($facetDetails['value'], $itemsToCheck);

                    // Inject "applied" value into EDS results:
                    $current['counts'][$facetIndex]['isApplied'] = $isApplied;

                    // Set operator:
                    $current['counts'][$facetIndex]['operator']
                        = $this->getParams()->getFacetOperator($field);

                    // Create display value:
                    $current['counts'][$facetIndex]['displayText'] = $translate
                        ? $this->translate(
                            "$transTextDomain::{$facetDetails['displayText']}"
                        ) : $facetDetails['displayText'];

                    // Create display value:
                    $current['counts'][$facetIndex]['value']
                        = $facetDetails['value'];
                }
                // The EDS API returns facets in the order they should be displayed
                $current['label'] = $filter[$field] ?? $field;

                // Create a reference to counts called list for consistency with
                // Solr output format -- this allows the facet recommendations
                // modules to be shared between the Search and other modules.
                $current['list'] = & $current['counts'];
                $facetResult[$currentFilterKey] = $current;
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
}
