<?php

namespace TueFind\Meta;

use Interop\Container\ContainerInterface;

class PluginFactory extends \VuFind\ServiceManager\AbstractPluginFactory
{
    public function __construct()
    {
        $this->defaultNamespace = 'TueFind\Meta';
    }

    public function __invoke(ContainerInterface $container, $requestedName,
        array $options = null
    ) {
        $class = $this->getClassName($requestedName);
        return new $class($container->get('ViewHelperManager')->get('HeadMeta'));
    }
}
