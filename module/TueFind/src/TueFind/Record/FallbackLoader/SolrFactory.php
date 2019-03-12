<?php

namespace TueFind\Record\FallbackLoader;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class SolrFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName,
        array $options = null
    ) {
        if (!empty($options)) {
            throw new \Exception('Unexpected options passed to factory.');
        }
        return new $requestedName(
            $container->get('VuFindSearch\Service')
        );
    }
}
