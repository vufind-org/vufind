<?php
  
namespace KrimDok\Search;

class BackendRegistry extends \TueFind\Search\BackendRegistry {
    public function __construct($configOrContainerInstance = null,
        array $v3config = []
    ) {
        $this->factories['solr'] = 'KrimDok\Search\Factory\SolrDefaultBackendFactory';
        $this->factories['Search2'] = 'KrimDok\Search\Factory\Search2BackendFactory';
        parent::__construct($configOrContainerInstance, $v3config);
    }
}   
