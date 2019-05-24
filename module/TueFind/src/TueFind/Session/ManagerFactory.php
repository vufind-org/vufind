<?php

namespace TueFind\Session;

use Interop\Container\ContainerInterface;

class ManagerFactory extends \VuFind\Session\ManagerFactory {

    /**
     * This is only for VuFind session cookie.
     * For all other cookies (like ui and language), see Cookie/CookieManager.
     *
     * @see parent
     */
    protected function getOptions(ContainerInterface $container)
    {
        $options = parent::getOptions($container);

        // Make cookies persistent.
        // Normal session lifetime only refers to session, but not to cookie.
        // So this way we will keep the session cookie even after browser is closed.
        $configManager = $container->get('VuFind\Config\PluginManager');
        $persistentCookies = $configManager->get('tuefind')->General->persistent_cookies ?? false;
        if ($persistentCookies) {
            $lifetime = $configManager->get('config')->Session->lifetime ?? false;
            if ($lifetime != false) {
                $options['cookie_lifetime'] = $lifetime;
            }
        }

        return $options;
    }
}
