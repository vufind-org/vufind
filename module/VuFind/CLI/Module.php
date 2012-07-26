<?php

namespace VuFind\CLI;
use VuFind\Mvc\Router\ConsoleRouter,
    Zend\ModuleManager\ModuleManager, Zend\Mvc\MvcEvent;

class Module
{
    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    public function getAutoloaderConfig()
    {
        // No extra configuration necessary; since this module uses a subset of the
        // VuFind namespace, its library code is in the main src area of the VuFind
        // module.
        return array();
    }

    public function init(ModuleManager $m)
    {
    }

    public function onBootstrap(MvcEvent $e)
    {
        $callback = function ($e) {
            $e->setRouter(new ConsoleRouter());
        };
        $events = $e->getApplication()->getEventManager();
        $events->attach('route', $callback, 10000);
    }
}
