<?php
namespace VuFind\Record;

use VuFind\RecordDriver\PluginManager as RecordFactory, 
    VuFind\Db\Table\PluginManager as DbTableManager,
    Zend\Config\Config as Config;

class Cache
{
    const FAVORITE      = 'favorite';
    
    protected $cachePolicy = 0;
    protected $DISABLED          = 0b000;
    protected $PRIMARY           = 0b001;
    protected $FALLBACK          = 0b010;
    protected $INCLUDE_USER_ID   = 0b100;
    
    protected $cacheIds = array();
    protected $cachableSources = null;
    
    protected $recordFactories = array();
    protected $recordTable = null;
    protected $resourceTable = null;
    protected $userResourceTable = null;
    
    protected $config;
    
    public function __construct(RecordFactory $recordFactoryManager, 
        Config $config,
        DbTableManager $dbTableManager)
    {
        $this->recordTable = $dbTableManager->get('record');
        $this->resourceTable = $dbTableManager->get('resource');
        $this->userResourceTable = $dbTableManager->get('user_resource');
        
        $this->cachableSources = preg_split("/[\s,]+/", $config->RecordCache->cachableSources);
        
        $this->recordFactories['VuFind'] = array($recordFactoryManager, 'getSolrRecord');
        $this->recordFactories['WorldCat'] = function ($data) use ($recordFactoryManager) {
            $driver = $recordFactoryManager->get('WorldCat');
            $driver->setRawData($data);
            $driver->setSourceIdentifier('WorldCat');
            return $driver;
        };
        $this->config = $config;
    }

    public function load($id, $source = 'VuFind')
    {
        if (! in_array($source, $this->cachableSources) || $this->cachePolicy === 0 ) {
            return array();
        }
        
        $this->initCacheIds($id, $source);
        // try to load record from cache if source is cachable
        $cachedRecords = $this->read(array($id), $this->cachePolicy);
        
        return $cachedRecords;
    }

    public function loadBatch($ids)
    {
        $this->initCacheIds($ids);
    }
    
    public function loadBatchForSource($ids, $source = 'VuFind') {
        $cachedRecords = $this->read($ids, $this->cachePolicy);
        return $cachedRecords;
    }
    
    public function createOrUpdate($recordId, $userId, $source, $rawData, $sessionId, $resourceId) {
        if (in_array($source, $this->cachableSources)) {
            $cId = $this->getCacheId($recordId, $source, $userId);
            $this->recordTable->updateRecord($cId, $source, $rawData, $recordId, $userId, $sessionId, $resourceId);
        }
    }

    public function cleanup($userId) {
        $this->recordTable->cleanup($userId);
    }
    
    protected function read($ids) {
        
        if ($this->cachePolicy === 0) {
            return array();
        }
        
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
        
        if (($this->cachePolicy & $this->INCLUDE_USER_ID) === $this->INCLUDE_USER_ID) {
            $cIdHelper['userId']   = $userId;
        }
        
        $md5 = md5(json_encode($cIdHelper));
        
        $cacheIds[$recordId] = $md5;
        
        return $md5;
    }
    
    public function setPolicy($cachePolicy) {
        $this->cachePolicy = $this->config->RecordCache->cachePolicy->$cachePolicy;
                    
        if (! isset($this->cachePolicy)) {
            $this->cachePolicy = $cachePolicy;
        }
        
    }
    
    public function isPrimary() {
        return (($this->cachePolicy & $this->PRIMARY) === $this->PRIMARY);
    }
    
    public function isFallback() {
        return (($this->cachePolicy & $this->FALLBACK) === $this->FALLBACK);
    }
    
   
}
