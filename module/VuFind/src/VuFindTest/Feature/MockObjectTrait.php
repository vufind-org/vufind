<?php

/**
 * Trait containing helper functions for augmenting mocked objects.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2025.
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
 * @package  Tests
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Feature;

use ReflectionClass;

/**
 * Trait containing helper functions for augmenting mocked objects.
 *
 * @category VuFind
 * @package  Tests
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
trait MockObjectTrait
{
    /**
     * Helper function to set objects protected variable to another
     *
     * @param object $object   Object to change value for
     * @param string $variable Variable to change
     * @param mixed  $value    Value to set
     *
     * @return void
     */
    public function setObjectVariable(object $object, string $variable, mixed $value): void
    {
        $reflection = new ReflectionClass($object);
        $property = $reflection->getProperty($variable);
        $property->setAccessible(true);
        $property->setValue($object, $value);
    }
}
