<?php

namespace VuFind\ILS\Driver;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class AlmaFactory implements FactoryInterface
{    
    /**
     * {@inheritDoc}
     * @see \Zend\ServiceManager\Factory\FactoryInterface::__invoke()
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null) {
            // Set up the driver with the date converter (and any extra parameters passed in as options):
            $driver = new $requestedName(
                $container->get('VuFind\Date\Converter'),
                $container->get('VuFind\Config\PluginManager'),
                ...($options ?: [])
            );
            
            // Populate cache storage if a setCacheStorage method is present:
            if (method_exists($driver, 'setCacheStorage')) {
                $driver->setCacheStorage($container->get('VuFind\Cache\Manager')->getCache('object'));
            }
            
            return $driver;
    }
}

?>