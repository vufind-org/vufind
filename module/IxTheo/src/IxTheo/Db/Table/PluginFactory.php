<?php

namespace IxTheo\Db\Table;

class PluginFactory extends \TueFind\Db\Table\PluginFactory {
    public function __construct()
    {
        $this->defaultNamespace = 'IxTheo\Db\Table';
    }
}
