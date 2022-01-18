<?php

namespace KrimDok\Search\Params;

class PluginManager extends \VuFind\Search\Params\PluginManager {
    public function __construct($configOrContainerInstance = null,
        array $v3config = []
    ) {
        $this->aliases['solr'] = \KrimDok\Search\Solr\Params::class;
        $this->factories[\KrimDok\Search\Solr\Params::class] = \VuFind\Search\Solr\ParamsFactory::class;
        parent::__construct($configOrContainerInstance, $v3config);
    }


    protected function _addAliasesAndFactories()
    {
        parent::_addAliasesAndFactories();
        $this->aliases['solr'] = \KrimDok\Search\Solr\Params::class;
    }
}
