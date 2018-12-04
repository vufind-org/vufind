<?php

namespace VuFind\I18n\Translator\Loader;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class DirectoryLoaderFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $loader = $container->get(PluginManager::class)->get(LoaderInterface::class);
        return new $requestedName($loader, $options['dir'], $options['ext']);
    }
}