<?php

namespace IxTheo\Autocomplete;
use Zend\ServiceManager\ServiceManager;

class SolrFactory extends \VuFind\Autocomplete\SolrFactory
{
    /**
     * Construct the Solr plugin.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Solr
     */
    public static function getSolr(ServiceManager $sm)
    {
        return new Solr(
            $sm->getServiceLocator()->get('VuFind\SearchResultsPluginManager')
        );
    }
}
