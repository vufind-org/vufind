<?php

namespace TueFind\Search\Params;

class PluginManager extends \VuFind\Search\Params\PluginManager {
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
    protected function _addAliasesAndFactories()
    {
        $this->aliases['solrauthorfacets'] = \TueFind\Search\SolrAuthorFacets\Params::class;
        $this->factories[\TueFind\Search\SolrAuthorFacets\Params::class] = \VuFind\Search\Solr\ParamsFactory::class;
    }
}
