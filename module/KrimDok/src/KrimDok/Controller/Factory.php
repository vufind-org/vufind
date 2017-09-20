<?php

namespace KrimDok\Controller;
use Zend\ServiceManager\ServiceManager;

class Factory
{
    public static function getBrowseController(ServiceManager $sm)
    {
        return new BrowseController(
            $sm->getServiceLocator()->get('VuFind\Config')->get('config')
        );
    }

    public static function getFIDSystematikController(ServiceManager $sm) {
        return new FIDSystematikController($sm->getServiceLocator());
    }

    public static function getHelpController(ServiceManager $sm) {
        return new HelpController($sm->getServiceLocator());
    }

    public static function getSearchController(ServiceManager $sm) {
        return new SearchController($sm->getServiceLocator());
    }
}
