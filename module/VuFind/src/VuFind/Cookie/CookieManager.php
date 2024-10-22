<?php

/**
 * Cookie Manager
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2015.
 * Copyright (C) The National Library of Finland 2020.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Cookie
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\Cookie;

use function is_array;

/**
 * Cookie Manager
 *
 * @category VuFind
 * @package  Cookie
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class CookieManager
{
    /**
     * Cookie array to work with
     *
     * @var array
     */
    protected $cookies;

    /**
     * Cookie base path
     *
     * @var string
     */
    protected $path;

    /**
     * Cookie domain
     *
     * @var string
     */
    protected $domain;

    /**
     * Are cookies secure only?
     *
     * @var bool
     */
    protected $secure;

    /**
     * Are cookies HTTP only?
     *
     * @var bool
     */
    protected $httpOnly;

    /**
     * The name of the session cookie
     *
     * @var ?string
     */
    protected $sessionName;

    /**
     * Default SameSite attribute
     *
     * @var string
     */
    protected $sameSite;

    /**
     * Constructor
     *
     * @param array   $cookies     Cookie array to manipulate (e.g. $_COOKIE)
     * @param string  $path        Cookie base path (default = /)
     * @param string  $domain      Cookie domain
     * @param bool    $secure      Are cookies secure only? (default = false)
     * @param ?string $sessionName Session cookie name (if null defaults to PHP
     * settings)
     * @param bool    $httpOnly    Are cookies HTTP only? (default = true)
     * @param string  $sameSite    Default SameSite attribute (default = 'Lax')
     */
    public function __construct(
        $cookies,
        $path = '/',
        $domain = null,
        $secure = false,
        $sessionName = null,
        $httpOnly = true,
        $sameSite = 'Lax'
    ) {
        $this->cookies = $cookies;
        $this->path = $path;
        $this->domain = $domain;
        $this->secure = $secure;
        $this->httpOnly = $httpOnly;
        $this->sessionName = $sessionName;
        $this->sameSite = $sameSite;
    }

    /**
     * Get all cookie values.
     *
     * @return array
     */
    public function getCookies()
    {
        return $this->cookies;
    }

    /**
     * Get the cookie domain.
     *
     * @return string
     */
    public function getDomain()
    {
        return $this->domain;
    }

    /**
     * Get the cookie path.
     *
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * Are cookies set to "secure only" mode?
     *
     * @return bool
     */
    public function isSecure()
    {
        return $this->secure;
    }

    /**
     * Are cookies set to "HTTP only" mode?
     *
     * @return bool
     */
    public function isHttpOnly()
    {
        return $this->httpOnly;
    }

    /**
     * Get the name of the cookie
     *
     * @return ?string
     */
    public function getSessionName()
    {
        return $this->sessionName;
    }

    /**
     * Get the cookie SameSite attribute.
     *
     * @return string
     */
    public function getSameSite()
    {
        return $this->sameSite;
    }

    /**
     * Support method for setGlobalCookie -- proxy PHP's setcookie() function
     * for compatibility with unit testing.
     *
     * @param string $key      Name of cookie to set
     * @param mixed  $value    Value to set
     * @param int    $expire   Cookie expiration time
     * @param string $path     Path
     * @param string $domain   Domain
     * @param bool   $secure   Whether the cookie is secure only
     * @param bool   $httpOnly Whether the cookie should be "HTTP only"
     * @param string $sameSite SameSite attribute to use (Lax, Strict or None)
     *
     * @return bool
     */
    public function proxySetCookie(
        $key,
        $value,
        $expire,
        $path,
        $domain,
        $secure,
        $httpOnly,
        $sameSite
    ) {
        // Special case: in CLI -- don't actually write headers!
        if ('cli' === PHP_SAPI) {
            return true;
        }
        return setcookie(
            $key,
            $value ?? '',
            [
                'expires' => $expire,
                'path' => $path,
                'domain' => $domain,
                'samesite' => $sameSite,
                'secure' => $secure,
                'httponly' => $httpOnly,
            ]
        );
    }

    /**
     * Support method for set() -- set the actual cookie in PHP.
     *
     * @param string    $key      Name of cookie to set
     * @param mixed     $value    Value to set
     * @param int       $expire   Cookie expiration time
     * @param null|bool $httpOnly Whether the cookie should be "HTTP only"
     * @param string    $sameSite SameSite attribute to use (Lax, Strict or None)
     *
     * @return bool
     */
    public function setGlobalCookie(
        $key,
        $value,
        $expire,
        $httpOnly = null,
        $sameSite = null
    ) {
        if (null === $httpOnly) {
            $httpOnly = $this->httpOnly;
        }
        if (null === $sameSite) {
            $sameSite = $this->sameSite;
        }
        // Simple case: flat value.
        if (!is_array($value)) {
            return $this->proxySetCookie(
                $key,
                $value,
                $expire,
                $this->path,
                $this->domain,
                $this->secure,
                $httpOnly,
                $sameSite
            );
        }

        // Complex case: array of values.
        $success = true;
        foreach ($value as $i => $curr) {
            $lastSuccess = $this->proxySetCookie(
                $key . '[' . $i . ']',
                $curr,
                $expire,
                $this->path,
                $this->domain,
                $this->secure,
                $httpOnly,
                $sameSite
            );
            if (!$lastSuccess) {
                $success = false;
            }
        }
        return $success;
    }

    /**
     * Set a cookie.
     *
     * @param string    $key      Name of cookie to set
     * @param mixed     $value    Value to set
     * @param int       $expire   Cookie expiration time
     * @param null|bool $httpOnly Whether the cookie should be "HTTP only"
     * @param string    $sameSite SameSite attribute to use (Lax, Strict or None)
     *
     * @return bool
     */
    public function set(
        $key,
        $value,
        $expire = 0,
        $httpOnly = null,
        $sameSite = null
    ) {
        $success = $this
            ->setGlobalCookie($key, $value, $expire, $httpOnly, $sameSite);
        if ($success) {
            $this->cookies[$key] = $value;
        }
        return $success;
    }

    /**
     * Clear a cookie.
     *
     * @param string $key Name of cookie to unset
     *
     * @return bool
     */
    public function clear($key)
    {
        $value = $this->get($key);
        if (is_array($value)) {
            $success = true;
            foreach (array_keys($value) as $i) {
                if (!$this->clear($key . '[' . $i . ']')) {
                    $success = false;
                }
            }
            return $success;
        }
        return $this->set($key, null, time() - 3600);
    }

    /**
     * Retrieve a cookie value (or null if unset).
     *
     * @param string $key Name of cookie to retrieve
     *
     * @return mixed
     */
    public function get($key)
    {
        return $this->cookies[$key] ?? null;
    }
}
