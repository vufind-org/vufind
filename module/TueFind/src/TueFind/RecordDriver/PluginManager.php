<?php

namespace TueFind\RecordDriver;

class PluginManager extends \VuFind\RecordDriver\PluginManager {

    use \TueFind\PluginManagerExtensionTrait;

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
        $this->addOverride('aliases', 'solrauth', 'TueFind\RecordDriver\SolrAuthMarc');
        $this->addOverride('aliases', 'solrauthdefault', 'TueFind\RecordDriver\SolrAuthMarc');
        $this->addOverride('aliases', 'solrauthmarc', 'TueFind\RecordDriver\SolrAuthMarc');
        $this->addOverride('aliases', 'solrdefault', 'TueFind\RecordDriver\SolrDefault');
        $this->addOverride('aliases', 'solrmarc', 'TueFind\RecordDriver\SolrMarc');

        $this->addOverride('delegators', 'TueFind\RecordDriver\SolrMarc', 'VuFind\RecordDriver\IlsAwareDelegatorFactory');

        $this->addOverride('factories', 'TueFind\RecordDriver\SolrAuth', 'VuFind\RecordDriver\SolrDefaultWithoutSearchServiceFactory');
        $this->addOverride('factories', 'TueFind\RecordDriver\SolrAuthDefault', 'VuFind\RecordDriver\SolrDefaultWithoutSearchServiceFactory');
        $this->addOverride('factories', 'TueFind\RecordDriver\SolrAuthMarc', 'VuFind\RecordDriver\SolrDefaultWithoutSearchServiceFactory');
        $this->addOverride('factories', 'TueFind\RecordDriver\SolrDefault', 'TueFind\RecordDriver\SolrDefaultFactory');
        $this->addOverride('factories', 'TueFind\RecordDriver\SolrMarc', 'TueFind\RecordDriver\SolrMarcFactory');

        $this->applyOverrides();

        parent::__construct($configOrContainerInstance, $v3config);
    }
}
