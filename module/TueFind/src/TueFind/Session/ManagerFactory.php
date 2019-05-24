<?php

namespace TueFind\Session;

use Interop\Container\ContainerInterface;

class ManagerFactory extends \VuFind\Session\ManagerFactory {
    /**
     * Build the options array.
     *
     * @param ContainerInterface $container Service manager
     *
     * @return array
     */
    protected function getOptions(ContainerInterface $container)
    {
        $options = parent::getOptions($container);

        // Make cookies persistent.
        // Normal session lifetime only refers to session, but not to cookie.
        // So this way we will keep the session cookie even after browser is closed.
        $tuefindConfig = $container->get('VuFind\Config\PluginManager')->get('tuefind');
        $persistentCookies = $tuefindConfig->General->persistent_cookies ?? false;
        if ($persistentCookies) {
            $vufindConfig = $container->get('VuFind\Config\PluginManager')->get('config');
            $lifetime = $vufindConfig->Session->lifetime ?? false;
            if ($lifetime != false) {
                $options['cookie_lifetime'] = $lifetime;
            }
        }

        return $options;
    }
}
