<?php

namespace IxTheo\Autocomplete;

class PluginManager extends \VuFind\Autocomplete\PluginManager {
    public function __construct($configOrContainerInstance = null,
        array $v3config = []
    ) {
        $this->aliases['solr'] = 'IxTheo\Autocomplete\Solr';
        $this->factories['IxTheo\Autocomplete\Solr'] = 'IxTheo\Autocomplete\SolrFactory';
        parent::__construct($configOrContainerInstance, $v3config);
    }
}
