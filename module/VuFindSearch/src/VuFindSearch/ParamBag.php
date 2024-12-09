<?php

/**
 * Parameter bag.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
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
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */

namespace VuFindSearch;

use function count;
use function in_array;
use function is_array;
use function sprintf;

/**
 * Lightweight wrapper for request parameters.
 *
 * This class represents the request parameters. Parameters are stored in an
 * associative array with the parameter name as key. Because e.g. SOLR allows
 * repeated query parameters the values are always stored in an array.
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class ParamBag implements \Countable
{
    /**
     * Parameters
     *
     * @var array
     */
    protected $params = [];

    /**
     * Constructor.
     *
     * @param array $initial Initial parameters
     *
     * @return void
     */
    public function __construct(array $initial = [])
    {
        foreach ($initial as $name => $value) {
            $this->add($name, $value);
        }
    }

    /**
     * Return parameter value.
     *
     * @param string $name Parameter name
     *
     * @return mixed|null Parameter value or NULL if not set
     */
    public function get($name)
    {
        return $this->params[$name] ?? null;
    }

    /**
     * Count parameters in internal array. Needed for Countable interface.
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->params);
    }

    /**
     * Return true if the bag contains any value(s) for the specified parameter.
     *
     * @param string $name Parameter name
     *
     * @return bool
     */
    public function hasParam($name)
    {
        return isset($this->params[$name]);
    }

    /**
     * Return true if the bag contains a parameter-value-pair.
     *
     * @param string $name  Parameter name
     * @param string $value Parameter value
     *
     * @return bool
     */
    public function contains($name, $value)
    {
        $haystack = $this->get($name);
        return is_array($haystack) && in_array($value, $haystack);
    }

    /**
     * Set a parameter.
     *
     * @param string $name  Parameter name
     * @param string $value Parameter value
     *
     * @return void
     */
    public function set($name, $value)
    {
        if (is_array($value)) {
            $this->params[$name] = $value;
        } else {
            $this->params[$name] = [$value];
        }
    }

    /**
     * Remove a parameter.
     *
     * @param string $name Parameter name
     *
     * @return void
     */
    public function remove($name)
    {
        if (isset($this->params[$name])) {
            unset($this->params[$name]);
        }
    }

    /**
     * Add parameter value.
     *
     * @param string $name        Parameter name
     * @param mixed  $value       Parameter value
     * @param bool   $deduplicate Deduplicate parameter values
     *
     * @return void
     */
    public function add($name, $value, $deduplicate = true)
    {
        if (!isset($this->params[$name])) {
            $this->params[$name] = [];
        }
        if (is_array($value)) {
            $this->params[$name] = array_merge_recursive($this->params[$name], $value);
        } else {
            $this->params[$name][] = $value;
        }
        if ($deduplicate) {
            // Avoid deduplicating associative array params (like Primo filterList):
            foreach ($this->params[$name] as $key => $current) {
                if (!is_numeric($key) || is_array($current)) {
                    return;
                }
            }
            $this->params[$name] = array_values(array_unique($this->params[$name]));
        }
    }

    /**
     * Merge with another parameter bag.
     *
     * @param ParamBag $bag Parameter bag to merge with
     *
     * @return void
     */
    public function mergeWith(ParamBag $bag)
    {
        foreach ($bag->params as $key => $value) {
            if (!empty($value)) {
                $this->add($key, $value);
            }
        }
    }

    /**
     * Merge with all supplied parameter bags.
     *
     * @param array $bags Parameter bags to merge with
     *
     * @return void
     */
    public function mergeWithAll(array $bags)
    {
        foreach ($bags as $bag) {
            $this->mergeWith($bag);
        }
    }

    /**
     * Return copy of parameters as array.
     *
     * @return array
     */
    public function getArrayCopy()
    {
        return $this->params;
    }

    /**
     * Exchange the parameter array.
     *
     * @param array $input New parameters
     *
     * @return array Old parameters
     */
    public function exchangeArray(array $input)
    {
        $current = $this->params;
        $this->params = [];
        foreach ($input as $key => $value) {
            $this->set($key, $value);
        }
        return $current;
    }

    /**
     * Return array of params ready to be used in a HTTP request.
     *
     * Returns a numerical array with all request parameters as properly URL
     * encoded key-value pairs.
     *
     * @return array
     */
    public function request()
    {
        $request = [];
        foreach ($this->params as $name => $values) {
            if (!empty($values)) {
                $request = array_merge(
                    $request,
                    array_map(
                        function ($value) use ($name) {
                            return sprintf(
                                '%s=%s',
                                urlencode($name),
                                urlencode($value ?? '')
                            );
                        },
                        $values
                    )
                );
            }
        }
        return $request;
    }
}
