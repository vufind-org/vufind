<?php

namespace KrimDok\ILS\Driver;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class KrimDokILSFactory extends \VuFind\ILS\Driver\NoILSFactory
{
    public function __invoke(ContainerInterface $container, $requestedName,
        array $options = null
    ) {
        if (!empty($options)) {
            throw new \Exception('Unexpected options passed to factory.');
        }
        return new KrimDokILS(
            $container->get('VuFind\RecordLoader'),
            $container->get('VuFind\Search')
        );
    }
}
