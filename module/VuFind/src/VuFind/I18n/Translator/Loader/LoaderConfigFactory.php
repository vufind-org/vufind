<?php

namespace VuFind\I18n\Translator\Loader;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class LoaderConfigFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new $requestedName($this->getConfig($container));
    }

    protected function getConfig(ContainerInterface $container): array
    {
        return array_reverse($container->get('config')['vufind']['translator_loader'] ?? []);
    }
}