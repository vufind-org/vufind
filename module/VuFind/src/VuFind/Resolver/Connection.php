<?php
/**
 * Link Resolver Driver Wrapper
 *
 * PHP version 5
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Resolver_Drivers
 * @author   Graham Seaman <Graham.Seaman@rhul.ac.uk>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_a_link_resolver_driver Wiki
 */
namespace VuFind\Resolver;
use VuFind\Config\Reader as ConfigReader;

/**
 * Resolver Connection Class
 *
 * This abstract class defines the signature for the available methods for
 * interacting with the local OpenURL Resolver. It is a cutdown version
 * of the CatalogConnection class.
 *
 * Required functions in implementing Drivers are listed in Interface.php
 *
 * @category VuFind2
 * @package  Resolver_Drivers
 * @author   Graham Seaman <Graham.Seaman@rhul.ac.uk>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_a_link_resolver_driver Wiki
 */
class Connection
{
    /**
     * A boolean value that defines whether a connection has been successfully
     * made.
     *
     * @var bool
     */
    public $status = false;

    /**
     * The object of the appropriate driver.
     *
     * @var object
     */
    protected $driver = false;

    /**
     * A boolean value that defines whether to cache the resolver results
     *
     * @var bool
     */
    protected $useCache = false;

    /**
     * The path to the resolver cache, if any
     *
     * @var string
     */
    protected $cachePath;

    /**
     * Constructor
     *
     * This is responsible for instantiating the driver that has been specified.
     *
     * @param string $driver The name of the driver to load.
     */
    public function __construct($driver)
    {
        // Backward compatibility -- can't have class beginning with number:
        if (strtolower($driver) == '360link') {
            $driver = 'threesixtylink';
        }
        $class = 'VuFind\\Resolver\\Driver\\' . ucwords($driver);
        if (class_exists($class)) {
            $this->driver = new $class();
            $this->status = true;
            $config = ConfigReader::getConfig();
            if (isset($config->OpenURL->resolver_cache)
                && is_dir($config->OpenURL->resolver_cache)
                && is_writable($config->OpenURL->resolver_cache)
            ) {
                $this->useCache = true;
                $this->cachePath = $config->OpenURL->resolver_cache;
                if (!(substr($this->cachePath, -1) == '/')) {
                    $this->cachePath .= '/';
                }
            }
        }
    }

    /**
     * Check if driver loaded successfully.
     *
     * @return bool
     */
    public function driverLoaded()
    {
        return is_object($this->driver);
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
        $cache_found = false;
        if ($this->useCache) {
            $hashedURL = md5($openURL);
            if (file_exists($this->cachePath . $hashedURL)) {
                $links = file_get_contents($this->cachePath . $hashedURL);
            } else {
                $links = $this->driver->fetchLinks($openURL);
                $fp = fopen($this->cachePath . $hashedURL, 'w');
                fwrite($fp, $links);
                fclose($fp);
            }
        } else {
            $links = $this->driver->fetchLinks($openURL);
        }
        return $this->driver->parseLinks($links);
    }

    /**
     * Default method -- pass along calls to the driver if available; return
     * false otherwise.  This allows custom functions to be implemented in
     * the driver without constant modification to the connection class.
     *
     * @param string $methodName The name of the called method.
     * @param array  $params     Array of passed parameters.
     *
     * @return mixed             Varies by method (false if undefined method)
     */
    public function __call($methodName, $params)
    {
        $method = array($this->driver, $methodName);
        if (is_callable($method)) {
            return call_user_func_array($method, $params);
        }
        return false;
    }
}
