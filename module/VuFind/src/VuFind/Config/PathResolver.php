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

use VuFind\Config\Handler\PluginManager as HandlerPluginManager;
use VuFind\Config\Location\ConfigDirectory;
use VuFind\Config\Location\ConfigFile;
use VuFind\Config\Location\ConfigLocationInterface;

use function array_key_exists;

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
    protected array $baseDirectorySpec;

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
    protected array $localConfigDirStack;

    /**
     * Cache for locations found in getConfigLocationsInPath.
     *
     * @var array
     */
    protected array $configLocationCache = [];

    /**
     * Constructor
     *
     * @param HandlerPluginManager $configHandlerManager Config handler plugin manager
     * @param array                $baseDirectorySpec    Base directory specification
     * @param array                $localConfigDirStack  Local configuration directory specification stack
     */
    public function __construct(
        protected HandlerPluginManager $configHandlerManager,
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
                $configLocation->setDirLocationsParent($currentLocation);
                $currentLocation = $configLocation;
            }
        }
        return $currentLocation;
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

    /**
     * Get all configuration locations in a specific path.
     *
     * @param string $path Path of the directory to scan
     *
     * @return ConfigLocationInterface[]
     */
    public function getConfigLocationsInPath(string $path): array
    {
        $path = realpath($path);
        if (array_key_exists($path, $this->configLocationCache)) {
            return $this->configLocationCache[$path];
        }
        $dirContent = is_dir($path) ? scandir($path) : [];
        $result = [];
        foreach ($dirContent as $item) {
            if ($item == '.' || $item == '..') {
                continue;
            }
            $itemPath = $path . DIRECTORY_SEPARATOR . $item;
            $configLocation = $this->getConfigLocationOnPath($itemPath);
            // ignore locations without a matching handler
            if ($configLocation === null || !$this->configHandlerManager->hasForLocation($configLocation)) {
                continue;
            }
            $result[] = $configLocation;
        }
        $this->configLocationCache[$path] = $result;
        return $result;
    }

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
        return $configNameMatch;
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
        $fallbackResult = null;
        foreach (array_reverse($this->localConfigDirStack) as $localDirSpec) {
            $configPath = $this->buildPath($localDirSpec, $path, $filename);
            if (file_exists($configPath) || is_dir($configPath)) {
                return $configPath;
            }
            if ($force && null === $fallbackResult) {
                $fallbackResult = $configPath;
            }
        }
        return $fallbackResult;
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
