<?php

namespace TueFind\View\Helper\Root;

use Interop\Container\ContainerInterface;

class PiwikFactory extends \VuFind\View\Helper\Root\PiwikFactory
{
    public function __invoke(ContainerInterface $container, $requestedName,
        array $options = null)
    {
        if (!empty($options)) {
            throw new \Exception('Unexpected options sent to factory.');
        }
        $config = $container->get('VuFind\Config\PluginManager')->get('config');
        $url = isset($config->Piwik->url) ? $config->Piwik->url : false;
        $siteId = -1;
        if (isset($config->Piwik->site_ids)) {
            $siteIds = array_reduce(explode(",", $config->Piwik->site_ids), function ($array, $value) {
                $values = array_map("trim", explode(":", $value));
                $array[$values[0]] = $values[1];
                return $array;
            }, []);
            $http_host_without_port = preg_replace('":[^:]+$"', '', $_SERVER['HTTP_HOST']);
            $siteId = array_key_exists($http_host_without_port, $siteIds) ? $siteIds[$http_host_without_port] : $siteId;
        } else {
            $siteId = $config->Piwik->site_id ?: $siteId;
        }
        if ($siteId == -1) {
            return new $requestedName("", null, false, null, null);
        }

        $settings = [
            'siteId' => $siteId,
            'searchPrefix' => $config->Piwik->searchPrefix ?? null,
            'disableCookies' => $config->Piwik->disableCookies ?? false
        ];
        $customVars = $config->Piwik->custom_variables ?? false;
        $request = $container->get('Request');
        $router = $container->get('Router');
        return new $requestedName($url, $settings, $customVars, $router, $request);
    }
}
