<?php

namespace TueFind\Search\Results;

class PluginManager extends \VuFind\Search\Results\PluginManager {
    public function __construct($configOrContainerInstance = null,
        array $v3config = []
    ) {
        $this->aliases['solr'] = 'TueFind\Search\Solr\Results';
        $this->factories['TueFind\Search\Solr\Results'] = 'VuFind\Search\Solr\ResultsFactory';
        parent::__construct($configOrContainerInstance, $v3config);
    }
}
