<?php

/**
 * Configuration object
 *
 * This is a lightweight replacement for the deprecated Laminas\Config\Config class.
 * It will eventually be deprecated and removed, but it will help ease the migration
 * away from laminas-config in the meantime.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2024.
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
 * @category VuFind
 * @package  Config
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Config;

use ArrayAccess;
use Countable;
use Iterator;
use VuFind\Exception\ConfigException;

use function count;
use function is_array;

/**
 * Configuration object
 *
 * @category VuFind
 * @package  Config
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class Config implements ArrayAccess, Countable, Iterator
{
    /**
     * Constructor
     *
     * @param array $data Configuration array
     */
    public function __construct(protected array $data = [])
    {
    }

    /**
     * Get a property of the configuration (which may be a nested Config object)
     *
     * @param string $key     Property name
     * @param mixed  $default Default value to use if $key is unset
     *
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if (!isset($this->data[$key])) {
            return $default;
        }
        return is_array($this->data[$key]) ? new Config($this->data[$key]) : $this->data[$key];
    }

    /**
     * Get a property of the configuration (which may be a nested Config object)
     *
     * @param string $key Property name
     *
     * @return mixed
     */
    public function __get(string $key): mixed
    {
        return $this->get($key);
    }

    /**
     * Enforce configuration immutability.
     *
     * @param string $key   Key to update
     * @param mixed  $value Value being set
     *
     * @return void
     * @throws ConfigException
     */
    public function __set(string $key, mixed $value): void
    {
        throw new ConfigException("Config is immutable; cannot set $key to $value");
    }

    /**
     * Check if a property is set.
     *
     * @param string $key Key to check
     *
     * @return bool
     */
    public function __isset(string $key): bool
    {
        return isset($this->data[$key]);
    }

    /**
     * Enforce configuration immutability.
     *
     * @param string $key Key to unset
     *
     * @return void
     * @throws ConfigException
     */
    public function __unset(string $key): void
    {
        throw new ConfigException("Config is immutable; cannot unset $key");
    }

    /**
     * String conversion.
     *
     * @return string
     */
    public function __toString(): string
    {
        if (empty($this->data)) {
            return '';
        }
        throw new ConfigException('Config cannot be converted to string');
    }

    /**
     * Return the configuration as an array.
     *
     * @return array
     */
    public function toArray(): array
    {
        return $this->data;
    }

    /**
     * Isset check for ArrayAccess interface.
     *
     * @param mixed $offset Offset to check
     *
     * @return bool
     */
    public function offsetExists(mixed $offset): bool
    {
        return $this->__isset($offset);
    }

    /**
     * Getter for ArrayAccess interface.
     *
     * @param mixed $offset Offset to get
     *
     * @return mixed
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->get($offset);
    }

    /**
     * Setter for ArrayAccess interface.
     *
     * @param mixed $offset Offset to set
     * @param mixed $value  New value
     *
     * @return void
     * @throws ConfigException
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->__set($offset, $value);
    }

    /**
     * Unsetter for ArrayAccess interface.
     *
     * @param mixed $offset Offset to unset
     *
     * @return void
     * @throws ConfigException
     */
    public function offsetUnset(mixed $offset): void
    {
        $this->__unset($offset);
    }

    /**
     * Get count of members (Countable interface).
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->data);
    }

    /**
     * Get current element (Iterator interface).
     *
     * @return mixed
     */
    public function current(): mixed
    {
        return current($this->data);
    }

    /**
     * Get current key (Iterator interface).
     *
     * @return mixed
     */
    public function key(): mixed
    {
        return key($this->data);
    }

    /**
     * Advance the pointer used by the Iterator interface.
     *
     * @return void
     */
    public function next(): void
    {
        next($this->data);
    }

    /**
     * Rewind (Iterator interface).
     *
     * @return void
     */
    public function rewind(): void
    {
        reset($this->data);
    }

    /**
     * Is the Iterator interface in a valid state?
     *
     * @return bool
     */
    public function valid(): bool
    {
        return isset($this->data[$this->key()]);
    }
}
