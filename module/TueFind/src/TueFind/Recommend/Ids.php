<?php

namespace TueFind\Recommend;

class Ids implements \VuFind\Recommend\RecommendInterface {
    protected $results;
    protected $searchQueryContainsDistinctId = false;
    protected $searchedId;

    // Unused mandatory interface functions / dummy implementation:
    public function setConfig($settings) {}
    public function init($params, $request) {}

    /**
     * Called after the Search Results object has performed its main search.  This
     * may be used to extract necessary information from the Search Results object
     * or to perform completely unrelated processing.
     *
     * @param \VuFind\Search\Base\Results $results Search results object
     *
     * @return void
     */
    public function process($results) {
        $this->results = $results;
        $query = $results->getParams()->getDisplayQuery();
        if (preg_match('"^(id|superior_ppn):([^:]+)$"', $query, $hits)) {
            $this->searchQueryContainsDistinctId = true;
            $this->searchedId = $hits[2];
        }
    }

    /**
     * Functions to be used by view
     */
    public function getResults() {
        return $this->results;
    }

    public function getSearchedId() {
        return $this->searchedId;
    }

    public function searchQueryContainsDistinctId() {
        return $this->searchQueryContainsDistinctId;
    }
}
