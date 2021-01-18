<?php

namespace TueFind\MetadataVocabulary;

class PluginFactory extends \VuFind\MetadataVocabulary\PluginFactory
{
    public function __construct()
    {
        $this->defaultNamespace = 'TueFind\MetadataVocabulary';
    }
}
