<?php

namespace TueFind\Service;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;


class KfLFactory implements FactoryInterface
{

    public function __invoke(ContainerInterface $container, $requestedName,
        array $options = null
    ) {
        if (!empty($options)) {
            throw new \Exception('Unexpected options passed to factory.');
        }

        $authManager = $container->get(\VuFind\Auth\Manager::class);
        $tuefindInstance = $container->get('ViewHelperManager')->get('tuefind')->getTueFindInstance();
        $config = $container->get(\VuFind\Config\PluginManager::class)
            ->get('tuefind')->KfL;

        return new $requestedName(
            $config, $authManager, $tuefindInstance
        );
    }
}
