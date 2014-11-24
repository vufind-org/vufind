<?php
namespace VuFind\Record;

use VuFind\Exception\RecordMissing as RecordMissingException, VuFind\RecordDriver\PluginManager as RecordFactory, VuFindSearch\Service as SearchService;
use VuFindSearch\ParamBag;
use VuFind\Record\Loader;

class Cache extends Loader
{

    protected $cacheIdentifiers = null;

    public function __construct(SearchService $searchService, 
        RecordFactory $recordFactory)
    {
        $this->cacheIdentifiers = new ParamBAg();
        parent::__construct($searchService, $recordFactory);
    }

    public function load($id, $source = 'VuFind', $tolerateMissing = false)
    {
        $results = $this->searchService->retrieve('RecordCache', $id)->getRecords();
        if (count($results) > 0) {
            return $results[0];
        }
        
        return parent::load($id, $source, $tolerateMissing = false);
    }

    public function loadBatchForSource($ids, $source = 'VuFind')
    {
        $results = $this->searchService->retrieveBatch('RecordCache', $ids, $this->cacheIdentifiers)->getRecords();
        if (count($results) > 0) {
            return $results;
        }
        
        return $this->searchService->retrieveBatch($source, $ids)->getRecords();
    }

    public function loadBatch($ids)
    {
        foreach ($ids as $details) {
            $this->cacheIdentifiers->add($details['id'], 
                array(
                    'listId' => $details['listId'],
                    'userId' => $details['userId']
                ));
        }
        
        return parent::loadBatch($ids);
    }
}
