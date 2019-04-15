<?php
namespace TueFindSearch;

use VuFindSearch\Backend\BackendInterface;
use VuFindSearch\Backend\Exception\BackendException;
use VuFindSearch\Feature\RandomInterface;
use VuFindSearch\Feature\RetrieveBatchInterface;
use VuFindSearch\Response\RecordCollectionInterface;

use Zend\EventManager\EventManager;
use Zend\EventManager\EventManagerInterface;


class Service extends \VuFindSearch\Service {

    public function __construct(EventManagerInterface $events = null) {
        parent::__construct($events);
    }
}
