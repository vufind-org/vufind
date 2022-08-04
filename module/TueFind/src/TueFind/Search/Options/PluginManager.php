<?php
  
namespace TueFind\Search\Options;

use VuFind\Search\Options\OptionsFactory; 

class PluginManager extends \VuFind\Search\Options\PluginManager {
    public function __construct($configOrContainerInstance = null,
        array $v3config = []
    ) {
        $this->aliases['search2'] = \TueFind\Search\Search2\Options::class;
        $this->aliases['search3'] = \TueFind\Search\Search3\Options::class;
        $this->aliases['solrauthorfacets'] = \TueFind\Search\SolrAuthorFacets\Options::class;
        $this->factories['solrauthorfacets'] = \VuFind\Search\Options\OptionsFactory::class;
        parent::__construct($configOrContainerInstance, $v3config);
    }
}
