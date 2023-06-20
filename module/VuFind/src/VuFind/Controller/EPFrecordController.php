<?php

namespace VuFind\Controller;

use Laminas\ServiceManager\ServiceLocatorInterface;
use VuFindSearch\ParamBag;

class EPFrecordController extends AbstractRecord
{

    public function __construct(ServiceLocatorInterface $sm)
    {
        $this->sourceId = 'EPF';
        parent::__construct($sm);
    }

}