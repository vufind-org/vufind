<?php

namespace IxTheo\Autocomplete;

class PluginManager extends \VuFind\Autocomplete\PluginManager {
    public function __construct($configOrContainerInstance = null,
        array $v3config = []
    ) {
        $this->aliases['solr'] = Solr::class;
        $this->factories[Solr::class] = SolrFactory::class;
        parent::__construct($configOrContainerInstance, $v3config);
    }
}
