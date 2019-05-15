<?php

namespace VuFind\UrlShortener;

use Interop\Container\ContainerInterface;

class DatabaseFactory {

    /**
     * Create Database object
     *
     * @param ContainerInterface $container
     * @param type $requestedName
     * @param array $options
     * @return \VuFind\UrlShortener\requestedName
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $config = $container->get(\VuFind\Config\PluginManager::class);
        $table = $container->get(\VuFind\Db\Table\PluginManager::class)->get('shortlinks');
        return new $requestedName($config, $table);
    }
}
