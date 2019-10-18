<?php

namespace TueFind\Meta;

class PluginManager extends \VuFind\ServiceManager\AbstractPluginManager {
    public function __construct($configOrContainerInstance = null,
        array $v3config = []
    ) {
        $this->addAbstractFactory(PluginFactory::class);
        parent::__construct($configOrContainerInstance, $v3config);
    }

    protected function getExpectedInterface()
    {
        return MetaInterface::class;
    }

}