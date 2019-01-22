<?php

namespace KrimDok\Search\Params;

class PluginManager extends \VuFind\Search\Params\PluginManager {
    public function __construct($configOrContainerInstance = null,
        array $v3config = []
    ) {
        $this->aliases['solr'] = 'KrimDok\Search\Solr\Params';
        $this->factories['KrimDok\Search\Solr\Params'] = 'VuFind\Search\Solr\ParamsFactory';
        parent::__construct($configOrContainerInstance, $v3config);
    }
}
