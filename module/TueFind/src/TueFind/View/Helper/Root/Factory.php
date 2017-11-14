<?php

namespace TueFind\View\Helper\Root;

use Zend\ServiceManager\ServiceManager;

class Factory
{
    /**
     * Construct the HelpText helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return HelpText
     */
    public static function getHelpText(ServiceManager $sm)
    {
        $lang = $sm->getServiceLocator()->has('VuFind\Translator')
            ? $sm->getServiceLocator()->get('VuFind\Translator')->getLocale()
            : 'en';
        return new HelpText($sm->get('context'), $lang);
    }
}
