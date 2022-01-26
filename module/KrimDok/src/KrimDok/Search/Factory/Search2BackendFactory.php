<?php

namespace KrimDok\Search\Factory;

class Search2BackendFactory extends SolrDefaultBackendFactory
{
    public function __construct()
    {
        parent::__construct();
        $this->mainConfig = $this->searchConfig = $this->facetConfig = 'Search2';
        $this->searchYaml = 'searchspecs2.yaml';
    }
}
