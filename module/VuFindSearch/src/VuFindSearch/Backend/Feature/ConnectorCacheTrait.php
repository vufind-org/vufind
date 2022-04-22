<?php
/**
 * Caching support trait for connectors.
 *
 * Prerequisites:
 *
 * - LoggerAwareInterface
 *
 * PHP version 7
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

use Laminas\Cache\Storage\StorageInterface;
use Laminas\Http\Client as HttpClient;

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
                $this->debug('Returning cached results');
                return $result;
            }
        } catch (\Exception $ex) {
            $this->logWarning('Cache getItem failed: ' . $ex->getMessage());
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
            // Try to determine if caching failed due to response size and log the
            // case accordingly. Unfortunately Laminas Cache does not translate
            // exceptions to any common error codes, so we must check codes and/or
            // message for adapter-specific values.
            // 'ITEM TOO BIG' is the message from the Memcached adapter
            // and comes directly from libmemcached.
            if ($ex->getMessage() === 'ITEM TOO BIG') {
                $this->debug(
                    'Cache setItem failed: ITEM TOO BIG; Response exceeds configured'
                    . ' maximum cacheable size in memcached'
                );
            } else {
                $this->logWarning('Cache setItem failed: ' . $ex->getMessage());
            }
        } catch (\Exception $ex) {
            $this->logWarning('Cache setItem failed: ' . $ex->getMessage());
        }
    }
}
