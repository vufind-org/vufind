<?php
namespace TueFindSearch;

use Laminas\EventManager\EventManagerInterface;

class Service extends \VuFindSearch\Service {

    public function __construct(EventManagerInterface $events = null) {
        parent::__construct($events);
    }
}
