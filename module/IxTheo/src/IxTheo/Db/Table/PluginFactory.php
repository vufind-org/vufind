<?php

namespace IxTheo\Db\Table;

class PluginFactory extends \VuFind\Db\Table\PluginFactory {
    public function __construct()
    {
        $this->defaultNamespace = 'IxTheo\Db\Table';
    }
}
