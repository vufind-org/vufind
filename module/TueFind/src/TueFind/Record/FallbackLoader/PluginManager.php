<?php

namespace TueFind\Record\FallbackLoader;

class PluginManager extends \VuFind\Record\FallbackLoader\PluginManager {
    public function __construct($configOrContainerInstance = null,
        array $v3config = []
    ) {
        $this->aliases['solr'] = 'TueFind\Record\FallbackLoader\Solr';
        $this->factories['TueFind\Record\FallbackLoader\Solr'] = 'TueFind\Record\FallbackLoader\SolrFactory';
        parent::__construct($configOrContainerInstance, $v3config);
    }
}
