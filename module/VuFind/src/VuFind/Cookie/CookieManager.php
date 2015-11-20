<?php
/**
 * Cookie Manager
 *
 * PHP version 5
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Cookie
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace VuFind\Cookie;

/**
 * Cookie Manager
 *
 * @category VuFind2
 * @package  Cookie
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
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
     * Constructor
     *
     * @param array  $cookies Cookie array to manipulate (e.g. $_COOKIE)
     * @param string $path    Cookie base path (default = /)
     * @param string $domain  Cookie domain
     * @param bool   $secure  Are cookies secure only? (default = false)
     */
    public function __construct($cookies, $path = '/', $domain = null,
        $secure = false
    ) {
        $this->cookies = $cookies;
        $this->path = $path;
        $this->domain = $domain;
        $this->secure = $secure;
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
     * Support method for setGlobalCookie -- proxy PHP's setcookie() function
     * for compatibility with unit testing.
     *
     * @return bool
     */
    public function proxySetCookie()
    {
        // Special case: in test suite -- don't actually write headers!
        return defined('VUFIND_PHPUNIT_RUNNING')
            ? true : call_user_func_array('setcookie', func_get_args());
    }

    /**
     * Support method for set() -- set the actual cookie in PHP.
     *
     * @param string $key    Name of cookie to set
     * @param mixed  $value  Value to set
     * @param int    $expire Cookie expiration time
     *
     * @return bool
     */
    public function setGlobalCookie($key, $value, $expire)
    {
        // Simple case: flat value.
        if (!is_array($value)) {
            return $this->proxySetCookie(
                $key, $value, $expire, $this->path, $this->domain, $this->secure
            );
        }

        // Complex case: array of values.
        $success = true;
        foreach ($value as $i => $curr) {
            $lastSuccess = $this->proxySetCookie(
                $key . '[' . $i . ']', $curr, $expire,
                $this->path, $this->domain, $this->secure
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
     * @param string $key    Name of cookie to set
     * @param mixed  $value  Value to set
     * @param int    $expire Cookie expiration time
     *
     * @return bool
     */
    public function set($key, $value, $expire = 0)
    {
        if ($success = $this->setGlobalCookie($key, $value, $expire)) {
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