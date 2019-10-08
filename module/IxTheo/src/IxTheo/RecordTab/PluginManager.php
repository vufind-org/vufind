<?php

namespace IxTheo\RecordTab;

class PluginManager extends \TueFind\RecordTab\PluginManager
{
   public function __construct($configOrContainerInstance = null, array $v3config = []) {
       $this->addAbstractFactory('IxTheo\RecordTab\PluginFactory');
       parent::__construct($configOrContainerInstance, $v3config);
   }
}
?>
