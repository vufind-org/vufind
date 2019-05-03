<?php

namespace TueFind\RecordDriver;

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
        $this->aliases['solrauth'] = 'TueFind\RecordDriver\SolrAuthMarc';
        $this->aliases['solrauthdefault'] = 'TueFind\RecordDriver\SolrAuthMarc';
        $this->aliases['solrauthmarc'] = 'TueFind\RecordDriver\SolrAuthMarc';
        $this->aliases['solrdefault'] = 'TueFind\RecordDriver\SolrDefault';
        $this->aliases['solrmarc'] = 'TueFind\RecordDriver\SolrMarc';

        $this->delegators['TueFind\RecordDriver\SolrMarc'] = ['VuFind\RecordDriver\IlsAwareDelegatorFactory'];

        $this->factories['TueFind\RecordDriver\SolrAuth'] = 'VuFind\RecordDriver\SolrDefaultWithoutSearchServiceFactory';
        $this->factories['TueFind\RecordDriver\SolrAuthDefault'] = 'VuFind\RecordDriver\SolrDefaultWithoutSearchServiceFactory';
        $this->factories['TueFind\RecordDriver\SolrAuthMarc'] = 'VuFind\RecordDriver\SolrDefaultWithoutSearchServiceFactory';
        $this->factories['TueFind\RecordDriver\SolrDefault'] = 'TueFind\RecordDriver\SolrDefaultFactory';
        $this->factories['TueFind\RecordDriver\SolrMarc'] = 'TueFind\RecordDriver\SolrMarcFactory';

        parent::__construct($configOrContainerInstance, $v3config);
    }
}
