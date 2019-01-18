<?php

namespace KrimDok\Search\Params;

class PluginFactory extends \VuFind\Search\Params\PluginFactory
{
    public function __construct()
    {
        $this->defaultNamespace = 'KrimDok\Search';
        $this->classSuffix = '\Params';
    }
}
