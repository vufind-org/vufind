<?php

/**
 * Caching Proxy for Cover Images
 *
 * PHP version 8
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
 * @package  Cover_Generator
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/configuration:external_content Wiki
 */

namespace VuFind\Cover;

use Laminas\Http\Client;
use Laminas\Http\Response;

use function dirname;

/**
 * Caching Proxy for Cover Images
 *
 * @category VuFind
 * @package  Cover_Generator
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/configuration:external_content Wiki
 */
class CachingProxy
{
    /**
     * HTTP client
     *
     * @var Client
     */
    protected $client;

    /**
     * Base directory for cache
     *
     * @var string
     */
    protected $cache;

    /**
     * Array of regular expressions for hosts to cache
     *
     * @var array
     */
    protected $allowedHosts;

    /**
     * Constructor
     *
     * @param Client  $client       HTTP client
     * @param ?string $cache        Base directory for cache (null to disable caching)
     * @param array   $allowedHosts Array of regular expressions for hosts to cache
     */
    public function __construct(Client $client, $cache, array $allowedHosts = [])
    {
        $this->client = $client;
        $this->cache = $cache;
        $this->allowedHosts = $allowedHosts;
    }

    /**
     * Fetch an image from either a URL or the cache (as appropriate).
     *
     * @param string $url URL to fetch
     *
     * @return Response
     */
    public function fetch($url)
    {
        $file = $this->getCacheFile($url);
        $cacheAllowed = $this->cache && $this->hasLegalHost($url);
        if (!$cacheAllowed || !($response = $this->fetchCache($file))) {
            $response = $this->client->setUri($url)->send();
            if ($cacheAllowed) {
                $this->setCache($file, $response);
            }
        }
        return $response;
    }

    /**
     * Load a response from cache (or return false if cache is missing).
     *
     * @param string $file Cache file to load
     *
     * @return bool|Response
     */
    protected function fetchCache($file)
    {
        return file_exists($file)
            ? unserialize(file_get_contents($file))
            : false;
    }

    /**
     * Save a response to the cache.
     *
     * @param string   $file     Filename to update
     * @param Response $response Response to write
     *
     * @return void
     */
    protected function setCache($file, Response $response)
    {
        if (!$this->cache) {
            return; // don't write if cache is disabled
        }
        if (!file_exists($this->cache)) {
            mkdir($this->cache);
        }
        if (!file_exists(dirname($file))) {
            mkdir(dirname($file));
        }
        file_put_contents($file, serialize($response));
    }

    /**
     * Check if the URL is on the configured list for caching.
     *
     * @param string $url URL to check
     *
     * @return bool
     */
    protected function hasLegalHost($url)
    {
        $host = parse_url($url, PHP_URL_HOST);
        foreach ($this->allowedHosts as $current) {
            if (preg_match($current, $host)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get the cache filename corresponding with the provided URL.
     *
     * @param string $url URL
     *
     * @return string
     * @throws \Exception
     */
    protected function getCacheFile($url)
    {
        if (!$this->cache) {
            throw new \Exception('Unexpected call to getCacheFile -- cache is disabled.');
        }
        $hash = md5($url);
        return $this->cache . '/' . substr($hash, 0, 3) . '/' . substr($hash, 3);
    }
}
