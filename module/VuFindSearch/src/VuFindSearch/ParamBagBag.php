<?php

/**
 * Bag of parameters or nested parameter bags.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Search
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */

namespace VuFindSearch;

use function count;
use function is_array;

/**
 * Bag of (string) parameters or nested ParamBags.
 *
 * @category VuFind
 * @package  Search
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class ParamBagBag extends ParamBag
{
    /**
     * Transform any ParamBag into a ParamBagBag.
     *
     * @param ?ParamBag $original The original ParamBag
     *
     * @return ?ParamBagBag
     */
    public static function from(ParamBag $original): ?ParamBagBag
    {
        if (!$original) {
            return null;
        }
        if ($original instanceof ParamBagBag) {
            return $original;
        }
        $bag = new ParamBagBag();
        $bag->mergeWith($original);
        return $bag;
    }

    /**
     * Return nested parameter value.
     *
     * @param string $name       Parameter name
     * @param string $nestedName Nested parameter name
     *
     * @return ?array Array of parameter values or NULL if not set
     */
    public function getNested(string $name, string $nestedName): ?array
    {
        $nestedBag = $this->get($name);
        if (!$nestedBag) {
            return null;
        }
        return $nestedBag[0]->get($nestedName);
    }

    /**
     * Return true if the bag contains any value(s) for the specified parameters.
     *
     * @param string $name       Parameter name
     * @param string $nestedName Nested parameter name
     *
     * @return bool
     */
    public function hasNestedParam($name, $nestedName): bool
    {
        $nestedBag = $this->get($name);
        if (!$nestedBag) {
            return false;
        }
        return $nestedBag[0]->hasParam($nestedName);
    }

    /**
     * Set a nested parameter.
     *
     * @param string $name        Parameter name
     * @param string $nestedName  Nested parameter name
     * @param string $nestedValue Nested parameter value
     *
     * @return void
     */
    public function setNested($name, $nestedName, $nestedValue): void
    {
        $nestedBag = $this->items[$name] ?? null;
        if (!$nestedBag) {
            $nestedBag = [new ParamBag()];
            $this->set($name, $nestedBag);
        }
        $nestedBag[0]->set($nestedName, $nestedValue);
    }

    /**
     * Add a nested parameter value.
     *
     * @param string $name        Parameter name
     * @param string $nestedName  Nested parameter name
     * @param string $nestedValue Nested parameter value
     *
     * @return void
     */
    public function addNested($name, $nestedName, $nestedValue): void
    {
        $nestedBag = $this->items[$name] ?? null;
        if (!$nestedBag) {
            $nestedBag = new ParamBag();
            $this->set($name, $nestedBag);
        }
        $nestedBag[0]->add($nestedName, $nestedValue);
    }

    /**
     * Parse n-deep arrays to add values.
     *
     * @param string $name  Parameter name
     * @param string $value Some n-deep array of arrays into parameters
     *
     * @return void
     */
    public function addMultiNested($name, $value): void
    {
        if (is_array($value)) {
            $nestedBag = $this->items[$name] ?? null;
            if (!$nestedBag) {
                $nestedBag = new ParamBagBag();
                $this->set($name, $nestedBag);
            }
            foreach ($value as $nestedName => $nestedValue) {
                $nestedBag->addMultiNested($nestedName, $nestedValue);
            }
        }
        else {
            $this->add($name, $value);
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
    public function add($name, $value, $deduplicate = true): void
    {
        // Merge as needed so there is only one ParamBag for any $name
        if (is_array($value) && count($value) && $value[0] instanceof ParamBag) {
            $existingValues = $this->items[$name] ?? [];
            if (count($existingValues) && $existingValues[0] instanceof ParamBag) {
                $existingValues[0]->mergeWith($value[0]);
                return;
            }
            throw new \Exception('WTF are we combining?');
        }

        parent::add($name, $value, $deduplicate);
    }

    /**
     * Return JSON string of params ready to be used in a HTTP POST body.
     *
     * @return string
     */
    public function json(): string
    {
        $jsonObject = $this->jsonObject($this->items);
        return json_encode($jsonObject);
    }

    /**
     * Parse ParamBag items into an array, recursively.
     *
     * @param array $items
     *
     * @return array
     */
    protected function jsonObject($items)
    {
        foreach ($items as $name => $values) {
            if (is_array($values) && count($values) > 1) {
                throw new \Exception('got more than one value for ' . $name);
            }
            if (count($values) == 1) {
                $value = $values[0];
                if ($value instanceof ParamBag) {
                    $nestedValues = $value->getArrayCopy();
                    $jsonObject[$name] = $this->jsonObject($nestedValues);
                } else {
                    $jsonObject[$name] = $value;
                }
            } else {
                // TODO This can't work properly...need unique names?
                // But will JSON ever require non-unique?  If not, then using the array-based parambag is not needed?
                $jsonObject[$name] = $values;
            }
        }
        return $jsonObject;
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
        throw new \Exception('fix this...cannot happen?');
    }
}
