<?php

namespace IxTheo\Search\Solr;

/**
 * This trait will be re-used in
 * - Solr\Results
 * - Search2\Results
 */

trait ResultsTrait {
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
            if (preg_match('"^dewey-"i', $facetKey)) {
                foreach ($facet['list'] as $listKey => $listItem) {
                    if (preg_match('"^\d{3}\b"', $listItem['value'], $hits)) {
                        $ddcNumber = $hits[0];
                        $list[$facetKey]['list'][$listKey]['displayText'] = $ddcNumber . ' - ' . $this->translate(['DDC23', $ddcNumber]);
                    }
                }
            }
        }
        return $list;
    }
}
