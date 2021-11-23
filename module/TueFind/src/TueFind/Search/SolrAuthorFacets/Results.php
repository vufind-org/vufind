<?php

namespace TueFind\Search\SolrAuthorFacets;

class Results extends \VuFind\Search\SolrAuthorFacets\Results
{
    /**
     * Support method for performAndProcessSearch -- perform a search based on the
     * parameters passed to the object.
     *
     * @return void
     */
    protected function performSearch()
    {
        $query = $this->getParams()->getQuery();
        $params = $this->getParams()->getBackendParametersAuthorAndIdFacet();

        // Perform the search:
        $collection = $this->getSearchService()
            ->search($this->backendId, $query, 0, 0, $params);

        $this->responseFacets = $collection->getFacets();

        // Get the facets from which we will build our results:
        $facets = $this->getFacetList(['author_and_id_facet' => null]);
        if (isset($facets['author_and_id_facet'])) {
            $params = $this->getParams();
            $this->resultTotal
                = (($params->getPage() - 1) * $params->getLimit())
                + count($facets['author_and_id_facet']['list']);
            $this->results = array_slice(
                $facets['author_and_id_facet']['list'], 0, $params->getLimit()
            );
        }
    }
}
