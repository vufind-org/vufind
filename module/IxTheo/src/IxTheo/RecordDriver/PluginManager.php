<?php

namespace IxTheo\RecordDriver;

use VuFind\RecordDriver\SolrDefaultWithoutSearchServiceFactory;

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
        $this->addOverride('aliases', 'solrauth', SolrAuthMarc::class);
        $this->addOverride('aliases', 'solrauthdefault', SolrAuthMarc::class);
        $this->addOverride('aliases', 'solrauthmarc', SolrAuthMarc::class);
        $this->addOverride('aliases', 'solrdefault', SolrDefault::class);
        $this->addOverride('aliases', 'solrmarc', SolrMarc::class);

        $this->addOverride('factories', SolrAuthDefault::class, SolrDefaultWithoutSearchServiceFactory::class);
        $this->addOverride('factories', SolrAuthMarc::class, SolrDefaultWithoutSearchServiceFactory::class);
        $this->addOverride('factories', SolrDefault::class, \TueFind\RecordDriver\SolrDefaultFactory::class);
        $this->addOverride('factories', SolrMarc::class, \TueFind\RecordDriver\SolrMarcFactory::class);

        parent::__construct($configOrContainerInstance, $v3config);
    }
}
