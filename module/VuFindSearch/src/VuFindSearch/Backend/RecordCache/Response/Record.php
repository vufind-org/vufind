<?php

namespace VuFindSearch\Backend\RecordCache\Response;

use VuFindSearch\Response\RecordInterface;

class Record implements RecordInterface
{
    protected $source;

    protected $data;
    
    public function __construct($data)
    {
        $this->data = $data;
    }

    public function setSourceIdentifier($identifier)
    {
        $this->source = $identifier;
    }

    public function getSourceIdentifier()
    {
        return $this->source;
    }

    public function getData() {
        return $this->data;
    }
    
}
