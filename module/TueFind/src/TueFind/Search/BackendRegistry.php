<?php

namespace TueFind\Search;

class BackendRegistry extends \VuFind\Search\BackendRegistry {
    public function __construct($configOrContainerInstance = null,
        array $v3config = []
    ) {
        $this->factories['SolrAuth'] = 'TueFind\Search\Factory\SolrAuthBackendFactory';
        parent::__construct($configOrContainerInstance, $v3config);
    }
}
