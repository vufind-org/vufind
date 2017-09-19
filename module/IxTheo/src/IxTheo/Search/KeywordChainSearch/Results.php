<?php

namespace IxTheo\Search\KeywordChainSearch;

class Results extends \VuFind\Search\Solr\Results
{
    /**
     * Constructor
     *
     * @param \VuFind\Search\Base\Params $params Object representing user search
     * parameters.
     */
    public function __construct($params)
    {
        // Call parent constructor:
        parent::__construct($params);
    }

    /**
     * Is the current search saved in the database?
     *
     * @return bool
     */
    public function isSavedSearch()
    {
        return false;
    }

    protected function performSearch()
    {
        $query = $this->getParams()->getQuery();
        $params = $this->getParams()->getBackendParameters();

        $limit = $this->getParams()->getLimit();
        $offset = ($this->getParams()->getPage() - 1) * $limit;

        $params->set("facet.offset", $offset);
        $params->set("facet.limit", $limit);

        // Perform the search:
        $collection = $this->getSearchService()->search($this->backendId, $query, 0, 0, $params);

        $this->responseFacets = $collection->getFacets();

        // Generate language extension and remove language subcode
        $lang =  implode($params->get("lang"));
        $lang_ext = $lang ? "_" . explode("-", $lang)[0] : "_de";

        $facet = 'key_word_chains_sorted' . $lang_ext;
        $facet_count = $facet . '-count';

        // Get the facets from which we will build our results:
        $facets = $this->getFacetList([$facet => null]);
        $count =  $this->getFacetList([$facet_count => null]);
        if (isset($facets[$facet])) {
            $this->resultTotal = $count[$facet_count]['list'][0]['count'];
            $this->results = $facets[$facet]['list'];
        }
    }

}
