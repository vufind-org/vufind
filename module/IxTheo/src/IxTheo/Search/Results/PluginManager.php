<?php

namespace IxTheo\Search\Results;

class PluginManager extends \TueFind\Search\Results\PluginManager {
    public function __construct($configOrContainerInstance = null,
        array $v3config = []
    ) {
        $this->aliases['solr'] = 'IxTheo\Search\Solr\Results';
        $this->aliases['keywordchainsearch'] = 'IxTheo\Search\KeywordChainSearch\Results';

        $this->factories['IxTheo\Search\Solr\Results'] = 'VuFind\Search\Solr\ResultsFactory';
        $this->factories['IxTheo\Search\KeywordChainSearch\Results'] = 'VuFind\Search\Solr\ResultsFactory';

        parent::__construct($configOrContainerInstance, $v3config);
    }
}
