<?php

namespace TueFind\ContentBlock;

use Zend\ServiceManager\Factory\InvokableFactory;

class PluginManager extends \VuFind\ContentBlock\PluginManager
{
    public function __construct($configOrContainerInstance = null,
        array $v3config = []
    ) {
        $this->aliases['home'] = Home::class;
        $this->factories[Home::class] = InvokableFactory::class;
        parent::__construct($configOrContainerInstance, $v3config);
    }
}
