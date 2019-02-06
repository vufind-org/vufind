<?php
/**
 * Cookie Manager
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2015.
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
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace VuFind\Cookie;

/**
 * Cookie Manager
 *
 * @category VuFind
 * @package  Cookie
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class CookieManager
{
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
     * @var string
     */
    protected $sessionName;

    /**
     * Constructor
     *
     * @param array  $cookies     Cookie array to manipulate (e.g. $_COOKIE)
     * @param string $path        Cookie base path (default = /)
     * @param string $domain      Cookie domain
     * @param bool   $secure      Are cookies secure only? (default = false)
     * @param string $sessionName Session cookie name (if null defaults to PHP
     * settings)
     * @param bool   $httpOnly    Are cookies HTTP only? (default = true)
     */
    public function __construct($cookies, $path = '/', $domain = null,
        $secure = false, $sessionName = null, $httpOnly = true
    ) {
        $this->cookies = $cookies;
        $this->path = $path;
        $this->domain = $domain;
        $this->secure = $secure;
        $this->httpOnly = $httpOnly;
        $this->sessionName = $sessionName;
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
     * @return mixed
     */
    public function getSessionName()
    {
        return $this->sessionName;
    }

    /**
     * Support method for setGlobalCookie -- proxy PHP's setcookie() function
     * for compatibility with unit testing.
     *
     * @return bool
     */
    public function proxySetCookie()
    {
        // Special case: in CLI -- don't actually write headers!
        return 'cli' === PHP_SAPI
            ? true : call_user_func_array('setcookie', func_get_args());
    }

    /**
     * Support method for set() -- set the actual cookie in PHP.
     *
     * @param string    $key      Name of cookie to set
     * @param mixed     $value    Value to set
     * @param int       $expire   Cookie expiration time
     * @param null|bool $httpOnly Whether the cookie should be "HTTP only"
     *
     * @return bool
     */
    public function setGlobalCookie($key, $value, $expire, $httpOnly = null)
    {
        if (null === $httpOnly) {
            $httpOnly = $this->httpOnly;
        }
        // Simple case: flat value.
        if (!is_array($value)) {
            return $this->proxySetCookie(
                $key, $value, $expire, $this->path, $this->domain, $this->secure,
                $httpOnly
            );
        }

        // Complex case: array of values.
        $success = true;
        foreach ($value as $i => $curr) {
            $lastSuccess = $this->proxySetCookie(
                $key . '[' . $i . ']', $curr, $expire,
                $this->path, $this->domain, $this->secure, $httpOnly
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
     *
     * @return bool
     */
    public function set($key, $value, $expire = 0, $httpOnly = null)
    {
        if ($success = $this->setGlobalCookie($key, $value, $expire, $httpOnly)) {
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
        return isset($this->cookies[$key]) ? $this->cookies[$key] : null;
    }
}
