<?php

/**
 * PSR-6 cache item pool decorator used with Finna Code Sets library.
 *
 * Partial implementation with no support for removing items, deferred items or
 * time to live (TTL).
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2023.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category Finna
 * @package  Cache
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace Finna\Cache;

use Laminas\Cache\Storage\StorageInterface;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * PSR-6 cache item pool decorator used with Finna Code Sets library.
 *
 * @category Finna
 * @package  Cache
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class CodeSetsCacheItemPoolDecorator implements CacheItemPoolInterface
{
    /**
     * Storage.
     *
     * @var StorageInterface
     */
    protected StorageInterface $storage;

    /**
     * CodeSetsCacheItemPoolDecorator constructor.
     *
     * @param StorageInterface $storage Storage
     */
    public function __construct(StorageInterface $storage)
    {
        $this->storage = $storage;
    }

    /**
     * Returns a Cache Item representing the specified key.
     *
     * @param string $key The key for which to return the corresponding Cache Item.
     *
     * @return CacheItemInterface
     *   The corresponding Cache Item.
     */
    public function getItem($key)
    {
        return new CodeSetsCacheItem($key, $this->storage->getItem($key), $this->hasItem($key));
    }

    /**
     * Returns a traversable set of cache items.
     *
     * @param string[] $keys An indexed array of keys of items to retrieve.
     *
     * @return array|\Traversable
     *   A traversable collection of Cache Items keyed by the cache keys of
     *   each item.
     */
    public function getItems(array $keys = [])
    {
        $items = [];
        foreach ($keys as $key) {
            $item = $this->getItem($key);
            $items[$item->getKey()] = $item;
        }
        return $items;
    }

    /**
     * Confirms if the cache contains specified cache item.
     *
     * @param string $key The key for which to check existence.
     *
     * @return bool
     *   True if item exists in the cache, false otherwise.
     */
    public function hasItem($key)
    {
        return $this->storage->hasItem($key);
    }

    /**
     * Deletes all items in the pool.
     *
     * @return bool
     *   True if the pool was successfully cleared. False if there was an error.
     */
    public function clear()
    {
        // Not supported.
        return false;
    }

    /**
     * Removes the item from the pool.
     *
     * @param string $key The key to delete.
     *
     * @return bool
     *   True if the item was successfully removed. False if there was an error.
     */
    public function deleteItem($key)
    {
        // Not supported.
        return false;
    }

    /**
     * Removes multiple items from the pool.
     *
     * @param string[] $keys An array of keys that should be removed from the pool.
     *
     * @return bool
     *   True if the items were successfully removed. False if there was an error.
     */
    public function deleteItems(array $keys)
    {
        // Not supported.
        return false;
    }

    /**
     * Persists a cache item immediately.
     *
     * @param CacheItemInterface $item The cache item to save.
     *
     * @return bool
     *   True if the item was successfully persisted. False if there was an error.
     */
    public function save(CacheItemInterface $item)
    {
        $this->storage->setItem($item->getKey(), $item->get());
        return true;
    }

    /**
     * Sets a cache item to be persisted later.
     *
     * @param CacheItemInterface $item The cache item to save.
     *
     * @return bool
     *   False if the item could not be queued or if a commit was attempted and failed. True otherwise.
     */
    public function saveDeferred(CacheItemInterface $item)
    {
        // Not supported.
        return false;
    }

    /**
     * Persists any deferred cache items.
     *
     * @return bool
     *   True if all not-yet-saved items were successfully saved or there were none. False otherwise.
     */
    public function commit()
    {
        // Not supported.
        return false;
    }
}
