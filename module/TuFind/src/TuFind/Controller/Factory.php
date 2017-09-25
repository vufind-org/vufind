<?php

namespace TuFind\Controller;

use Zend\ServiceManager\ServiceManager;

class Factory extends \VuFind\Controller\Factory
{
    public static function getPDAProxyController(ServiceManager $sm) {
        return new PDAProxyController($sm->getServiceLocator());
    }

    public static function getProxyController(ServiceManager $sm) {
        return new PDAProxyController($sm->getServiceLocator());
    }
}