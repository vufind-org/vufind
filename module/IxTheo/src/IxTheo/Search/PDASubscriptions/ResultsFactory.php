<?php

namespace IxTheo\Search\PDASubscriptions;

use Interop\Container\ContainerInterface;

class ResultsFactory extends \VuFind\Search\Results\ResultsFactory
{
    public function __invoke(ContainerInterface $container, $requestedName,
        array $options = null
    ) {
        $results = parent::__invoke($container, $requestedName, $options);
        $results->setPDASubscriptionTable($container->get('VuFind\Db\Table\PluginManager')->get('pdasubscription'));

        $init = new \ZfcRbac\Initializer\AuthorizationServiceInitializer();
        $init($container, $results);

        return $results;
    }
}
