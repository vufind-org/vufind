<?php

namespace TueFind\ContentBlock;

class PluginManager extends \VuFind\ContentBlock\PluginManager
{
    public function __construct($configOrContainerInstance = null,
        array $v3config = []
    ) {
        $this->aliases['home'] = 'TueFind\ContentBlock\Home';
        $this->factories['TueFind\ContentBlock\Home'] = 'Zend\ServiceManager\Factory\InvokableFactory';
        parent::__construct($configOrContainerInstance, $v3config);
    }
}
