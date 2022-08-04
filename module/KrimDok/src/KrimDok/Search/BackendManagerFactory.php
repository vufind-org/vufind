<?php

namespace KrimDok\Search;

use Interop\Container\ContainerInterface;

class BackendManagerFactory extends \VuFind\Search\BackendManagerFactory {

    // same as parent, but we return KrimDok\Search\BackendRegistry instead
    protected function getRegistry(ContainerInterface $container)
    {
        $config = $container->get('config');
        return new BackendRegistry(
            $container, $config['vufind']['plugin_managers']['search_backend']
        );
    }
}
