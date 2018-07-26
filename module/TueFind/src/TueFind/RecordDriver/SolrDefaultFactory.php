<?php

namespace TueFind\RecordDriver;

use Interop\Container\ContainerInterface;

class SolrDefaultFactory extends \VuFind\RecordDriver\SolrDefaultFactory {

    public function __invoke(ContainerInterface $container, $requestedName,
        array $options = null
    ) {
        $driver = parent::__invoke($container, $requestedName, $options);
        $driver->attachSearchService($container->get('VuFindSearch\Service'));
        $driver->setContainer($container);
        return $driver;
    }
}
