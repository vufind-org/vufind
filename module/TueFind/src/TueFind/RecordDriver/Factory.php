<?php

namespace TueFind\RecordDriver;

class Factory extends \VuFind\RecordDriver\Factory
{
    /**
     * Factory for SolrAuth record driver.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return SolrAuth
     */
    public static function getSolrAuth(\Zend\ServiceManager\ServiceManager $sm)
    {
        return new SolrAuth(
            $sm->getServiceLocator()->get('VuFind\Config')->get('config'),
            null,
            $sm->getServiceLocator()->get('VuFind\Config')->get('searches')
        );
    }

    /**
     * Factory for SolrDefault record driver.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return SolrDefault
     */
    public static function getSolrDefault(\Zend\ServiceManager\ServiceManager $sm)
    {
        $driver = new SolrDefault(
            $sm->getServiceLocator()->get('VuFind\Config')->get('config'),
            null,
            $sm->getServiceLocator()->get('VuFind\Config')->get('searches')
        );
        $driver->attachSearchService($sm->getServiceLocator()->get('VuFind\Search'));
        return $driver;
    }

    /**
     * Factory for SolrMarc record driver.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return SolrMarc
     */
    public static function getSolrMarc(\Zend\ServiceManager\ServiceManager $sm)
    {
        $driver = new SolrMarc(
            $sm->getServiceLocator()->get('VuFind\Config')->get('config'),
            null,
            $sm->getServiceLocator()->get('VuFind\Config')->get('searches')
        );
        $driver->attachILS(
            $sm->getServiceLocator()->get('VuFind\ILSConnection'),
            $sm->getServiceLocator()->get('VuFind\ILSHoldLogic'),
            $sm->getServiceLocator()->get('VuFind\ILSTitleHoldLogic')
        );
        $driver->attachSearchService($sm->getServiceLocator()->get('VuFind\Search'));
        return $driver;
    }


}

