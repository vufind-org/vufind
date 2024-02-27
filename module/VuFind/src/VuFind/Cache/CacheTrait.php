<?php

/**
 * Trait for caching data.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2007.
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
 * @package  Cache
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */

namespace VuFind\Cache;

use Laminas\Cache\Storage\StorageInterface;

/**
 * Trait for caching data.
 *
 * @category VuFind
 * @package  Cache
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
trait CacheTrait
{
    use KeyGeneratorTrait;

    /**
     * Cache for storing data temporarily (e.g. patron blocks with the ILS driver)
     *
     * @var StorageInterface
     */
    protected $cache = null;

    /**
     * Lifetime of cache (in seconds).
     *
     * @var int
     */
    protected $cacheLifetime = 30;

    /**
     * Set a cache storage object.
     *
     * @param StorageInterface $cache Cache storage interface
     *
     * @return void
     */
    public function setCacheStorage(StorageInterface $cache = null)
    {
        $this->cache = $cache;
    }

    /**
     * Helper function for fetching cached data.
     *
     * Data is cached for up to $this->cacheLifetime.
     *
     * @param string $key Cache entry key
     *
     * @return mixed|null Cached entry or null if not cached or expired
     */
    protected function getCachedData($key)
    {
        // No cache object, no cached results!
        if (null === $this->cache) {
            return null;
        }

        $fullKey = $this->getCacheKey($key);
        $item = $this->cache->getItem($fullKey);
        if (null !== $item) {
            // Return value if still valid:
            $lifetime = $item['lifetime'] ?? $this->cacheLifetime;
            if (time() - $item['time'] <= $lifetime) {
                return $item['entry'];
            }
            // Clear expired item from cache:
            $this->cache->removeItem($fullKey);
        }
        return null;
    }

    /**
     * Helper function for storing cached data.
     *
     * Data is cached for up to $this->cacheLifetime seconds.
     *
     * @param string $key      Cache entry key
     * @param mixed  $entry    Entry to be cached
     * @param int    $lifetime Optional lifetime for the entry in seconds
     *
     * @return void
     */
    protected function putCachedData($key, $entry, $lifetime = null)
    {
        // Don't write to cache if we don't have a cache!
        if (null === $this->cache) {
            return;
        }
        $item = [
            'time' => time(),
            'lifetime' => $lifetime,
            'entry' => $entry,
        ];
        $this->cache->setItem($this->getCacheKey($key), $item);
    }

    /**
     * Helper function for removing cached data.
     *
     * @param string $key Cache entry key
     *
     * @return void
     */
    protected function removeCachedData($key)
    {
        // Don't write to cache if we don't have a cache!
        if (null === $this->cache) {
            return;
        }
        $this->cache->removeItem($this->getCacheKey($key));
    }
}
