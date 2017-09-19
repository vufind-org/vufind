<?php

namespace IxTheo\Controller;

use Zend\ServiceManager\ServiceManager;

class Factory extends \VuFind\Controller\Factory
{
    public static function getBrowseController(ServiceManager $sm)
    {
        return new BrowseController(
            $sm->getServiceLocator()->get('VuFind\Config')->get('config')
        );
    }

    public static function getRecordController(ServiceManager $sm)
    {
        return new RecordController(
            $sm->getServiceLocator()->get('VuFind\Config')->get('config')
        );
    }
}