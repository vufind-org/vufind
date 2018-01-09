<?php

namespace IxTheo\Search\Results;
use Zend\ServiceManager\ServiceManager;

class Factory extends \TueFind\Search\Results\Factory
{
    /**
     * Factory for KeywordChainSearch results object.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \IxTheo\Search\KeywordChainSearch\Results
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
     * Factory for Solr results object.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \VuFind\Search\Solr\Results
     */
    public static function getSolr(ServiceManager $sm)
    {
        $factory = new PluginFactory();
        $solr = $factory->createServiceWithName($sm, 'solr', 'Solr');
        $config = $sm->getServiceLocator()
            ->get('VuFind\Config')->get('config');
        $spellConfig = isset($config->Spelling)
            ? $config->Spelling : null;
        $solr->setSpellingProcessor(
            new \VuFind\Search\Solr\SpellingProcessor($spellConfig)
        );
        return $solr;
    }

    /**
     * Factory for Subscriptions results object.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \IxTheo\Search\Subscriptions\Results
     */
    public static function getSubscriptions(ServiceManager $sm)
    {
        $factory = new PluginFactory();
        $tm = $sm->getServiceLocator()->get('VuFind\DbTablePluginManager');
        $obj = $factory->createServiceWithName($sm, 'subscriptions', 'Subscriptions', [$tm->get('subscription')]);
        $init = new \ZfcRbac\Initializer\AuthorizationServiceInitializer();
        $init->initialize($obj, $sm);
        return $obj;
    }

    /**
     * Factory for PDA-Subscriptions results object.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \IxTheo\Search\PDASubscriptions\Results
     */
    public static function getPDASubscriptions(ServiceManager $sm)
    {
        $factory = new PluginFactory();
        $tm = $sm->getServiceLocator()->get('VuFind\DbTablePluginManager');
        $obj = $factory->createServiceWithName($sm, 'pdasubscriptions', 'PDASubscriptions', [$tm->get('pdasubscription')]);
        $init = new \ZfcRbac\Initializer\AuthorizationServiceInitializer();
        $init->initialize($obj, $sm);
        return $obj;
    }
}
