<?php

/**
 * Base formatter for API responses
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2015-2016.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.    See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  API_Formatter
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */

namespace VuFindApi\Formatter;

use function count;
use function is_array;
use function is_bool;

/**
 * Base formatter for API responses
 *
 * @category VuFind
 * @package  API_Formatter
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
class BaseFormatter
{
    /**
     * Recursive function to filter array fields:
     * - remove empty values
     * - convert boolean values to 0/1
     * - force numerically indexed (non-associative) arrays to have numeric keys.
     *
     * @param array $array Array to check
     *
     * @return void
     */
    protected function filterArrayValues(&$array)
    {
        foreach ($array as $key => &$value) {
            if (is_array($value) && !empty($value)) {
                $this->filterArrayValues($value);
                $this->resetArrayIndices($value);
            }

            // We don't want to return empty values -- unless it's an empty array
            // with a non-numeric key, since the key could be significant (e.g. in
            // the case of an author name => roles array with no assigned roles).
            if (
                (is_numeric($key) && is_array($value) && empty($value))
                || (is_bool($value) && !$value)
                || $value === null || $value === ''
            ) {
                unset($array[$key]);
            } elseif (is_bool($value) || $value === 'true' || $value === 'false') {
                $array[$key] = $value === true || $value === 'true' ? 1 : 0;
            }
        }
        $this->resetArrayIndices($array);
    }

    /**
     * Reset numerical array indices.
     *
     * @param array $array Array
     *
     * @return void
     */
    protected function resetArrayIndices(&$array)
    {
        $isNumeric = count(array_filter(array_keys($array), 'is_string')) === 0;
        if ($isNumeric) {
            $array = array_values($array);
        }
    }
}
