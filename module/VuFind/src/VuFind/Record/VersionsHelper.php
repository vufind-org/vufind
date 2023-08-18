<?php

/**
 * Helper that provides support methods for record versions search
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
 * @package  Record
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Record;

/**
 * Helper that provides support methods for record versions search
 *
 * @category VuFind
 * @package  Record
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class VersionsHelper
{
    /**
     * Record loader
     *
     * @var Loader
     */
    protected $recordLoader;

    /**
     * Constructor
     *
     * @param Loader $recordLoader Record loader
     */
    public function __construct(
        Loader $recordLoader
    ) {
        $this->recordLoader = $recordLoader;
    }

    /**
     * Get record id, record driver (if available) and work keys from query params
     *
     * @param array  $params  Query params containing id and/or keys
     * @param string $backend Search backend ID
     *
     * @return array with id, driver and keys
     */
    public function getIdDriverAndWorkKeysFromParams(
        array $params,
        string $backend
    ): array {
        $id = $params['id'] ?? null;
        $keys = (array)($params['keys'] ?? []);
        $driver = null;
        if ($id) {
            $driver = $this->recordLoader->load($id, $backend, true);
            if ($driver instanceof \VuFind\RecordDriver\Missing) {
                $driver = null;
            } else {
                $keys = $driver->tryMethod('getWorkKeys') ?? $keys;
            }
        }
        return compact('id', 'driver', 'keys');
    }

    /**
     * Convert work keys to a search string
     *
     * @param array $keys Work keys
     *
     * @return string
     */
    public function getSearchStringFromWorkKeys(array $keys): string
    {
        $mapFunc = function ($val) {
            return '"' . addcslashes($val, '"') . '"';
        };

        return implode(' OR ', array_map($mapFunc, $keys));
    }

    /**
     * Get search type for work keys search
     *
     * @return string
     */
    public function getWorkKeysSearchType(): string
    {
        return 'WorkKeys';
    }
}
