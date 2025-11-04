<?php

/**
 * PSR-6 cache item implementation used with Finna Code Sets library.
 *
 * Partial implementation with no support for time to live (TTL).
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

use Psr\Cache\CacheItemInterface;

/**
 * PSR-6 cache item implementation used with Finna Code Sets library.
 *
 * @category Finna
 * @package  Cache
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class CodeSetsCacheItem implements CacheItemInterface
{
    /**
     * The key for the current cache item.
     *
     * @var string
     */
    protected string $key;

    /**
     * The serializable value to be stored.
     *
     * @var mixed
     */
    protected mixed $value;

    /**
     * Has the request resulted in a cache hit.
     *
     * @var bool
     */
    protected bool $isHit;

    /**
     * CodeSetsCacheItem constructor.
     *
     * @param string $key   The key for the current cache item
     * @param mixed  $value The serializable value to be stored
     * @param bool   $isHit Has the request resulted in a cache hit
     */
    public function __construct(
        string $key,
        mixed $value,
        bool $isHit
    ) {
        $this->key = $key;
        $this->value = $value;
        $this->isHit = $isHit;
    }

    /**
     * Returns the key for the current cache item.
     *
     * @return string
     *   The key string for this cache item.
     */
    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * Retrieves the value of the item from the cache associated with this object's key.
     *
     * @return mixed
     *   The value corresponding to this cache item's key, or null if not found.
     */
    public function get(): mixed
    {
        return $this->value;
    }

    /**
     * Confirms if the cache item lookup resulted in a cache hit.
     *
     * @return bool
     *   True if the request resulted in a cache hit. False otherwise.
     */
    public function isHit(): bool
    {
        return $this->isHit;
    }

    /**
     * Sets the value represented by this cache item.
     *
     * @param mixed $value The serializable value to be stored.
     *
     * @return static
     *   The invoked object.
     */
    public function set(mixed $value): static
    {
        $this->value = $value;
        return $this;
    }

    /**
     * Sets the expiration time for this cache item.
     *
     * @param \DateTimeInterface|null $expiration Expiration
     *
     * @return static
     *   The called object.
     */
    public function expiresAt($expiration): static
    {
        // Not supported.
        return $this;
    }

    /**
     * Sets the expiration time for this cache item.
     *
     * @param int|\DateInterval|null $time Time
     *
     * @return static
     *   The called object.
     */
    public function expiresAfter($time): static
    {
        // Not supported.
        return $this;
    }
}
