<?php

namespace TueFind\Search\SolrAuthorFacets;

use VuFindSearch\ParamBag;

class Params extends \VuFind\Search\SolrAuthorFacets\Params
{
    public function getBackendParametersAuthorAndIdFacet()
    {
        $backendParams = $this->getBackendParameters();

        // TueFind: Use author_id_and_facet instead of regular parameters
        $backendParams->remove('fl');
        $backendParams->add('fl', 'id,author_and_id_facet');
        $backendParams->remove('facet.field');
        $backendParams->add('facet.field', 'author_and_id_facet');

        return $backendParams;
    }
}
