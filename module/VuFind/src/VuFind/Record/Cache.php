<?php
namespace VuFind\Record;

use VuFindSearch\Service as SearchService,
    VuFind\RecordDriver\PluginManager as RecordFactory, 
    VuFind\Db\Table\PluginManager as DbTableManager,
    Zend\Config\Config as Config;
use VuFind\Record\Loader;


class Cache extends Loader
{
    protected $cacheIds = array();
    
    protected $cachableSources = null;
    protected $recordFactories = array();
    protected $recordTable = null;
    protected $resourceTable = null;
    protected $userResourceTable = null;
    
    public function __construct(SearchService $searchService, 
        RecordFactory $recordFactoryManager, 
        Config $config,
        DbTableManager $dbTableManager)
    {
        $this->recordTable = $dbTableManager->get('record');
        $this->resourceTable = $dbTableManager->get('resource');
        $this->userResourceTable = $dbTableManager->get('user_resource');
        
        $this->cachableSources = preg_split("/[\s,]+/", $config->Social->cachableSources);
        
        $this->recordFactories['VuFind'] = array($recordFactoryManager, 'getSolrRecord');
        $this->recordFactories['WorldCat'] = function ($data) use ($recordFactoryManager) {
            $driver = $recordFactoryManager->get('WorldCat');
            $driver->setRawData($data);
            $driver->setSourceIdentifier('WorldCat');
            return $driver;
        };
        
        parent::__construct($searchService, $recordFactoryManager);
    }

    public function load($id, $source = 'VuFind', $tolerateMissing = false)
    {
        $this->initCacheIds($id, $source);
        
        // try to load record from cache if source is cachable
        if (in_array($source, $this->cachableSources)) {
            $cachedRecord = $this->loadFromCache(array($id));
            if (!empty($cachedRecord)) {
                return $cachedRecord[0];
            }
        }
        
        // on cache miss try to load the record from the original $source
        return parent::load($id, $source, $tolerateMissing = false);
    }

    public function loadBatch($ids)
    {
        // remember ids for later use loadFromCache and loadBatchFromCache
        $this->initCacheIds($ids);
    
        return parent::loadBatch($ids);
    }
    
    public function loadBatchForSource($ids, $source = 'VuFind')
    {
        // try to load records from cache if source is cachable
        $cachedRecords = array();
        if (in_array($source, $this->cachableSources)) {
            $cachedRecords = $this->loadFromCache($ids);
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
    
    public function createOrUpdate($recordId, $userId, $source, $rawData, $sessionId) {
        if (in_array($source, $this->cachableSources)) {
            $cId = $this->getCacheId($recordId, $source, $userId);
            $this->recordTable->updateRecord($cId, $source, $rawData, $recordId, $userId, $sessionId);
        }
    }

    public function delete($ids, $userId) {
        
        foreach ($ids as $id) {
            $source = explode('|', $id)[0];
            $recordId = explode('|', $id)[1];
            
            if (in_array($source, $this->cachableSources)) {
                $isOrphaned = $this->recordTable->isOrphaned($recordId, $source, $userId);
                if ($isOrphaned) {
                    $this->recordTable->delete($this->getCacheId($recordId, $source, $userId));
                }
            }
        }
    }
    
    
    protected function loadFromCache($ids) {
        
        $cacheIds = array();
        foreach ($ids as $id) {
                $cacheIds[] = $this->cacheIds[$id];
        }
        
        $cachedRecords = $this->recordTable->findRecord($cacheIds);
        
        $vufindRecords = array();
        foreach($cachedRecords as $cachedRecord) {
            $factory = $this->recordFactories[$cachedRecord['source']];
            $doc = json_decode($cachedRecord['data'], true);
            
            $vufindRecords[] = call_user_func($factory, $doc);
        }
        
        return $vufindRecords;
    }
    
    
    
    // $ids = recordId
    // $ids = array(array(), array(), array());
    protected function initCacheIds($ids, $source = null, $userId = null) {
        if (!is_array($ids)) {
            $recordId = $ids;
            $source = ($source == 'Solr') ? 'VuFind' : $source;
            $userId = isset($_SESSION['Account']) ? $_SESSION['Account']->userId : null;
            $this->cacheIds[$recordId] = $this->getCacheId($recordId, $source, $userId);
        } else {
            foreach ($ids as $id) {
                $recordId = $id['id'];
                $source = isset($id['source']) ? $id['source'] : null;
                $userId = isset($id['userId']) ? $id['userId'] : null;
                
                $this->cacheIds[$recordId] = $this->getCacheId($recordId, $source, $userId);
            }
        } 

    }
    
    protected function getCacheId($recordId, $source = null, $userId = null) {
        
        $cIdHelper = array();
        $cIdHelper['recordId'] = $recordId;
        $cIdHelper['source']   = $source;
        $cIdHelper['userId']   = $userId;
        
        $md5 = md5(json_encode($cIdHelper));
        
        $cacheIds[$recordId] = $md5;
        
        return $md5;
    }
   
}
