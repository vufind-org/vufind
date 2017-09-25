<?php

namespace IxTheo\Search\Options;
use Zend\ServiceManager\ServiceManager;

class Factory extends \VuFind\Search\Options\Factory
{
    /**
     * Factory for KeywordChainSearch results object.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Solr
     */
    public static function getKeywordChainSearch(ServiceManager $sm)
    {
        $config = $sm->getServiceLocator()->get('VuFind\Config');
        return new \IxTheo\Search\KeywordChainSearch\Options($config);
    }

    /**
     * Factory for PDASubscriptions results object.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Solr
     */
    public static function getPDASubscriptions(ServiceManager $sm)
    {
        $config = $sm->getServiceLocator()->get('VuFind\Config');
        return new \IxTheo\Search\PDASubscriptions\Options($config);
    }

    /**
     * Factory for Subscriptions results object.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Solr
     */
    public static function getSubscriptions(ServiceManager $sm)
    {
        $config = $sm->getServiceLocator()->get('VuFind\Config');
        return new \IxTheo\Search\Subscriptions\Options($config);
    }
}
