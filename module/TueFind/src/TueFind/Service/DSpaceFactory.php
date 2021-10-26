<?php

namespace TueFind\Service;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class DSpaceFactory implements FactoryInterface {
    public function __invoke(ContainerInterface $container, $requestedName,
        array $options = null
    ) {
        if (!empty($options)) {
            throw new \Exception('Unexpected options passed to factory.');
        }

        $config = $container->get(\VuFind\Config\PluginManager::class)
            ->get('tuefind')->Publication;

        return new $requestedName(
            $config->dspace_url, $config->dspace_username, $config->dspace_password
        );
    }
}
