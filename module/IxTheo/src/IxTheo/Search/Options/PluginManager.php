<?php

namespace IxTheo\Search\Options;

use VuFind\Search\Options\OptionsFactory;

class PluginManager extends \TueFind\Search\Options\PluginManager {
    public function __construct($configOrContainerInstance = null,
        array $v3config = []
    ) {
        $this->aliases['search2'] = \IxTheo\Search\Search2\Options::class;
        $this->aliases['solr'] = \IxTheo\Search\Solr\Options::class;
        $this->aliases['keywordchainsearch'] = \IxTheo\Search\KeywordChainSearch\Options::class;
        $this->aliases['Subscriptions'] = \IxTheo\Search\Subscriptions\Options::class;
        $this->aliases['pdasubscriptions'] = \IxTheo\Search\PDASubscriptions\Options::class;

        $this->factories[\IxTheo\Search\Search2\Options::class] = OptionsFactory::class;
        $this->factories[\IxTheo\Search\Solr\Options::class] = OptionsFactory::class;
        $this->factories[\IxTheo\Search\KeywordChainSearch\Options::class] = OptionsFactory::class;
        $this->factories[\IxTheo\Search\Subscriptions\Options::class] = OptionsFactory::class;
        $this->factories[\IxTheo\Search\PDASubscriptions\Options::class] = OptionsFactory::class;

        parent::__construct($configOrContainerInstance, $v3config);
    }
}
