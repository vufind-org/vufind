<?php

/**
 * Configuration location trait - Provides configuration location helper methods
 *
 * PHP version 8
 *
 * Copyright (C) Hebis Verbundzentrale 2025.
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
 * @package  Config_Location
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\Config\Location;

use VuFind\Exception\ConfigException;

/**
 * Configuration location trait - Provides configuration location helper methods
 *
 * @category VuFind
 * @package  Config_Location
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
trait ConfigLocationTrait
{
    /**
     * Get a matching configuration location based on a config name from a directory if present.
     *
     * @param string $path       Path of the directory to scan
     * @param string $configName Configuration name
     *
     * @return ?ConfigLocationInterface
     */
    public function getMatchingConfigLocation(string $path, string $configName): ?ConfigLocationInterface
    {
        $configLocations = $this->getConfigLocationsInPath($path);
        $configNameMatch = null;
        foreach ($configLocations as $configLocation) {
            // exact matches are preferred
            if ($configLocation->getFileName() === $configName) {
                return $configLocation;
            }
            // fallback if there is no exact match
            if ($configLocation->getConfigName() === $configName) {
                $configNameMatch = $configLocation;
            }
        }
        if ($configNameMatch !== null) {
            return $configNameMatch;
        }
        return null;
    }

    /**
     * Get all configuration locations in a specific path.
     *
     * @param string $path Path of the directory to scan
     *
     * @return ConfigLocationInterface[]
     */
    public function getConfigLocationsInPath(string $path): array
    {
        $dirContent = is_dir($path) ? scandir($path) : [];
        $result = [];
        foreach ($dirContent as $item) {
            // Exclude "." and "..". Files that include .bak or .dist should be skipped because they represent
            // backups (e.g config.ini.bak.100000 for upgraded configs) or
            // templates for configuration (e.g. DirLocations.ini.dist)
            $ignoredExtensions = ['bak', 'dist'];
            $ignorePattern = "/(^\.{1,2}$|\.(" . implode('|', $ignoredExtensions) . ")(\.|$))/";
            if (
                preg_match($ignorePattern, $item)
            ) {
                continue;
            }
            $itemPath = $path . DIRECTORY_SEPARATOR . $item;
            $configLocation = $this->getConfigLocationOnPath($itemPath);
            if ($configLocation === null) {
                throw new ConfigException('Could not create config location on path: ' . $itemPath);
            }
            $result[] = $configLocation;
        }
        return $result;
    }

    /**
     * Get configuration location on a specific path if present.
     *
     * @param string $path Path
     *
     * @return ?ConfigLocationInterface
     */
    public function getConfigLocationOnPath(string $path): ?ConfigLocationInterface
    {
        if (is_dir($path)) {
            return new ConfigDirectory($path);
        } elseif (file_exists($path)) {
            return new ConfigFile($path);
        }
        return null;
    }
}
