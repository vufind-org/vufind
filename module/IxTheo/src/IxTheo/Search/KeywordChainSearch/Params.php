<?php

namespace IxTheo\Search\KeywordChainSearch;

class Params extends \VuFind\Search\Solr\Params {
    public function __construct($options, \VuFind\Config\PluginManager $configLoader) {
        parent::__construct($options, $configLoader);
    }

   protected function initBasicSearch($request) {
        // If no lookfor parameter was found, we have no search terms to
        // add to our array!
        if (is_null($lookfor = $request->get('lookfor'))) {
            return false;
        }

        $this->setBasicSearch($lookfor, 'keywordChainSearch');
        return true;
    }

    // We have to add additional functionality to choose the qf field

    /**
     * Create search backend parameters for advanced features.
     *
     * @return ParamBag
     */
    public function getBackendParameters() {
        $backendParams = parent::getBackendParameters();

	// We are either called with a specific chain, thus we have to search
	// key_word_chains or we are looking in the flattened bag
        $backendParams->add('qf', 'key_word_chain_bag key_word_chains');

	// Make sure we use edismax, so we can use the 'qf'-parameter
	// and select a default operator
	$backendParams->add('qt', 'edismax');

	// Make sure we look for the individual terms also
	$backendParams->add('q.op', 'OR');

        return $backendParams;
    }
}
?>
