<?php

namespace TueFind\Record\FallbackLoader;

use VuFindSearch\Service;
use VuFindSearch\Query\Query;

class Solr implements \VuFind\Record\FallbackLoader\FallbackLoaderInterface {

    /**
     * Solr search service
     *
     * @var Service
     */
    private $searchService;

    /**
     * Constructor
     *
     * @param Service  $searchService Solr search service
     */
    public function __construct(Service $searchService)
    {
        $this->searchService = $searchService;
    }

    /**
     * Given an array of IDs that failed to load, try to find them using a
     * fallback mechanism.
     *
     * @param array $ids IDs to load
     *
     * @return array
     */
    public function load($ids)
    {
        $retVal = [];
        foreach ($ids as $id) {
            foreach ($this->fetchRecordCandidates($id) as $record) {
                $record->isFallback = true;
                $retVal[] = $record;
            }
        }
        return $retVal;
    }

    /**
     * Fetch all candidates for the given ID (null if not found).
     *
     * @param string $id ID to load
     *
     * @return \VuFindSearch\Response\RecordCollectionInterface
     */
    protected function fetchRecordCandidates($id)
    {
        $id = addcslashes($id, '"');
        $query = new Query('ids:"' . $id . '"', 'AllFields');
        $result = $this->searchService->search('Solr', $query);
        return $result->getRecords();
    }
}
