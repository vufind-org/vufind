<?php

namespace IxTheo\Search\Params;

class PluginManager extends \VuFind\Search\Params\PluginManager {
    public function __construct($configOrContainerInstance = null,
        array $v3config = []
    ) {
        $this->aliases['solr'] = 'IxTheo\Search\Solr\Params';
        $this->aliases['keywordchainsearch'] = 'IxTheo\Search\KeywordChainSearch\Params';

        $this->factories['IxTheo\Search\Solr\Params'] = 'VuFind\Search\Solr\ParamsFactory';
        $this->factories['IxTheo\Search\KeywordChainSearch\Params'] = 'VuFind\Search\Solr\ParamsFactory';

        parent::__construct($configOrContainerInstance, $v3config);
    }
}
