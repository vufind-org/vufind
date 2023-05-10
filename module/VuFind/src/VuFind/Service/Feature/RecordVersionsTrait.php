<?php

/**
 * Trait that provides support methods for record versions search
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2020-2023.
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
 * @package  Service
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Service\Feature;

/**
 * Trait that provides support methods for record versions search
 *
 * @category VuFind
 * @package  Service
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
trait RecordVersionsTrait
{
    /**
     * Convert work keys to a search string
     *
     * @param array $keys Work keys
     *
     * @return string
     */
    protected function getSearchStringFromWorkKeys(array $keys): string
    {
        $mapFunc = function ($val) {
            return '"' . addcslashes($val, '"') . '"';
        };

        return implode(' OR ', array_map($mapFunc, (array)$keys));
    }

    /**
     * Get search type for work keys search
     *
     * @return string
     */
    protected function getWorkKeysSearchType(): string
    {
        return 'WorkKeys';
    }
}
