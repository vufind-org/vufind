<?php

namespace IxTheo\Search\Options;

class PluginManager extends \VuFind\Search\Options\PluginManager {
    public function __construct($configOrContainerInstance = null,
        array $v3config = []
    ) {
        $this->aliases['solr'] = 'IxTheo\Search\Solr\Options';
        $this->aliases['keywordchainsearch'] = 'IxTheo\Search\KeywordChainSearch\Options';
        $this->aliases['Subscriptions'] = 'IxTheo\Search\Subscriptions\Options';
        $this->aliases['pdasubscriptions'] = 'IxTheo\Search\PDASubscriptions\Options';

        $this->factories['IxTheo\Search\Solr\Options'] = 'VuFind\Search\Options\OptionsFactory';
        $this->factories['IxTheo\Search\KeywordChainSearch\Options'] = 'VuFind\Search\Options\OptionsFactory';
        $this->factories['IxTheo\Search\Subscriptions\Options'] = 'VuFind\Search\Options\OptionsFactory';
        $this->factories['IxTheo\Search\PDASubscriptions\Options'] = 'VuFind\Search\Options\OptionsFactory';

        parent::__construct($configOrContainerInstance, $v3config);
    }
}
