<?php

namespace TueFind\Search\Factory;

class Search3BackendFactory extends SolrDefaultBackendFactory
{
    protected $createRecordMethod = 'getSearch3Record';

    public function __construct()
    {
        parent::__construct();
        $this->mainConfig = $this->searchConfig = $this->facetConfig = 'Search3';
        $this->searchYaml = 'searchspecs3.yaml';
    }
}
