<?php

namespace IxTheo\Search;

class BackendRegistry extends \TueFind\Search\BackendRegistry {
    public function __construct($configOrContainerInstance = null,
        array $v3config = []
    ) {
        $this->factories['Solr'] = 'IxTheo\Search\Factory\SolrDefaultBackendFactory';
        $this->factories['Search2'] = 'IxTheo\Search\Factory\Search2BackendFactory';
        parent::__construct($configOrContainerInstance, $v3config);
    }
}
