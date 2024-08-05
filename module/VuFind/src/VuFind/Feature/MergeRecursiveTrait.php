<?php

/**
 * VuFind Merge Recursive Trait - Provides Custom Array Merge Function
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2024
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
 * @package  Feature
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Chris Hallberg <challber@villanova.edu>
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Feature;

use function array_key_exists;
use function is_array;

/**
 * VuFind Merge Recursive Trait - Provides Custom Array Merge Function
 *
 * @category VuFind
 * @package  Feature
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Chris Hallberg <challber@villanova.edu>
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
trait MergeRecursiveTrait
{
    /**
     * Determine if a variable is a string-keyed array
     *
     * @param mixed $op Variable to test
     *
     * @return boolean
     */
    protected function isStringKeyedArray($op)
    {
        return is_array($op) && !array_is_list($op);
    }

    /**
     * Merge array recursive without combining single values to a new array
     * as php's array_merge_recursive function does.
     *
     * @param array|string $val1 First Value
     * @param array|string $val2 Second Value
     *
     * @return array|string merged values
     */
    protected function mergeRecursive($val1, $val2)
    {
        if ($val2 === null || $val2 === []) {
            return $val1;
        }

        // Early escape for string, number, etc. values
        if (!is_array($val2) && !is_array($val1)) {
            return $val2;
        }

        if (!$this->isStringKeyedArray($val2)) {
            return array_merge((array)$val1, (array)$val2);
        }

        if (!$this->isStringKeyedArray($val1)) {
            // don't merge if incompatible
            return $val2;
        }

        foreach ($val1 as $key => $val) {
            if (!array_key_exists($key, $val2)) {
                // capture missing string keys
                $val2[$key] = $val;
            } else {
                // recurse
                $val2[$key] = $this->mergeRecursive($val, $val2[$key]);
            }
        }
        return $val2;
    }
}
