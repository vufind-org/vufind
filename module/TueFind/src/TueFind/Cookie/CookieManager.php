<?php

namespace TueFind\Cookie;

class CookieManager extends \VuFind\Cookie\CookieManager implements \TueFind\ServiceManager\ConfigAwareInterface {

    use \TueFind\ServiceManager\ConfigAwareTrait;

    /**
     * This is only for additional cookies like "ui" and "language".
     * For VuFind session cookie, see Session/ManagerFactory.
     *
     * @see parent
     */
    public function setGlobalCookie($key, $value, $expire, $httpOnly = null)
    {
        // Make cookies persistent.
        // Normal session lifetime only refers to session, but not to cookie.
        // So this way we will keep the session cookie even after browser is closed.
        if ($expire == 0) {
            $expirationOverride = $this->getLifetimeOverride();
            if ($expirationOverride != false) {
                $expire = time() + $expirationOverride;
            }
        }

        return parent::setGlobalCookie($key, $value, $expire, $httpOnly);
    }

    /**
     * If persistent cookies are enabled in config, return Session->lifetime, else false.
     *
     * @return mixed
     */
    public function getLifetimeOverride() {
        $persistentCookies = $this->getConfig('tuefind')->General->persistent_cookies ?? false;
        if (!$persistentCookies) {
            return false;
        }

        $lifetime = $this->getConfig('config')->Session->lifetime ?? false;
        if (!$lifetime) {
            return false;
        }

        return intval($lifetime);
    }
}
