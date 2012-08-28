<?php

namespace VuFindConsole;
use VuFindConsole\Mvc\Router\ConsoleRouter,
    Zend\ModuleManager\ModuleManager, Zend\Mvc\MvcEvent;

class Module
{
    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    public function getAutoloaderConfig()
    {
        return array(
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
        $callback = function ($e) {
            $e->setRouter(new ConsoleRouter());
        };
        $events = $e->getApplication()->getEventManager();
        $events->attach('route', $callback, 10000);
    }
}
