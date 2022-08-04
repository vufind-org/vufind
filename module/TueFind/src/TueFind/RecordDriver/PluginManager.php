<?php

namespace TueFind\RecordDriver;

use VuFind\RecordDriver\IlsAwareDelegatorFactory;
use VuFind\RecordDriver\SolrDefaultWithoutSearchServiceFactory;

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
        $this->addOverride('aliases', 'solrauth', SolrAuthMarc::class);
        $this->addOverride('aliases', 'solrauthdefault', SolrAuthMarc::class);
        $this->addOverride('aliases', 'solrauthmarc', SolrAuthMarc::class);
        $this->addOverride('aliases', 'solrdefault', SolrDefault::class);
        $this->addOverride('aliases', 'solrmarc', SolrMarc::class);
        $this->addOverride('aliases', 'search3default', Search3Default::class);

        $this->addOverride('delegators', SolrMarc::class, IlsAwareDelegatorFactory::class);

        $this->addOverride('factories', SolrAuthDefault::class, SolrDefaultWithoutSearchServiceFactory::class);
        $this->addOverride('factories', SolrAuthMarc::class, SolrDefaultWithoutSearchServiceFactory::class);
        $this->addOverride('factories', SolrDefault::class, SolrDefaultFactory::class);
        $this->addOverride('factories', SolrMarc::class, SolrMarcFactory::class);

        $this->applyOverrides();

        parent::__construct($configOrContainerInstance, $v3config);
    }

    public function getSearch3Record($data, $defaultKeySuffix = 'Default')
    {
        return $this->getSolrRecord($data, 'Search3', $defaultKeySuffix);
    }


}
