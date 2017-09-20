<?php

namespace IxTheo\Controller;

use Zend\ServiceManager\ServiceManager;

class Factory extends \VuFind\Controller\Factory
{
    public static function getAlphabrowseController(ServiceManager $sm) {
        return new AlphabrowseController($sm->getServiceLocator());
    }

    public static function getBrowseController(ServiceManager $sm)
    {
        return new BrowseController(
            $sm->getServiceLocator()->get('VuFind\Config')->get('config')
        );
    }

    public static function getFeedbackController(ServiceManager $sm) {
        return new FeedbackController($sm->getServiceLocator());
    }

    public static function getKeywordChainSearchController(ServiceManager $sm) {
        return new Search\KeywordChainSearchController($sm->getServiceLocator());
    }

    public static function getMyResearchController(ServiceManager $sm) {
        return new MyResearchController($sm->getServiceLocator());
    }

    public static function getPipelineController(ServiceManager $sm) {
        return new PipelineController($sm->getServiceLocator());
    }

    public static function getRecordController(ServiceManager $sm)
    {
        return new RecordController(
            $sm->getServiceLocator()->get('VuFind\Config')->get('config')
        );
    }

    public static function getSearchController(ServiceManager $sm) {
        return new SearchController($sm->getServiceLocator());
    }

    public static function getStaticPageController(ServiceManager $sm) {
        return new StaticPageController($sm->getServiceLocator());
    }
}