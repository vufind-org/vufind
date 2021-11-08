<?php

namespace TueFind\Search\SolrAuth;

class Results extends \VuFind\Search\SolrAuth\Results
{
    public function getFacetList($filter = null)
    {
        $list = parent::getFacetList($filter);

        // Translate "Record Type" facet
        // Note that all translations also need to be added to Params::formatFilterListEntry
        // for the translations of active filters at the top.
        foreach ($list as $facetKey => $facet) {
            if (in_array($facetKey, ['type'])) {
                $prefix = 'authority_type_';
                foreach ($facet['list'] as $listKey => $listItem) {
                    $list[$facetKey]['list'][$listKey]['displayText'] = $this->translate($prefix . $listItem['displayText']);
                }
            }
        }

        // Normally, VuFind will only display facets with values.
        //
        // The 'year' facet just contains searchable ranges
        // which will not be returned (e.g. [1900 TO 2000]).
        // So we need to always display the facet, even if Solr does not return
        // any values.
        if (!isset($list['year'])) {
            $list['year'] = ['label' => 'Year', 'list' => []];
        }

        return $list;
    }
}
