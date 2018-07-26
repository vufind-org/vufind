<?php

namespace TueFind\RecordDriver;

use Interop\Container\ContainerInterface;

class SolrMarcFactory extends SolrDefaultFactory {

    public function __invoke(ContainerInterface $container, $requestedName,
        array $options = null
    ) {
        $driver = parent::__invoke($container, $requestedName, $options);
        $driver->attachSearchService($container->get('VuFindSearch\Service'));
        $driver->setContainer($container);

        $driver->attachILS(
            $container->get('VuFind\ILSConnection'),
            $container->get('VuFind\ILSHoldLogic'),
            $container->get('VuFind\ILSTitleHoldLogic')
        );

        return $driver;
    }
}
