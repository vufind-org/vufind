<?php

namespace IxTheo\Search\Results;

class PluginManager extends \TueFind\Search\Results\PluginManager {
    public function __construct($configOrContainerInstance = null,
        array $v3config = []
    ) {
        $this->aliases['solr'] = 'IxTheo\Search\Solr\Results';
        $this->aliases['keywordchainsearch'] = 'IxTheo\Search\KeywordChainSearch\Results';
        $this->aliases['Subscriptions'] = 'IxTheo\Search\Subscriptions\Results';
        $this->aliases['pdasubscriptions'] = 'IxTheo\Search\PDASubscriptions\Results';

        $this->factories['IxTheo\Search\Solr\Results'] = 'VuFind\Search\Solr\ResultsFactory';
        $this->factories['IxTheo\Search\KeywordChainSearch\Results'] = 'VuFind\Search\Solr\ResultsFactory';
        $this->factories['IxTheo\Search\Subscriptions\Results'] = 'IxTheo\Search\Subscriptions\ResultsFactory';
        $this->factories['IxTheo\Search\PDASubscriptions\Results'] = 'IxTheo\Search\PDASubscriptions\ResultsFactory';

        parent::__construct($configOrContainerInstance, $v3config);
    }
}
