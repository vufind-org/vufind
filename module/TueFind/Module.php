<?php
namespace TueFind;
use Zend\ModuleManager\ModuleManager,
    Zend\Mvc\MvcEvent;

class Module
{
    /**
     * Get module configuration
     *
     * @return array
     */
    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    /**
     * Get autoloader configuration
     *
     * @return array
     */
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

    /**
     * Initialize the module
     *
     * @param ModuleManager $m Module manager
     *
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function init(ModuleManager $m)
    {
    }

    /**
     * Bootstrap the module
     *
     * @param MvcEvent $e Event
     *
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function onBootstrap(MvcEvent $e)
    {
        BotProtect::CheckRequest($e);
    }
}
