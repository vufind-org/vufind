<?php
namespace TueFind\RecordTab;

class PluginFactory extends \VuFind\RecordTab\PluginFactory
{
    public function __construct()
    {
        $this->defaultNamespace = 'TueFind\RecordTab';
    }
}
?>
