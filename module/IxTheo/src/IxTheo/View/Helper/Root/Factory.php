<?php

namespace IxTheo\View\Helper\Root;

use Zend\ServiceManager\ServiceManager;

class Factory
{
    /**
     * Construct the Citation helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Citation
     */
    public static function getCitation(ServiceManager $sm)
    {
        return new Citation($sm->getServiceLocator()->get('VuFind\DateConverter'));
    }

    /**
     * Construct the Record helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Record
     */
    public static function getRecord(ServiceManager $sm)
    {
        $helper = new Record(
            $sm->getServiceLocator()->get('VuFind\Config')->get('config')
        );
        $helper->setCoverRouter(
            $sm->getServiceLocator()->get('VuFind\Cover\Router')
        );
        return $helper;
    }

    /**
     * Construct the IxTheo helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return IxTheo
     */

    public static function getIxTheo(ServiceManager $sm) {
        return new IxTheo($sm);
   }
}
