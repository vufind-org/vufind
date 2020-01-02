<?php

namespace TueFind\Recommend;

use Zend\ServiceManager\Factory\InvokableFactory;

class PluginManager extends \VuFind\Recommend\PluginManager {
    public function __construct($configOrContainerInstance = null,
        array $v3config = []
    ) {
        $this->aliases['ids'] = Ids::class;
        $this->factories[Ids::class] = InvokableFactory::class;
        parent::__construct($configOrContainerInstance, $v3config);
    }
}
