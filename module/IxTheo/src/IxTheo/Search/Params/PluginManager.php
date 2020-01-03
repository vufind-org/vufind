<?php

namespace IxTheo\Search\Params;

use VuFind\Search\Solr\ParamsFactory;

class PluginManager extends \VuFind\Search\Params\PluginManager {
    public function __construct($configOrContainerInstance = null,
        array $v3config = []
    ) {
        $this->aliases['solr'] = \IxTheo\Search\Solr\Params::class;
        $this->aliases['keywordchainsearch'] = \IxTheo\Search\KeywordChainSearch\Params::class;

        $this->factories[\IxTheo\Search\Solr\Params::class] = ParamsFactory::class;
        $this->factories[\IxTheo\Search\KeywordChainSearch\Params::class] = ParamsFactory::class;

        parent::__construct($configOrContainerInstance, $v3config);
    }
}
