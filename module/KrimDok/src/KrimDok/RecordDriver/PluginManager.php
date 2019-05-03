<?php

namespace KrimDok\RecordDriver;

class PluginManager extends \TueFind\RecordDriver\PluginManager {
    /**
     * Constructor
     *
     * Make sure plugins are properly initialized.
     *
     * @param mixed $configOrContainerInstance Configuration or container instance
     * @param array $v3config                  If $configOrContainerInstance is a
     * container, this value will be passed to the parent constructor.
     */
    public function __construct($configOrContainerInstance = null,
        array $v3config = []
    ) {
        $this->addOverride('aliases', 'solrdefault', 'KrimDok\RecordDriver\SolrDefault');
        $this->addOverride('aliases', 'solrmarc', 'KrimDok\RecordDriver\SolrMarc');

        $this->addOverride('delegators', 'KrimDok\RecordDriver\SolrMarc', 'VuFind\RecordDriver\IlsAwareDelegatorFactory');

        $this->addOverride('factories', 'KrimDok\RecordDriver\SolrDefault', 'TueFind\RecordDriver\SolrDefaultFactory');
        $this->addOverride('factories', 'KrimDok\RecordDriver\SolrMarc', 'TueFind\RecordDriver\SolrMarcFactory');

        parent::__construct($configOrContainerInstance, $v3config);
    }
}
