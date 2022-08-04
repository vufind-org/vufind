<?php

namespace TueFind\Search\Results;

class PluginManager extends \VuFind\Search\Results\PluginManager {
    public function __construct($configOrContainerInstance = null,
        array $v3config = []
    ) {
        $this->_addAliasesAndFactories();
        parent::__construct($configOrContainerInstance, $v3config);
    }

    /**
     * We need this function for overriding in derived modules.
     * This way, children can call parent and override parent settings before constructing the object.
     */
    protected function _addAliasesAndFactories() {
        $this->aliases['solr'] = \TueFind\Search\Solr\Results::class;
        $this->aliases['solrauth'] = \TueFind\Search\SolrAuth\Results::class;
        $this->aliases['solrauthorfacets'] = \TueFind\Search\SolrAuthorFacets\Results::class;
        $this->aliases['search3'] = \TueFind\Search\Search3\Results::class;
        $this->factories[\TueFind\Search\Solr\Results::class] = \VuFind\Search\Solr\ResultsFactory::class;
        $this->factories[\TueFind\Search\SolrAuth\Results::class] = \VuFind\Search\Solr\ResultsFactory::class;
        $this->factories[\TueFind\Search\SolrAuthorFacets\Results::class] = \VuFind\Search\Solr\ResultsFactory::class;
        $this->factories[\TueFind\Search\Search3\Results::class] = \TueFind\Search\Search3\ResultsFactory::class;
    }
}
