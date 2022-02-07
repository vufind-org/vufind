<?php

namespace TueFind\View\Helper\TueFind;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class AuthorityFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName,
        array $options = null
    ) {
        if (!empty($options)) {
            throw new \Exception('Unexpected options sent to factory.');
        }

        return new $requestedName(
            $container->get(\VuFindSearch\Service::class),
            $container->get('ViewHelperManager'),
            $container->get(\VuFind\Record\Loader::class),
            $container->get(\VuFind\Db\Table\PluginManager::class)
        );
    }
}
