<?php
namespace IxTheo\RecordTab;

//use VuFind\RecordDriver\AbstractBase as AbstractRecordDriver;

class PluginManager extends \VuFind\RecordTab\PluginManager
{

   public function __construct($configOrContainerInstance = null,
        array $v3config = []
    ) {
        $this->addAbstractFactory('IxTheo\RecordTab\PluginFactory');
        parent::__construct($configOrContainerInstance, $v3config);
    }
}
?>
