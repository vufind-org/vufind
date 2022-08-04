<?php

namespace IxTheo\Search\Results;

class PluginManager extends \TueFind\Search\Results\PluginManager {
    protected function _addAliasesAndFactories() {
        parent::_addAliasesAndFactories();
        $this->aliases['search2'] = \IxTheo\Search\Search2\Results::class;
        $this->aliases['solr'] = \IxTheo\Search\Solr\Results::class;
        $this->aliases['keywordchainsearch'] = \IxTheo\Search\KeywordChainSearch\Results::class;
        $this->aliases['Subscriptions'] = \IxTheo\Search\Subscriptions\Results::class;
        $this->aliases['pdasubscriptions'] = \IxTheo\Search\PDASubscriptions\Results::class;

        $this->factories[\IxTheo\Search\Search2\Results::class] = \VuFind\Search\Search2\ResultsFactory::class;
        $this->factories[\IxTheo\Search\Solr\Results::class] = \VuFind\Search\Solr\ResultsFactory::class;
        $this->factories[\IxTheo\Search\KeywordChainSearch\Results::class] = \VuFind\Search\Solr\ResultsFactory::class;
        $this->factories[\IxTheo\Search\Subscriptions\Results::class] = \IxTheo\Search\Subscriptions\ResultsFactory::class;
        $this->factories[\IxTheo\Search\PDASubscriptions\Results::class] = \IxTheo\Search\PDASubscriptions\ResultsFactory::class;
    }
}
