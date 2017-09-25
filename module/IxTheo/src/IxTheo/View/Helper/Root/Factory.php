<?php
namespace IxTheo\View\Helper\Root;
use VuFind\View\Helper\Root\Piwik;
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
}
