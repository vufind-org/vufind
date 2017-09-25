<?php

namespace IxTheo\Search\Params;

class PluginFactory extends \VuFind\Search\Params\PluginFactory
{
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->defaultNamespace = 'IxTheo\Search';
        $this->classSuffix = '\Params';
    }
}
