<?php

namespace IxTheo\Search\Params;
use Zend\ServiceManager\ServiceManager;

class Factory
{
    /**
     * Factory for Solr params object.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \IxTheo\Search\Solr\Params
     */
    public static function getSolr(ServiceManager $sm)
    {
        $factory = new PluginFactory();
        $helper = $sm->getServiceLocator()->get('VuFind\HierarchicalFacetHelper');
        return $factory->createServiceWithName($sm, 'solr', 'Solr', [$helper]);
    }
}
