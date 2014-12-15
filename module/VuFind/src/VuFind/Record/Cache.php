<?php
namespace VuFind\Record;

use VuFind\Exception\RecordMissing as RecordMissingException, 
    VuFind\RecordDriver\PluginManager as RecordFactory, 
    VuFindSearch\Service as SearchService;
use VuFindSearch\ParamBag;
use VuFind\Record\Loader;

class Cache extends Loader
{

    protected $cacheIdentifiers = null;
    protected $cachableSources = null;
    protected $dbTableManager = null;
    
    public function __construct(SearchService $searchService, 
        RecordFactory $recordFactory, 
        \Zend\Config\Config $config,
        \VuFind\Db\Table\PluginManager $dbTableManager)
    {
        $this->dbTableManager = $dbTableManager;
        $this->cachableSources = preg_split("/[\s,]+/", $config->Social->cachableSources);
        $this->cacheIdentifiers = new ParamBag();
        parent::__construct($searchService, $recordFactory);
    }

    public function load($id, $source = 'VuFind', $tolerateMissing = false)
    {
        // try to load the record from cache if source is cachable
        if (in_array($source, $this->cachableSources)) {
            $results = $this->searchService->retrieve('RecordCache', $id)->getRecords();
            if (count($results) > 0) {
                return $results[0];
            }
        }
        
        // on cache miss try to load the record from the original $source
        return parent::load($id, $source, $tolerateMissing = false);
    }

    public function loadBatchForSource($ids, $source = 'VuFind')
    {
        $retVal = array();        
        
        $cachedRecords = array();
        // try to load the records from cache if source is cachable
        if (in_array($source, $this->cachableSources)) {
            $cachedRecords = $this->searchService->retrieveBatch('RecordCache', $ids, 
                $this->cacheIdentifiers)->getRecords();
            
            // which records could not be loaded from the record cache?  
            foreach ($cachedRecords as $cachedRecord) {
                $key = array_search($cachedRecord->getUniqueId(),$ids);
                unset($ids[$key]);
            }
        }         
        
        // try to load the missing records from the original $source 
        $genuineRecords = array();
        if (count($ids) > 0 ) {
            $genuineRecords = parent::loadBatchForSource($ids, $source);
        }
          
        // merge records found in cache and records loaded from original $source
        $retVal = $genuineRecords;
        foreach ($cachedRecords as $cachedRecord) {
            $retVal[] = $cachedRecord;
        }
        
        return $retVal;
    }

    public function loadBatch($ids)
    {
        // evaluate additional parameters to identify a cached record (e.g. userId, listId, sessionId )
        foreach ($ids as $details) {
            $tmp = array();
            if (isset($details['listId'])) {
                $tmp['listId'] = $details['listId'];
            } else {
                $tmp['listId'] = null;
            }
            if (isset($details['userId'])) {
                $tmp['userId'] = $details['userId'];
            } else {
                $tmp['userId'] = null;
            }
            
            $this->cacheIdentifiers->add($details['id'], $tmp);
        }
        
        return parent::loadBatch($ids);
    }
    
    public function update($recordId, $rawData, $source, $userId, $sessionId, $listId) {
        $recordTable = $this->dbTableManager->get('Record');
        if (in_array($source, $this->cachableSources)) {
            $recordTable->findRecord($recordId, $rawData, true, $source, $userId, $listId, $sessionId);
        }
    }
    
    private function retrieve() {
        
    }
    
    private function retrieveBatch() {
        
    }
    
}
