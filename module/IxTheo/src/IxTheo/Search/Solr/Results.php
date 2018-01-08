<?php

namespace IxTheo\Search\Solr;

class Results extends \VuFind\Search\Solr\Results
{
    /**
     * Returns the stored list of facets for the last search
     *
     * Contains special translation logic for ixtheo/relbib notation facets
     *
     * @param array $filter Array of field => on-screen description listing
     * all of the desired facet fields; set to null to get all configured values.
     *
     * @return array        Facets data arrays
     */
    public function getFacetList($filter = null)
    {
        $list = parent::getFacetList($filter);
        foreach ($list as $facetKey => $facet) {
            if (in_array($facetKey, ['ixtheo_notation_facet', 'relbib_notation_facet'])) {
                $prefix = 'ixtheo-';
                foreach ($facet['list'] as $listKey => $listItem) {
                    $list[$facetKey]['list'][$listKey]['displayText'] = $this->translate($prefix . $listItem['displayText']);
                }
            }
        }
        return $list;
    }

    /**
     * Get complete facet counts for several index fields
     *
     * Overwritten to sort translated_facets
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
    public function getPartialFieldFacets($facetfields, $removeFilter = true,
        $limit = -1, $facetSort = null, $page = null, $ored = false
    ) {
        $facets = parent::getPartialFieldFacets($facetfields, $removeFilter, $limit, $facetSort, $page, $ored);

        if ($facetSort == 'index') {
            foreach ($facets as $facet => $facetDetails) {
                $items = $facetDetails['data']['list'];
                array_multisort(array_column($items, 'displayText'), SORT_ASC, SORT_LOCALE_STRING, $items);
                $facets[$facet]['data']['list'] = $items;
            }
        }

        // Send back data:
        return $facets;
    }
}
