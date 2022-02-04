<?php

namespace KrimDok\Search\Results;

class PluginManager extends \TueFind\Search\Results\PluginManager {
    protected function _addAliasesAndFactories() {
        parent::_addAliasesAndFactories();
        $this->aliases['search2'] = \KrimDok\Search\Search2\Results::class;
        $this->aliases['solr'] = \KrimDok\Search\Solr\Results::class;

        $this->factories[\KrimDok\Search\Search2\Results::class] = \VuFind\Search\Search2\ResultsFactory::class;
        $this->factories[\KrimDok\Search\Solr\Results::class] = \VuFind\Search\Solr\ResultsFactory::class;
    }
}
