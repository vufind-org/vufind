<?php

namespace TueFind\View\Helper\Root;

use VuFind\View\Helper\Root\Piwik;
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

    /**
     * Construct the Piwik helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Piwik
     */
    public static function getPiwik(ServiceManager $sm)
    {
        $config = $sm->getServiceLocator()->get('VuFind\Config')->get('config');
        $url = isset($config->Piwik->url) ? $config->Piwik->url : false;
        $siteId = -1;
        if (isset($config->Piwik->site_ids)) {
            $siteIds = array_reduce(explode(",", $config->Piwik->site_ids), function ($array, $value) {
                $values = array_map("trim", explode(":", $value));
                $array[$values[0]] = $values[1];
                return $array;
            }, []);
            $siteId = array_key_exists($_SERVER['HTTP_HOST'], $siteIds) ? $siteIds[$_SERVER['HTTP_HOST']] : $siteId;
        } else {
            $siteId = $config->Piwik->site_id ?: $siteId;
        }
        if ($siteId == -1) {
            return new Piwik("", null, false, null, null);
        }
        $customVars = isset($config->Piwik->custom_variables)
            ? $config->Piwik->custom_variables
            : false;
        $request = $sm->getServiceLocator()->get('Request');
        $router = $sm->getServiceLocator()->get('Router');
        return new Piwik($url, $siteId, $customVars, $router, $request);
    }
}
