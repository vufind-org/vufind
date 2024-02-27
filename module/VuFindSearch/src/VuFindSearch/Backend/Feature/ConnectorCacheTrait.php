<?php

/**
 * Caching support trait for connectors.
 *
 * Prerequisites:
 *
 * - Logger as $this->logger
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2022.
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
 * @package  Search
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFindSearch\Backend\Feature;

use Laminas\Cache\Storage\Adapter\Memcached;
use Laminas\Cache\Storage\StorageInterface;
use Laminas\Http\Client as HttpClient;
use Laminas\Log\LoggerInterface;

/**
 * Caching support trait for connectors.
 *
 * @category VuFind
 * @package  Search
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
trait ConnectorCacheTrait
{
    /**
     * Request cache
     *
     * @var StorageInterface
     */
    protected $cache = null;

    /**
     * Set the cache storage
     *
     * @param StorageInterface $cache Cache
     *
     * @return void
     */
    public function setCache(StorageInterface $cache)
    {
        $this->cache = $cache;
    }

    /**
     * Create a cache key from client's request state
     *
     * @param HttpClient $client HTTP Client
     *
     * @return string
     */
    public function getCacheKey(HttpClient $client): string
    {
        return md5($client->getRequest()->toString());
    }

    /**
     * Get a request from cache if available
     *
     * @param string $key Cache key
     *
     * @return mixed
     */
    public function getCachedData(string $key)
    {
        try {
            if ($result = $this->cache->getItem($key)) {
                $this->logCacheDebug('Returning cached results');
                return $result;
            }
        } catch (\Exception $ex) {
            $this->logCacheWarning('getItem failed: ' . $ex->getMessage());
        }
        return null;
    }

    /**
     * Cache response data.
     *
     * @param string $key      Cache entry key
     * @param mixed  $response Response to be cached
     *
     * @return void
     */
    protected function putCachedData(string $key, $response): void
    {
        try {
            $this->cache->setItem($key, $response);
        } catch (\Laminas\Cache\Exception\RuntimeException $ex) {
            if ($this->cache->getCapabilities()->getAdapter() instanceof Memcached) {
                // Try to determine if caching failed due to response size and log
                // the case accordingly. Unfortunately Laminas Cache does not
                // translate exceptions to any common error codes, so we must check
                // the backend-specific code. Note that error code 37 is available as
                // a constant in Memcached, but we're not using it here due to it
                // being an optional extension.
                if ($ex->getCode() === 37) {
                    $this->logCacheDebug(
                        'setItem failed: ' . $ex->getMessage() . '; Response'
                        . ' exceeds configured maximum cacheable size in memcached'
                    );
                    return;
                }
            }
            $this->logCacheWarning('setItem failed: ' . $ex->getMessage());
        } catch (\Exception $ex) {
            $this->logCacheWarning('setItem failed: ' . $ex->getMessage());
        }
    }

    /**
     * Log a warning message
     *
     * @param string $msg Message
     *
     * @return void
     */
    protected function logCacheWarning(string $msg): void
    {
        if (($this->logger ?? null) instanceof LoggerInterface) {
            $this->logger->warn("Cache: $msg");
        } else {
            error_log("Warning: Cache: $msg");
        }
    }

    /**
     * Log a debug message
     *
     * @param string $msg Message
     *
     * @return void
     */
    protected function logCacheDebug(string $msg): void
    {
        if (($this->logger ?? null) instanceof LoggerInterface) {
            $this->logger->debug("Cache: $msg");
        }
    }
}
