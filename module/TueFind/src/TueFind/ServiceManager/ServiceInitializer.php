<?php

namespace TueFind\ServiceManager;

use Interop\Container\ContainerInterface;

class ServiceInitializer extends \VuFind\ServiceManager\ServiceInitializer {
    /**
     * Given an instance and a Service Manager, initialize the instance.
     *
     * @param ContainerInterface $sm       Service manager
     * @param object             $instance Instance to initialize
     *
     * @return object
     */
    public function __invoke(ContainerInterface $sm, $instance)
    {
        $instance = parent::__invoke($sm, $instance);
        if ($instance instanceof ConfigAwareInterface) {
            $instance->setConfig($sm->get('VuFind\Config\PluginManager'));
        }
        return $instance;
    }
}
