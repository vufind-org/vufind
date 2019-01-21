<?php

namespace IxTheo\Search;

class BackendRegistry extends \VuFind\Search\BackendRegistry {
    public function __construct($configOrContainerInstance = null,
        array $v3config = []
    ) {
        $this->factories['Solr'] = 'IxTheo\Search\Factory\SolrDefaultBackendFactory';
        parent::__construct($configOrContainerInstance, $v3config);
    }
}
