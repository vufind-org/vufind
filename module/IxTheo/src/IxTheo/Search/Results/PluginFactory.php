<?php

namespace IxTheo\Search\Results;

class PluginFactory extends \VuFind\Search\Results\PluginFactory {
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->defaultNamespace = 'IxTheo\Search';
        $this->classSuffix = '\Results';
    }
}
