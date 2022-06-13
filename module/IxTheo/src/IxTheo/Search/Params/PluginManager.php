<?php

namespace IxTheo\Search\Params;

class PluginManager extends \TueFind\Search\Params\PluginManager {
    protected function _addAliasesAndFactories()
    {
        parent::_addAliasesAndFactories();
        $this->aliases['solr'] = \IxTheo\Search\Solr\Params::class;
        $this->aliases['search2'] = \IxTheo\Search\Search2\Params::class;
        $this->aliases['keywordchainsearch'] = \IxTheo\Search\KeywordChainSearch\Params::class;
    }
}
