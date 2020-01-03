<?php

namespace TueFind\Record\FallbackLoader;

class PluginManager extends \VuFind\Record\FallbackLoader\PluginManager {
    public function __construct($configOrContainerInstance = null,
        array $v3config = []
    ) {
        $this->aliases['solr'] = Solr::class;
        $this->factories[Solr::class] = SolrFactory::class;
        parent::__construct($configOrContainerInstance, $v3config);
    }
}
