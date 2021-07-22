<?php

namespace TueFind\AjaxHandler;

use Interop\Container\ContainerInterface;

class GetSubscriptionBundleEntriesFactory
    implements \Laminas\ServiceManager\Factory\FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName,
        array $options = null
    ) {
        if (!empty($options)) {
            throw new \Exception('Unexpected options passed to factory.');
        }
        return new $requestedName(
            $container->get(\VuFind\Search\Results\PluginManager::class)
        );
    }
}
