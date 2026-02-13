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

use JsonSerializable;

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
class NestingParamBag extends ParamBag implements JsonSerializable
{
    /**
     * Transform any ParamBag into a NestingParamBag.
     *
     * @param ?ParamBag $original     The original ParamBag
     * @param bool      $createIfNull Create an empty ParamBag if $original is null
     *
     * @return ?NestingParamBag
     */
    public static function from(?ParamBag $original, bool $createIfNull = true): ?NestingParamBag
    {
        if (!$original) {
            return $createIfNull ? new NestingParamBag() : null;
        }
        if ($original instanceof NestingParamBag) {
            return $original;
        }
        $bag = new NestingParamBag();
        $bag->mergeWith($original);
        return $bag;
    }

    /**
     * Transform a potentially nested array of values into a NestingParamBag.
     *
     * @param array $values Source values
     *
     * @return NestingParamBag
     */
    public static function fromArray(array $values): NestingParamBag
    {
        $bag = new NestingParamBag();
        foreach ($values as $name => $value) {
            $bag->addMultiNested($name, $value);
        }
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
    public function hasNestedParam(string $name, string $nestedName): bool
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
    public function setNested(string $name, string $nestedName, string $nestedValue): void
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
    public function addNested(string $name, string $nestedName, string $nestedValue): void
    {
        $nestedBag = $this->items[$name] ?? null;
        if (!$nestedBag) {
            $nestedBag = [new ParamBag()];
            $this->set($name, $nestedBag);
        }
        $nestedBag[0]->add($nestedName, $nestedValue);
    }

    /**
     * Parse n-deep arrays to add values.
     *
     * @param string $name  Parameter name
     * @param mixed  $value A scalar value, or some n-deep array of arrays into parameters
     *
     * @return void
     */
    public function addMultiNested(string $name, mixed $value): void
    {
        if (is_array($value)) {
            if (array_is_list($value)) {
                foreach ($value as $valueItem) {
                    $this->add($name, $valueItem);
                }
            } else {
                $nestedBag = $this->items[$name][0] ?? null;
                if (!$nestedBag || !$nestedBag instanceof NestingParamBag) {
                    $nestedBag = NestingParamBag::from($nestedBag);
                    $this->set($name, $nestedBag);
                }
                foreach ($value as $nestedName => $nestedValue) {
                    $nestedBag->addMultiNested($nestedName, $nestedValue);
                }
            }
        } else {
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
        $existingValues = $this->items[$name] ?? [];
        $this->validateCompatibleValues($existingValues, $value, $name);

        if ($value && is_array($value)) {
            // Merge as needed so there is only one ParamBag for any $name
            if ($value[0] instanceof ParamBag) {
                $existingValues[0]->mergeWith($value[0]);
            }
        }

        parent::add($name, $value, $deduplicate);
    }

    /**
     * Validate that the new values can be added to the existing values.
     *
     * @param array   $existingValues The existing values
     * @param mixed   $newValues      Values being added
     * @param ?string $name           Name associated with these values
     *
     * @throws \Exception If the combination of values is not valid.
     *
     * @return void
     */
    protected function validateCompatibleValues(array $existingValues, mixed $newValues, ?string $name)
    {
        if (!is_array($newValues)) {
            $newValues = [$newValues];
        }
        foreach ([$existingValues, $newValues] as $values) {
            if (!$values) {
                return;
            }
            $this->validateValues($values, $name);
        }
        // Either both arrays, or neither, should contain a ParamBag
        if (
            empty(array_filter($existingValues, fn ($value) => $value instanceof ParamBag)) !=
            empty(array_filter($newValues, fn ($value) => $value instanceof ParamBag))
        ) {
            throw new \Exception('New values for name ' . ($name ?? '(unknown)')
                . ' are not compatible with existing values; both or neither must be a ParamBag.');
        }
    }

    /**
     * Return a serializable object, for json_encode use into a POST body.
     *
     * @return mixed
     */
    public function jsonSerialize(): mixed
    {
        $serializable = $this->jsonSerializeItems($this->items);
        return $serializable;
    }

    /**
     * Parse ParamBag items into an array, recursively.
     *
     * @param array $items The array from a ParamBag
     *
     * @return array
     */
    protected function jsonSerializeItems($items): array
    {
        $jsonObject = [];
        foreach ($items as $name => $values) {
            if (is_array($values)) {
                $this->validateValues($values, $name);

                if (count($values) > 1) {
                    $jsonObject[$name] = $values;
                } elseif (count($values) == 1) {
                    $value = $values[0];
                    if ($value instanceof ParamBag) {
                        $nestedValues = $value->getArrayCopy();
                        $jsonObject[$name] = $this->jsonSerializeItems($nestedValues);
                    } else {
                        $jsonObject[$name] = $value;
                    }
                }
            } else {
                throw new \Exception('ParamBag values for ' . $name . ' is not an array.');
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
        throw new \Exception('Simple query parameters are not supported by NestingParamBag');
    }

    /**
     * Validate the values in a ParamBag.
     *
     * @param array   $values The values
     * @param ?string $name   Name associated with these values
     *
     * @throws \Exception If the combination of values is not valid.
     *
     * @return void
     */
    protected function validateValues(array $values, ?string $name): void
    {
        if (
            count($values) > 1 &&
            array_filter($values, fn ($value) => $value instanceof ParamBag)
        ) {
            throw new \Exception('More than one value for name ' . ($name ?? '(unknown)')
                . 'including at least one ParamBag.');
        }
    }
}
