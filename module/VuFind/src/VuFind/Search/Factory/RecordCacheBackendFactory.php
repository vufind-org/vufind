<?php

namespace VuFind\Search\Factory;

use VuFind\RecordDriver\PluginManager;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

use VuFindSearch\Backend\RecordCache\Connector;
use VuFindSearch\Backend\RecordCache\Backend;
use VuFindSearch\Backend\RecordCache\Response\RecordCollectionFactory;

use VuFindSearch\Backend\Solr\Response\Json\RecordCollectionFactory as SolrRecordCollectionFactory;
use VuFindSearch\Backend\WorldCat\Response\XML\RecordCollectionFactory as WorldCatRecordCollectionFactory;

class RecordCacheBackendFactory implements FactoryInterface
{
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        $this->serviceLocator = $serviceLocator;
        
        $databaseManager = $this->serviceLocator->get('VuFind\DbTablePluginManager');
        $connector = $this->createConnector($databaseManager);
        return $this->createBackend($connector);
    }
    
    protected function createConnector($databaseManager)
    {
        $connector = new Connector($databaseManager);
        return $connector;
    }
    
    protected function createBackend(Connector $connector)
    {
        $backend = new Backend($connector);
        $recordCollectionFactory = $this->createRecordCollectionFactory();
        $backend->setRecordCollectionFactory($recordCollectionFactory);
        
        return $backend;
    }
    
    protected function createRecordCollectionFactory()
    {
        $manager = $this->serviceLocator->get('VuFind\RecordDriverPluginManager');
        
        $recordFactories = array();
        
        $recordFactories['VuFind'] = array($manager, 'getSolrRecord');
        
        $recordFactories['WorldCat'] = function ($data) use ($manager) {
            $driver = $manager->get('WorldCat');
            $driver->setRawData($data);
            $driver->setSourceIdentifier('WorldCat');
            return $driver;
        };
        
        return new RecordCollectionFactory($recordFactories);
    }
    
    
}