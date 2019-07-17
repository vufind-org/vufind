<?php
namespace IxTheo\RecordTab;

class PluginFactory extends \VuFind\RecordTab\PluginFactory
{
    public function __construct()
    {
        $this->defaultNamespace = 'IxTheo\RecordTab';
    }
}
?>
