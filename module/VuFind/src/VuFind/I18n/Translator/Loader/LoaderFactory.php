<?php

namespace VuFind\I18n\Translator\Loader;

use Interop\Container\ContainerInterface;
use ProxyManager\Factory\LazyLoadingValueHolderFactory as Factory;
use VuFind\I18n\Translator\Loader\Handler\HandlerManager;
use Zend\ServiceManager\Factory\FactoryInterface;

class LoaderFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $proxyConf = $container->get('ProxyManager\Configuration');
        return (new Factory($proxyConf))->createProxy(LoaderInterface::class,
            function (& $loader, $proxy, $method, $params, & $initializer) use ($container) {
                list ($loader, $initializer) = [$this->create($container), null];
            });
    }

    protected function create(ContainerInterface $container): LoaderInterface
    {
        $loader = new Loader();
        $handlerManager = $container->get(HandlerManager::class);
        foreach ($container->get(LoaderConfig::class) as $config) {
            $handler = $handlerManager->get($config['type']);
            $loader->attach($handler, $config['opts'] ?? [], $config['prio'] ?? 0);
        }
        return $loader;
    }
}