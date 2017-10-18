<?php

namespace KrimDok\Controller;

use Zend\ServiceManager\ServiceManager;

class Factory extends \TuFind\Controller\Factory
{
    public static function getBrowseController(ServiceManager $sm)
    {
        return new BrowseController(
            $sm->getServiceLocator(),
            $sm->getServiceLocator()->get('VuFind\Config')->get('config')
        );
    }
}
