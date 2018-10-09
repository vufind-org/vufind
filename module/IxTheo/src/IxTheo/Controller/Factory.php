<?php

namespace IxTheo\Controller;

use Zend\ServiceManager\ServiceManager;

class Factory extends \TueFind\Controller\Factory
{
    public static function getBrowseController(ServiceManager $sm)
    {
        return new BrowseController(
            $sm->getServiceLocator(),
            $sm->getServiceLocator()->get('VuFind\Config')->get('config')
        );
    }

    /**
     * This function is needed because KeywordChainSearchController
     * is in sub-namespace "Search\"
     *
     * @param ServiceManager $sm
     * @return \IxTheo\Controller\Search\KeywordChainSearchController
     */
    public static function getKeywordChainSearchController(ServiceManager $sm)
    {
        return new Search\KeywordChainSearchController(
            $sm->getServiceLocator()
        );
    }

    public static function getRecordController(ServiceManager $sm)
    {
        return new RecordController(
            $sm->getServiceLocator(),
            $sm->getServiceLocator()->get('VuFind\Config')->get('config')
        );
    }

    public static function getClassificationController(ServiceManager $sm)
    {
       return new ClassificationController(
            $sm->getServiceLocator()
       );
    }
}
