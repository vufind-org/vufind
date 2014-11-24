<?php
namespace VuFindSearch\Backend\RecordCache;

use VuFindSearch\Query\AbstractQuery;
use VuFindSearch\ParamBag;
use VuFindSearch\Response\RecordCollectionInterface;
use VuFindSearch\Response\RecordCollectionFactoryInterface;
use VuFindSearch\Backend\AbstractBackend;
use VuFindSearch\Feature\RetrieveBatchInterface;

class Backend extends AbstractBackend
// implements RetrieveBatchInterface
{

    protected $connector;

    protected $queryBuilder = null;

    protected $recordDriverPluginManager = null;

    protected $recordCollectionFactories = array();

    protected $databaseManager = null;

    public function __construct(Connector $connector, RecordCollectionFactoryInterface $factory = null)
    {
        if (null !== $factory) {
            $this->setRecordCollectionFactory($factory);
        }
        $this->connector = $connector;
    }

    public function search(AbstractQuery $query, $offset, $limit, ParamBag $params = null)
    {
        $response = $this->connector->search($params);
        $collection = $this->createRecordCollection($response);
        return $collection;
    }

    public function retrieve($id, ParamBag $params = null)
    {
        $response = $this->connector->retrieve($id, $params);
        $collection = $this->createRecordCollection($response);
        
        return $collection;
    }

    public function retrieveBatch($ids, ParamBag $params = null)
    {
        $result = array();
        
        $responses = $this->connector->retrieveBatch($ids, $params);
        foreach ($responses as $response) {
            $collection = $this->createRecordCollection($response);
        }
        
        return $collection;
    }

    protected function createRecordCollection($cacheEntries)
    {
        $recordCollectionFactory = $this->getRecordCollectionFactory();
        $recordCollection = $recordCollectionFactory->factory($cacheEntries);
        
        return $recordCollection;
    }

    public function getRecordCollectionFactory()
    {
        return $this->collectionFactory;
    }
}