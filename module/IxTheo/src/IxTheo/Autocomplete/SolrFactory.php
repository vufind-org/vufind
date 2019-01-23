<?php

namespace IxTheo\Autocomplete;
use Zend\ServiceManager\ServiceManager;

class SolrFactory extends \VuFind\Autocomplete\SolrFactory
{
    public function __invoke(ContainerInterface $container, $requestedName,
        array $options = null
    ) {
        return new $requestedName(
            $container->get('VuFind\Search\Results\PluginManager')
        );
    }
}
