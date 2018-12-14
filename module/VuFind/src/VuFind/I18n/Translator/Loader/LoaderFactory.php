<?php

namespace VuFind\I18n\Translator\Loader;

use Interop\Container\ContainerInterface;
use ProxyManager\Factory\LazyLoadingValueHolderFactory as Factory;
use VuFind\I18n\Translator\Loader\Listener\ListenerInterface;
use VuFind\I18n\Translator\Loader\Listener\ListenerManager;
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
        $loaderEvents = $loader->getEventManager();
        $listenerManager = $container->get(ListenerManager::class);
        foreach ($container->get(LoaderConfig::class) as $config) {
            /** @var ListenerInterface $listener */
            $listener = $listenerManager->build($config['type'], $config['opts'] ?? []);
            $loaderEvents->attach($listener->getEventName(), $listener, $config['prio'] ?? 0);
            $listener->setEventManager($loaderEvents);
        }
        return $loader;
    }
}