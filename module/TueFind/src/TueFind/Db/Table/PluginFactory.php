<?php

namespace TueFind\Db\Table;

class PluginFactory extends \VuFind\Db\Table\PluginFactory {
    public function __construct()
    {
        $this->defaultNamespace = 'TueFind\Db\Table';
    }
}
