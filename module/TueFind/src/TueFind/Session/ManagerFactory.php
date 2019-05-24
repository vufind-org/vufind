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
        $cookieManager = $container->get('VuFind\Cookie\CookieManager');
        $lifetimeOverride = $cookieManager->getLifetimeOverride();
        if ($lifetimeOverride != false) {
            $options['cookie_lifetime'] = $lifetimeOverride;
        }

        return $options;
    }
}
