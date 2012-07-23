<?php

namespace VuFind;
use VuFind\Bootstrap,
    Zend\ModuleManager\ModuleManager,
    Zend\Mvc\MvcEvent;

class Module
{
    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    public function getAutoloaderConfig()
    {
        return array(
            'Zend\Loader\ClassMapAutoloader' => array(
                'classes' => array(
                    'minSO' => __DIR__ . '/src/VuFind/Search/minSO.php'
                )
            ),
            'Zend\Loader\StandardAutoloader' => array(
                'namespaces' => array(
                    __NAMESPACE__ => __DIR__ . '/src/' . __NAMESPACE__,
                ),
            ),
        );
    }

    public function init(ModuleManager $m)
    {
    }

    public function onBootstrap(MvcEvent $e)
    {
        $bootstrapper = new Bootstrap($e);
        $bootstrapper->bootstrap();
    }
}
