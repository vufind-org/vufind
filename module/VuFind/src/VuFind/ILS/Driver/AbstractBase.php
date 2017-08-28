<?php
/**
 * Default ILS driver base class.
 *
 * PHP version 5
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
 * @package  ILS_Drivers
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
namespace VuFind\ILS\Driver;
use Zend\Cache\Storage\StorageInterface,
    VuFind\Cache\KeyGeneratorTrait;

/**
 * Default ILS driver base class.
 *
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 *
 * @SuppressWarnings(PHPMD.NumberOfChildren)
 */
abstract class AbstractBase implements DriverInterface
{
    use KeyGeneratorTrait;

    /**
     * Cache for storing ILS data temporarily (e.g. patron blocks)
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
     * Driver configuration
     *
     * @var array
     */
    protected $config = [];

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
     * Set configuration.
     *
     * Set the configuration for the driver.
     *
     * @param array $config Configuration array (usually loaded from a VuFind .ini
     * file whose name corresponds with the driver class name).
     *
     * @return void
     */
    public function setConfig($config)
    {
        $this->config = $config;
    }

    /**
     * Helper function for fetching cached data.
     * Data is cached for up to $this->cacheLifetime seconds so that it would be
     * faster to process e.g. requests where multiple calls to the backend are made.
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
            if (time() - $item['time'] < $this->cacheLifetime) {
                return $item['entry'];
            }
            // Clear expired item from cache:
            $this->cache->removeItem($fullKey);
        }
        return null;
    }

    /**
     * Helper function for storing cached data.
     * Data is cached for up to $this->cacheLifetime seconds so that it would be
     * faster to process e.g. requests where multiple calls to the backend are made.
     *
     * @param string $key   Cache entry key
     * @param mixed  $entry Entry to be cached
     *
     * @return void
     */
    protected function putCachedData($key, $entry)
    {
        // Don't write to cache if we don't have a cache!
        if (null === $this->cache) {
            return;
        }
        $item = [
            'time' => time(),
            'entry' => $entry
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
