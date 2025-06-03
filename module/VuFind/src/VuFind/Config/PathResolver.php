<?php

/**
 * Configuration File Path Resolver
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2022.
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
 * @package  Config
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Config;

use VuFind\Config\Location\ConfigLocationInterface;
use VuFind\Config\Location\ConfigLocationTrait;

/**
 * Configuration File Path Resolver
 *
 * @category VuFind
 * @package  Config
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class PathResolver
{
    use ConfigLocationTrait;

    /**
     * Default configuration subdirectory.
     *
     * @var string
     */
    public const DEFAULT_CONFIG_SUBDIR = 'config/vufind';

    /**
     * Base directory
     *
     * Must contain the following keys:
     *
     * directory           - The base configuration directory
     * defaultConfigSubdir - Default subdirectory under directory for configuration
     *                       files
     *
     * @var array
     */
    protected $baseDirectorySpec;

    /**
     * Local configuration directory stack. Local configuration files are searched
     * for in all directories until found, starting from the last entry.
     *
     * Each entry must contain the following keys:
     *
     * directory           - The local configuration directory
     * defaultConfigSubdir - Default subdirectory under directory for configuration
     *                       files
     *
     * @var array
     */
    protected $localConfigDirStack;

    /**
     * Constructor
     *
     * @param array $baseDirectorySpec   Base directory specification
     * @param array $localConfigDirStack Local configuration directory specification
     * stack
     */
    public function __construct(
        array $baseDirectorySpec,
        array $localConfigDirStack
    ) {
        $this->baseDirectorySpec = $baseDirectorySpec;
        $this->localConfigDirStack = $localConfigDirStack;
    }

    /**
     * Get the config location based on the config name.
     *
     * @param string  $configName Config name
     * @param ?string $path       path relative to VuFind base (optional; use null for
     * default)
     *
     * @return ?ConfigLocationInterface
     */
    public function getConfigLocation(
        string $configName,
        ?string $path = null,
    ): ?ConfigLocationInterface {
        return $this->getLocalConfigLocation($configName, $path)
            ?? $this->getConfigLocationFromSpec($configName, $this->baseDirectorySpec, $path);
    }

    /**
     * Get the local config location based on the config name.
     *
     * @param string  $configName Config name
     * @param ?string $path       path relative to VuFind base (optional; use null for
     * default)
     *
     * @return ?ConfigLocationInterface
     */
    public function getLocalConfigLocation(
        string $configName,
        ?string $path = null,
    ): ?ConfigLocationInterface {
        $currentLocation = null;
        foreach ($this->localConfigDirStack as $localDirSpec) {
            $configLocation = $this->getConfigLocationFromSpec($configName, $localDirSpec, $path);
            if ($configLocation !== null) {
                $currentLocation = $configLocation;
            }
        }
        return $currentLocation;
    }

    /**
     * Get the config location from a dir specification stack.
     *
     * @param string  $configName Config name
     * @param array   $dirSpec    Directory specification stack
     * @param ?string $path       path relative to VuFind base (optional; use null for
     * default)
     *
     * @return ?ConfigLocationInterface
     */
    public function getConfigLocationFromSpec(
        string $configName,
        array $dirSpec,
        ?string $path
    ): ?ConfigLocationInterface {
        return $this->getMatchingConfigLocation($this->buildPath($dirSpec, $path), $configName);
    }

    /**
     * Get the file path to the local configuration file (null if none found).
     *
     * @param string  $filename config file name
     * @param ?string $path     path relative to VuFind base (optional; use null for
     * default)
     * @param bool    $force    force method to return path even if file does not
     * exist (default = false, do not force)
     *
     * @return ?string
     */
    public function getLocalConfigPath(
        string $filename,
        ?string $path = null,
        bool $force = false
    ): ?string {
        $configLocation = $this->getLocalConfigLocation($filename, $path);
        if ($configLocation !== null) {
            return $configLocation->getPath();
        }
        if ($force) {
            $localDir = end($this->localConfigDirStack);
            return $this->buildPath($localDir, $path, $filename);
        }
        return null;
    }

    /**
     * Get the file path to the base configuration file.
     *
     * @param string  $filename config file name
     * @param ?string $path     path relative to VuFind base (optional; use null for
     * default)
     *
     * @return string
     */
    public function getBaseConfigPath(string $filename, ?string $path = null): string
    {
        return $this->buildPath($this->baseDirectorySpec, $path, $filename);
    }

    /**
     * Get the file path to a config file.
     *
     * @param string  $filename Config file name
     * @param ?string $path     Path relative to VuFind base (optional; use null for
     * default)
     *
     * @return string
     */
    public function getConfigPath(string $filename, ?string $path = null): ?string
    {
        // Check if config exists in local dir:
        $local = $this->getLocalConfigPath($filename, $path);
        if (!empty($local)) {
            return $local;
        }

        // Return base version:
        return $this->getBaseConfigPath($filename, $path);
    }

    /**
     * Get local config dir stack.
     *
     * @return array
     */
    public function getLocalConfigDirStack(): array
    {
        return $this->localConfigDirStack;
    }

    /**
     * Get path to base dir.
     *
     * @return string
     */
    public function getBaseConfigDirPath(): string
    {
        return $this->buildPath($this->baseDirectorySpec);
    }

    /**
     * Get path to top local dir.
     *
     * @return ?string
     */
    public function getLocalConfigDirPath(): ?string
    {
        $localDirStack = end($this->localConfigDirStack);
        return $localDirStack ? $this->buildPath($localDirStack) : null;
    }

    /**
     * Build a complete file path from a directory specification, optional
     * configuration file sub-directory and a filename.
     *
     * @param array   $directorySpec Directory specification
     * @param ?string $configSubdir  Optional configuration file subdirectory
     * @param ?string $filename      Optional filename
     *
     * @return string
     */
    protected function buildPath(
        array $directorySpec,
        ?string $configSubdir = null,
        ?string $filename = null
    ): string {
        $configSubdir ??= $directorySpec['defaultConfigSubdir'];
        $path = $directorySpec['directory'] . '/' . $configSubdir;
        if ($filename !== null) {
            $path .= '/' . $filename;
        }
        return $path;
    }
}
