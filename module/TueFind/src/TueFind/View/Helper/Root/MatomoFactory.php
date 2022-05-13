<?php

namespace TueFind\View\Helper\Root;

use Interop\Container\ContainerInterface;

class MatomoFactory extends \VuFind\View\Helper\Root\MatomoFactory {
    public function __invoke(
        ContainerInterface $container,
        $requestedName,
        array $options = null
    ) {
        if (!empty($options)) {
            throw new \Exception('Unexpected options sent to factory.');
        }
        $config = $container->get(\VuFind\Config\PluginManager::class)
            ->get('config');
        $request = $container->get('Request');
        $router = $container->get('Router');

        // TueFind: Add Auth Manager
        $auth = $container->get(\VuFind\Auth\Manager::class);
        return new $requestedName($config, $router, $request, $auth);
    }
}
