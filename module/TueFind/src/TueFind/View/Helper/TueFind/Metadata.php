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

    /**
     * Decide which Plugins to load for the given RecordDriver
     * dependant on configuration. (only by class name,
     * namespace will not be considered)
     *
     * @param \VuFind\RecordDriver\DefaultRecord $driver
     */
    public function generateMetatags(\VuFind\RecordDriver\DefaultRecord $driver) {
        $driverClassLabel = basename('/' . str_replace('\\', '/', get_class($driver)));
        $recordDrivers = $this->config->MetadataVocabularies ?? [];
        foreach ($recordDrivers as $recordDriver => $metatagTypes) {
            if ($driverClassLabel == $recordDriver) {
                foreach ($metatagTypes as $metatagType)
                    $this->pluginManager->get($metatagType)->addMetatags($driver);
            }
        }
    }
}
