<?php

namespace TueFind\View\Helper\TueFind;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class MetaFactory implements FactoryInterface {
    public function __invoke(ContainerInterface $container, $requestedName,
        array $options = null
    ) {
        if (!empty($options)) {
            throw new \Exception('Unexpected options sent to factory.');
        }

        return new Meta(
            $container->get('TueFind\Meta\PluginManager'),
            $container->get('VuFind\Config\PluginManager')->get('tuefind')
        );
    }
}
