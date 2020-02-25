<?php

namespace TueFind\Recommend;

use Zend\ServiceManager\Factory\InvokableFactory;

class PluginManager extends \VuFind\Recommend\PluginManager {
    public function __construct($configOrContainerInstance = null,
        array $v3config = []
    ) {
        $this->aliases['ids'] = Ids::class;
        $this->aliases['sidefacets'] = SideFacets::class;
        $this->factories[Ids::class] = InvokableFactory::class;
        $this->factories[SideFacets::class] = SideFacetsFactory::class;
        parent::__construct($configOrContainerInstance, $v3config);
    }
}
