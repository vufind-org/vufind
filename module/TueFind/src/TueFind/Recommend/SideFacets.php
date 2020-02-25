<?php
namespace TueFind\Recommend;

use VuFind\Search\Solr\HierarchicalFacetHelper;
use VuFind\Solr\Utils as SolrUtils;

class SideFacets extends \VuFind\Recommend\SideFacets
{
    /**
     * Facet where facet counts are not displayed
     */
    protected $suppressCountFacets = [];

    public function __construct(
        \VuFind\Config\PluginManager $configLoader,
        HierarchicalFacetHelper $facetHelper = null
    ) {
        parent::__construct($configLoader, $facetHelper);
    }

    public function setConfig($settings) {
        parent::setConfig($settings);

        // Parse the additional settings:
        $settings = explode(':', $settings);
        $mainSection = empty($settings[0]) ? 'Results' : $settings[0];
        $checkboxSection = $settings[1] ?? false;
        $iniName = $settings[2] ?? 'facets';

        // Load the desired facet information...
        $config = $this->configLoader->get($iniName);
        if (isset($config->Results_Settings->suppress_count))
            $this->suppressCountFacets = explode(',', $config->Results_Settings->suppress_count);
    }

    public function getSuppressCountFacets() {
        return $this->suppressCountFacets;
    }

}
