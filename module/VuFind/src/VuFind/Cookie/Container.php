<?php

/**
 * Class for treating a set of cookies as an object (inspired by
 * \Laminas\Session\Container).
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2012.
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

use function strlen;

/**
 * Class for treating a set of cookies as an object (inspired by
 * \Laminas\Session\Container).
 *
 * @category VuFind
 * @package  Cookie
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Container
{
    /**
     * Prefix to use for cookie values.
     *
     * @var string
     */
    protected $groupName;

    /**
     * Cookie manager.
     *
     * @var CookieManager
     */
    protected $manager;

    /**
     * Constructor
     *
     * @param string        $groupName Prefix to use for cookie values.
     * @param CookieManager $manager   Cookie manager.
     */
    public function __construct($groupName, CookieManager $manager = null)
    {
        $this->groupName = $groupName;
        $this->manager = $manager ?? new CookieManager($_COOKIE);
    }

    /**
     * Get all values in the container as an associative array.
     *
     * @return array
     */
    public function getAllValues()
    {
        $retVal = [];
        foreach ($this->manager->getCookies() as $key => $value) {
            if (str_starts_with($key, $this->groupName)) {
                $retVal[substr($key, strlen($this->groupName))] = $value;
            }
        }
        return $retVal;
    }

    /**
     * Get the value of a variable in this object.
     *
     * @param string $var programmatic name of a key, in a <key,value> pair in the
     * current container
     *
     * @return void
     */
    public function & __get($var)
    {
        $val = $this->manager->get($this->groupName . $var);
        return $val;
    }

    /**
     * Set a variable in this object.
     *
     * @param string $var   programmatic name of a key, in a <key,value> pair in the
     * current container
     * @param string $value new value for the key
     *
     * @return void
     */
    public function __set($var, $value)
    {
        $this->manager->set($this->groupName . $var, $value);
    }

    /**
     * Test the existence of a variable in this object.
     *
     * @param string $var programmatic name of a key, in a <key,value> pair in the
     * current container
     *
     * @return bool
     */
    public function __isset($var)
    {
        return null !== $this->manager->get($this->groupName . $var);
    }

    /**
     * Unset a variable in this object.
     *
     * @param string $var programmatic name of a key, in a <key,value> pair in the
     * current groupName
     *
     * @return void
     */
    public function __unset($var)
    {
        $this->manager->clear($this->groupName . $var);
    }
}
