<?php

namespace TueFind\Search\SolrAuth;

class Results extends \VuFind\Search\SolrAuth\Results
{
    public function getFacetList($filter = null)
    {
        $list = parent::getFacetList($filter);

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
