<?php

namespace IxTheo\Search\KeywordChainSearch;

use Interop\Container\ContainerInterface;

class ResultsFactory extends \VuFind\Search\Results\ResultsFactory {
    public function __invoke(ContainerInterface $container, $requestedName,
        array $options = null
    ) {
        $results = parent::__invoke($container, $requestedName, $options);
        $config = $container->get('VuFind\Config\PluginManager')->get('config');
        return $results;
    }
}
