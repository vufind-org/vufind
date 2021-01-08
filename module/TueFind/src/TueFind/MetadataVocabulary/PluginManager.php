<?php

namespace TueFind\MetadataVocabulary;

class PluginManager extends \VuFind\MetadataVocabulary\PluginManager {
    public function __construct($configOrContainerInstance = null,
        array $v3config = []
    ) {
        $this->addAbstractFactory(PluginFactory::class);
        parent::__construct($configOrContainerInstance, $v3config);
    }
}
