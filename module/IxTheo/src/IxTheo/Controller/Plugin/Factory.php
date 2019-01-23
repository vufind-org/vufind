<?php
namespace IxTheo\Controller\Plugin;
use Zend\ServiceManager\ServiceManager;

class Factory
{
    public static function getPDASubscriptions(ServiceManager $sm)
    {
        return new PDASubscriptions($sm);
    }
}
