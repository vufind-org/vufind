<?php

namespace IxTheo\Search\Options;
use Zend\ServiceManager\ServiceManager;

class Factory extends \VuFind\Search\Options\Factory
{
    /**
     * Factory for PDASubscriptions results object.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \IxTheo\Search\PDASubscriptions\Options
     */
    public static function getPDASubscriptions(ServiceManager $sm)
    {
        $config = $sm->getServiceLocator()->get('VuFind\Config');
        return new \IxTheo\Search\PDASubscriptions\Options($config);
    }

    /**
     * Factory for Subscriptions options object.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \IxTheo\Search\Subscriptions\Options
     */
    public static function getSubscriptions(ServiceManager $sm)
    {
        $config = $sm->getServiceLocator()->get('VuFind\Config');
        return new \IxTheo\Search\Subscriptions\Options($config);
    }
}
