<?php

namespace VuFind\Controller;

use Laminas\ServiceManager\ServiceLocatorInterface;

class EPFController extends AbstractSearch
{

    public function __construct(ServiceLocatorInterface $sm)
    {
        $this->searchClassId = 'EPF';
        parent::__construct($sm);
    }

    public function searchAction()
    {
        return $this->resultsAction();
    }

}