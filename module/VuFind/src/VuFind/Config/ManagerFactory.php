<?php

namespace VuFind\Config;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class ManagerFactory implements FactoryInterface
{
    /**
     * @param ContainerInterface $container
     * @param string             $requestedName
     * @param array|null         $options
     *
     * @return Manager
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function __invoke(
        ContainerInterface $container,
        $requestedName,
        array $options = null
    ): Manager {

        $applicationConfig = $container->get('ApplicationConfig');
        $moduleListenerOptions = $applicationConfig['module_listener_options'];

        return new $requestedName(...[
            APPLICATION_PATH . '/config/config.php',
            $moduleListenerOptions['cache_dir'],
            $moduleListenerOptions['config_cache_enabled']
        ]);
    }
}