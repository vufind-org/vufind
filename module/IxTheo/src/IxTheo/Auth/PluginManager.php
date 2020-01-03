<?php

namespace IxTheo\Auth;

class PluginManager extends \VuFind\Auth\PluginManager {
    public function __construct($configOrContainerInstance = null,
        array $v3config = []
    ) {
        $this->aliases['database'] = Database::class;
        $this->aliases['db'] = Database::class;
        $this->factories[Database::class] = \Zend\ServiceManager\Factory\InvokableFactory::class;
        parent::__construct($configOrContainerInstance, $v3config);
    }
}
