<?php

namespace TueFind\View\Helper\TueFind;

class Metadata extends \Zend\View\Helper\AbstractHelper {

    protected $config;
    protected $pluginManager;

    public function __construct(\TueFind\MetadataVocabulary\PluginManager $pluginManager,
                                \Zend\Config\Config $config)
    {
        $this->pluginManager = $pluginManager;
        $this->config = $config;
    }

    public function generateMetatags(\VuFind\RecordDriver\DefaultRecord $driver) {
        $metatagTypes = $this->config->General->metatagTypes ?? [];
        foreach ($metatagTypes as $metatagType) {
            $this->pluginManager->get($metatagType)->addMetatags($driver);
        }
    }
}
