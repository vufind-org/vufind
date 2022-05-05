<?php

namespace TueFind\Http;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class CachedDownloaderFactory implements FactoryInterface {
    public function __invoke(ContainerInterface $container, $requestedName,
        array $options = null
    ) {
        if (!empty($options)) {
            throw new \Exception('Unexpected options passed to factory.');
        }

        return new $requestedName(
            $client = $container->get(\VuFindHttp\HttpService::class)->createClient(),
            $container->get(\VuFind\Cache\Manager::class),
        );
    }
}
