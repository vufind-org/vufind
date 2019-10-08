<?php

namespace TueFind\RecordTab;

class PluginManager extends \VuFind\RecordTab\PluginManager {

    public function __construct($configOrContainerInstance = null,
        array $v3config = []
    ) {
        $this->addAbstractFactory('TueFind\RecordTab\PluginFactory');
        parent::__construct($configOrContainerInstance, $v3config);
    }

}