<?php

namespace IxTheo\ServiceManager;

use Interop\Container\ContainerInterface;

class AbstractPluginManagerFactory extends \VuFind\ServiceManager\AbstractPluginManagerFactory {
    private $redirects = [
        'VuFind\Db\Row\PluginManager' => 'IxTheo\Db\Row\PluginManager',
        'VuFind\Db\Table\PluginManager' => 'IxTheo\Db\Table\PluginManager',
    ];

    /**
     * Necessary because certain classes cannot be overridden in module.config.php anymore since Vufind5
     */
    public function __invoke(ContainerInterface $container, $requestedName,
        array $options = null
    ) {
        if (isset($this->redirects[$requestedName]))
            $requestedName = $this->redirects[$requestedName];

        return parent::__invoke($container, $requestedName, $options);
    }
}
