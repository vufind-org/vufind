<?php

namespace IxTheo\ContentBlock;


class PluginManager extends \VuFind\ContentBlock\PluginManager
{
    public function __construct($configOrContainerInstance = null,
        array $v3config = []
    ) {
        $this->aliases['home'] = 'IxTheo\ContentBlock\Home';
        $this->factories['IxTheo\ContentBlock\Home'] = 'Zend\ServiceManager\Factory\InvokableFactory';
        parent::__construct($configOrContainerInstance, $v3config);
    }
}
