<?php
namespace VuFind\Record;

use VuFind\RecordDriver\PluginManager as RecordFactory, 
    VuFind\Db\Table\PluginManager as DbTableManager,
    Zend\Config\Config as Config;

class Cache
{
    const FAVORITE      = 'favorite';
    
    protected $cachePolicy = 5;
    protected $DISABLED          = 0b00000; //  0
    protected $PRIMARY           = 0b00001; //  1
    protected $FALLBACK          = 0b00010; //  2
    protected $INCLUDE_RECORD_ID = 0b00100; //  4
    protected $INCLUDE_SOURCE    = 0b01000; //  8
    protected $INCLUDE_USER_ID   = 0b10000; // 16
    
    protected $cachableSources = null;
    
    protected $recordFactories = array();
    protected $recordTable = null;
    
    protected $config;
    
    public function __construct(RecordFactory $recordFactoryManager, Config $config, DbTableManager $dbTableManager )
    {
        $this->cachableSources = preg_split("/[\s,]+/", $config->RecordCache->cachableSources);
        $this->recordTable = $dbTableManager->get('record');
        $this->recordFactories['VuFind'] = array($recordFactoryManager, 'getSolrRecord');
        $this->recordFactories['WorldCat'] = function ($data) use ($recordFactoryManager) {
            $driver = $recordFactoryManager->get('WorldCat');
            $driver->setRawData($data);
            $driver->setSourceIdentifier('WorldCat');
            return $driver;
        };
        $this->config = $config;
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
    
    /**
     * Given an array of associative arrays with id and source keys (or pipe-
     * separated source|id strings) 
     *
     * @param array $ids Array of associative arrays with id/source keys or
     * strings in source|id format.  In associative array formats, there is
     * also an optional "extra_fields" key which can be used to pass in data
     *
     * @return array     Array of record drivers
     */
    
    public function lookup($ids, $source = null) {
        if ($this->cachePolicy === $this->DISABLED) {
            return array();
        }
        
        if (isset($source)) {
            foreach ($ids as $id) {
                $tmp[] = "$source|$id";
            }
            $ids = $tmp;
        } 

        $cacheIds = array();
        foreach ($ids as $i => $details) {
            if (!is_array($details)) {
                $parts = explode('|', $details, 2);
                $details = array('source' => $parts[0],'id' => $parts[1]);
            }
            
            $userId = isset($_SESSION['Account']) ? $_SESSION['Account']->userId : null;
            $cacheIds[] = $this->getCacheId($details['id'], $details['source'], $userId);
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
    
    
    protected function getCacheId($recordId, $source = null, $userId = null) {
        $cIdHelper = array();
        if (($this->cachePolicy & $this->INCLUDE_RECORD_ID) === $this->INCLUDE_RECORD_ID) {
            $cIdHelper['recordId'] = $recordId;
        }
        
        if (($this->cachePolicy & $this->INCLUDE_SOURCE) === $this->INCLUDE_SOURCE) {
            $source = ($source == 'Solr') ? 'VuFind' : $source;
            $cIdHelper['source']   = $source;
        }
            
        if (($this->cachePolicy & $this->INCLUDE_USER_ID) === $this->INCLUDE_USER_ID) {
            $cIdHelper['userId']   = $userId;
        }
        $md5 = md5(json_encode($cIdHelper));
        
        return $md5;
    }
}
