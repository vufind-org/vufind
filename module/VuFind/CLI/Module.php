<?php

namespace VuFind\CLI;
use Zend\ModuleManager\ModuleManager,
    Zend\Mvc\MvcEvent, Zend\Mvc\Router\Http\RouteMatch;

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
            // Get command line arguments and present working directory from
            // server superglobal:
            $server = $e->getApplication()->getRequest()->getServer();
            $args = $server->get('argv');
            $filename = $args[0];
            $pwd = $server->get('PWD', CLI_DIR);

            // Convert base filename (minus .php extension) and containing directory
            // name into action and controller, respectively:
            $baseFilename = basename($filename);
            $baseFilename = substr($baseFilename, 0, strlen($baseFilename) - 4);
            $baseDirname = basename(dirname(realpath($pwd . '/' . $filename)));
            $routeMatch = new RouteMatch(
                array('controller' => $baseDirname, 'action' => $baseFilename), 1
            );

            // Override standard routing:
            $routeMatch->setMatchedRouteName('default');
            $e->setRouteMatch($routeMatch);
        };
        $events = $e->getApplication()->getEventManager();
        $events->attach('route', $callback);
    }
}
