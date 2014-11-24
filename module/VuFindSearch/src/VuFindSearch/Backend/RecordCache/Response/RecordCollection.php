<?php
namespace VuFindSearch\Backend\RecordCache\Response;

use VuFindSearch\Response\AbstractRecordCollection;

class RecordCollection extends AbstractRecordCollection
{

    protected $response;

    public function __construct($response)
    {
        $this->response = $response;
        $this->rewind();
    }

    public function getTotal()
    {
        return 1;
    }

    public function getFacets()
    {
        return null;
    }
}