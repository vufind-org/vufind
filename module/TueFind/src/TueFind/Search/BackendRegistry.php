<?php

namespace TueFind\Search;

class BackendRegistry extends \VuFind\Search\BackendRegistry {
    public function __construct($configOrContainerInstance = null,
        array $v3config = []
    ) {
        $this->factories['SolrAuth'] = 'TueFind\Search\Factory\SolrAuthBackendFactory';
        $this->factories['Search3'] = 'TueFind\Search\Factory\Search3BackendFactory';
        parent::__construct($configOrContainerInstance, $v3config);
    }
}
