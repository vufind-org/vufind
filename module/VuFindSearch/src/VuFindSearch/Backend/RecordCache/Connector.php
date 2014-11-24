<?php
namespace VuFindSearch\Backend\RecordCache;

use VuFindSearch\ParamBag;
use Zend\Log\LoggerInterface;

class Connector
{

    protected $databaseManager = null;

    public function __construct($databaseManager)
    {
        $this->databaseManager = $databaseManager;
    }

    public function retrieve($id, ParamBag $params = null)
    {
        $recordTable = $this->databaseManager->get('record');
        $record = $recordTable->findRecord($id, null, null, null, $params->get($id)['userId'], $params->get($id)['listId'] );
        $response = $this->buildResponse($record);
        
        return $response;
    }

    public function retrieveBatch($ids, ParamBag $params = null)
    {
        $result = array();
        
        $recordTable = $this->databaseManager->get('record');
        foreach ($ids as $id) {
            $record = $recordTable->findRecord($id, null, null, null, $params->get($id)['userId'], $params->get($id)['listId'] );
            $response[] = $this->buildResponse($record);
        }
        
        return $response;
    }

    protected function buildResponse($record)
    {
        $response = array();
        
        if ($record['source'] === "VuFind") {
            $response[] = array(
                'source' => $record['source'],
                'data' => json_decode($record['data'], true)
            );
        }
        
        if ($record['source'] === "WorldCat") {
            $response[] = array(
                'source' => $record['source'],
                'data' => $record['data']
            );
        }
        return $response;
    }

    public function search(ParamBag $params)
    {
        return array();
    }

    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }
}
