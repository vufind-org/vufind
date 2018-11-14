<?php

namespace VuFind\I18n\Translator\Loader;

use Interop\Container\ContainerInterface;
use ProxyManager\Factory\LazyLoadingValueHolderFactory as Factory;
use Zend\ServiceManager\Factory\FactoryInterface;

class LoaderFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $proxyConf = $container->get('ProxyManager\Configuration');
        return (new Factory($proxyConf))->createProxy(LoaderInterface::class,
            function (& $resolver, $proxy, $method, $params, & $initializer) use ($container) {
                list ($resolver, $initializer) = [$this->create($container), null];
            });
    }

    protected function create(ContainerInterface $container): LoaderInterface
    {
        $chainLoader = new PriorityChainLoader();
        $manager = $container->get(PluginManager::class);
        foreach ($container->get(LoaderConfig::class) as $config) {
            $loader = $manager->build($config['type'], $config);
            $chainLoader->attach($loader, $config['prio']);
        }
        return $chainLoader;
    }
}