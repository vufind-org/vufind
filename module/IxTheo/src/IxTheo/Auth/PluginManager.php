<?php

namespace IxTheo\Auth;

class PluginManager extends \VuFind\Auth\PluginManager {
    public function __construct($configOrContainerInstance = null,
        array $v3config = []
    ) {
        $this->aliases['database'] = 'IxTheo\Auth\Database';
        $this->aliases['db'] = 'IxTheo\Auth\Database';
        $this->factories['IxTheo\Auth\Database'] = 'Zend\ServiceManager\Factory\InvokableFactory';
        parent::__construct($configOrContainerInstance, $v3config);
    }
}
