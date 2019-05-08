<?php

namespace TueFind\ContentBlock;

use Interop\Container\ContainerInterface;

class BlockLoaderFactory extends \VuFind\ContentBlock\BlockLoaderFactory
{
    public function __invoke(ContainerInterface $container, $requestedName,
        array $options = null
    ) {
        if (!empty($options)) {
            throw new \Exception('Unexpected options sent to factory.');
        }
        return new $requestedName(
            $container->get('VuFind\Search\Options\PluginManager'),
            $container->get('VuFind\Config\PluginManager'),
            $container->get('VuFind\ContentBlock\PluginManager'),
            $container->get('Request')
        );
    }
}
