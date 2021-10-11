<?php

namespace TueFind\Search\SolrAuth;

class Options extends \VuFind\Search\SolrAuth\Options
{
    /**
     * Since the authority.ini does not allow translated_facets in 7.0,
     * we need to override it here in the implementation.
     *
     * @return array
     */
    public function getTranslatedFacets() {
        $facets = parent::getTranslatedFacets();
        if (!in_array('language', $facets))
            $facets[] = 'language';
        return $facets;
    }
}
