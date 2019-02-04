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
            $siteId = array_key_exists($_SERVER['HTTP_HOST'], $siteIds) ? $siteIds[$_SERVER['HTTP_HOST']] : $siteId;
        } else {
            $siteId = $config->Piwik->site_id ?: $siteId;
        }
        if ($siteId == -1) {
            return new $requestedName("", null, false, null, null);
        }
        $customVars = isset($config->Piwik->custom_variables)
            ? $config->Piwik->custom_variables
            : false;

        $settings = [
            'siteId' => $siteId,
            'searchPrefix' => $config->Piwik->searchPrefix ?? null
        ];
        $request = $container->get('Request');
        $router = $container->get('Router');

        return new $requestedName($url, $settings, $customVars, $router, $request);
    }
}
