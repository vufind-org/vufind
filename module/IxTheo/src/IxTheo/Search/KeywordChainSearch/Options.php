<?php

namespace IxTheo\Search\KeywordChainSearch;

class Options extends \VuFind\Search\Solr\Options {
    public function __construct (\VuFind\Config\PluginManager $configLoader){
        parent::__construct($configLoader);
    }

    public function getSearchAction(){
        return 'keywordchainsearch-results';
    }

    public function getSearchHomeAction(){
        return 'keywordchainsearch-home';
    }

    // We do not have advanced Search - however a
    // missing route error occurs if false is returned
    // as suggested in vufind documentation
    public function getAdvancedSearchAction(){
        return 'keywordchainsearch-home';
    }

    // We only get Facets from Solr, so our resultLimit is
    // set to 0. Thus, we have to set this manually
    // We want to process all available facets so don't restrict
    public function getVisibleSearchResultLimit(){
        return -1;
    }
}
