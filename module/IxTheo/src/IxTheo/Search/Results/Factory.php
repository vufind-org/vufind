<?php

namespace IxTheo\Search\Results;
use Zend\ServiceManager\ServiceManager;

class Factory extends \VuFind\Search\Results\Factory
{
    /**
     * Factory for KeywordChainSearch results object.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Subscriptions
     */
    public static function getKeywordChainSearch(ServiceManager $sm)
    {
        $factory = new PluginFactory();
        $obj = $factory->createServiceWithName($sm, 'keywordchainsearch', 'KeywordChainSearch');
        $init = new \ZfcRbac\Initializer\AuthorizationServiceInitializer();
        $init->initialize($obj, $sm);
        return $obj;
    }

    /**
     * Factory for Subscriptions results object.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Subscriptions
     */
    public static function getSubscriptions(ServiceManager $sm)
    {
        $factory = new PluginFactory();
        $obj = $factory->createServiceWithName($sm, 'subscriptions', 'Subscriptions');
        $init = new \ZfcRbac\Initializer\AuthorizationServiceInitializer();
        $init->initialize($obj, $sm);
        return $obj;
    }

    /**
     * Factory for PDA-Subscriptions results object.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Subscriptions
     */
    public static function getPDASubscriptions(ServiceManager $sm)
    {
        $factory = new PluginFactory();
        $obj = $factory->createServiceWithName($sm, 'pdasubscriptions', 'PDASubscriptions');
        $init = new \ZfcRbac\Initializer\AuthorizationServiceInitializer();
        $init->initialize($obj, $sm);
        return $obj;
    }
}
