<?php

namespace IxTheo\RecordDriver;

class PluginManager extends \VuFind\RecordDriver\PluginManager {
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
        $this->aliases['solrdefault'] = 'IxTheo\RecordDriver\SolrDefault';
        $this->aliases['solrmarc'] = 'IxTheo\RecordDriver\SolrMarc';

        $this->delegators['IxTheo\RecordDriver\SolrMarc'] = ['VuFind\RecordDriver\IlsAwareDelegatorFactory'];

        $this->factories['IxTheo\RecordDriver\SolrDefault'] = 'TueFind\RecordDriver\SolrDefaultFactory';
        $this->factories['IxTheo\RecordDriver\SolrMarc'] = 'TueFind\RecordDriver\SolrMarcFactory';

        parent::__construct($configOrContainerInstance, $v3config);
    }
}
