<?php

namespace KrimDok\Search\Results;

class PluginFactory extends \VuFind\Search\Results\PluginFactory {
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->defaultNamespace = 'KrimDok\Search';
        $this->classSuffix = '\Results';
    }
}
