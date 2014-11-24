<?php
namespace VuFindSearch\Backend\RecordCache\Response;

use VuFindSearch\Response\RecordCollectionFactoryInterface;
use VuFindSearch\Exception\InvalidArgumentException;

class RecordCollectionFactory implements RecordCollectionFactoryInterface
{

    protected $recordFactories;

    protected $collectionClass;

    public function __construct($recordFactories = null, $collectionClass = null)
    {
        // // Set default record factory if none provided:
        // if (null === $recordFactory) {
        // $recordFactory = function ($i) {
        // return new Record($i);
        // };
        // } else if (!is_callable($recordFactory)) {
        // throw new InvalidArgumentException('Record factory must be callable.');
        // }
        $this->recordFactories = $recordFactories;
        $this->collectionClass = (null === $collectionClass) ? 'VuFindSearch\Backend\RecordCache\Response\RecordCollection' : $collectionClass;
    }

    public function factory($response)
    {
        $collection = new $this->collectionClass($response);
        
        foreach ($response as $record) {
            $factory = $this->recordFactories[$record['source']];
            $doc = $record['data'];
            
            $collection->add(call_user_func($factory, $doc));
        }
        
        return $collection;
    }
}