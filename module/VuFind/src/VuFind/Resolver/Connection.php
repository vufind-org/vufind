<?php

/**
 * Link Resolver Driver Wrapper
 *
 * PHP version 8
 *
 * Copyright (C) Royal Holloway, University of London
 *
 * last update: 2010-10-11
 * tested with X-Server SFX 3.2
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
 * @package  Resolver_Drivers
 * @author   Graham Seaman <Graham.Seaman@rhul.ac.uk>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:link_resolver_drivers Wiki
 */

namespace VuFind\Resolver;

use function call_user_func_array;
use function is_callable;

/**
 * Resolver Connection Class
 *
 * This abstract class defines the signature for the available methods for
 * interacting with the local OpenURL Resolver. It is a cutdown version
 * of the CatalogConnection class.
 *
 * Required functions in implementing Drivers are listed in Interface.php
 *
 * @category VuFind
 * @package  Resolver_Drivers
 * @author   Graham Seaman <Graham.Seaman@rhul.ac.uk>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:link_resolver_drivers Wiki
 */
class Connection
{
    /**
     * The object of the appropriate driver.
     *
     * @var object
     */
    protected $driver = false;

    /**
     * The path to the resolver cache, if any (empty string for no caching)
     *
     * @var string
     */
    protected $cachePath = '';

    /**
     * Constructor
     *
     * This is responsible for instantiating the driver that has been specified.
     *
     * @param \VuFind\Resolver\Driver\DriverInterface $driver The driver to use
     */
    public function __construct(\VuFind\Resolver\Driver\DriverInterface $driver)
    {
        $this->driver = $driver;
    }

    /**
     * Disable caching.
     *
     * @return void
     */
    public function disableCache()
    {
        $this->cachePath = '';
    }

    /**
     * Enable caching.
     *
     * @param string $cacheDir Directory to use for cache.
     *
     * @return void
     */
    public function enableCache($cacheDir)
    {
        if (is_dir($cacheDir) && is_writable($cacheDir)) {
            $this->cachePath = $cacheDir;
            if (!str_ends_with($this->cachePath, '/')) {
                $this->cachePath .= '/';
            }
        }
    }

    /**
     * Fetch Links
     *
     * This is responsible for retrieving the valid links for a
     * particular OpenURL. The links may be cached or fetched remotely.
     *
     * If an error occurs, throw exception
     *
     * @param string $openURL The OpenURL to use
     *
     * @return array          An associative array with the following keys:
     * linktype, aval, href, coverage
     */
    public function fetchLinks($openURL)
    {
        if (!empty($this->cachePath)) {
            $hashedURL = md5($openURL);
            if (file_exists($this->cachePath . $hashedURL)) {
                $links = file_get_contents($this->cachePath . $hashedURL);
            } else {
                $links = $this->driver->fetchLinks($openURL);
                file_put_contents($this->cachePath . $hashedURL, $links);
            }
        } else {
            $links = $this->driver->fetchLinks($openURL);
        }
        return $this->driver->parseLinks($links);
    }

    /**
     * Default method -- pass along calls to the driver if available; return
     * false otherwise. This allows custom functions to be implemented in
     * the driver without constant modification to the connection class.
     *
     * @param string $methodName The name of the called method.
     * @param array  $params     Array of passed parameters.
     *
     * @return mixed             Varies by method (false if undefined method)
     */
    public function __call($methodName, $params)
    {
        $method = [$this->driver, $methodName];
        if (is_callable($method)) {
            return call_user_func_array($method, $params);
        }
        return false;
    }
}
