<?php
/**
 * Configuration File Path Resolver
 *
 * PHP version 7
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
     * Default configuration path.
     *
     * @var string
     */
    public const DEFAULT_CONFIG_PATH = 'config/vufind';

    /**
     * Base configuration directory
     *
     * @var string
     */
    protected $baseConfigDir;

    /**
     * Local configuration directory stack. Local configuration files are searched
     * for in all directories until found.
     *
     * @var string[]
     */
    protected $localConfigDirStack;

    /**
     * Constructor
     *
     * @param string   $baseConfigDir       Base configuration directory
     * @param string[] $localConfigDirStack Local configuration directory stack
     */
    public function __construct(string $baseConfigDir, array $localConfigDirStack)
    {
        $this->baseConfigDir = $baseConfigDir;
        $this->localConfigDirStack = $localConfigDirStack;
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
        if (null === $path) {
            $path = self::DEFAULT_CONFIG_PATH;
        }
        $fallbackResult = null;
        foreach ($this->localConfigDirStack as $localDir) {
            $configPath = "$localDir/$path/$filename";
            if (file_exists($configPath)) {
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
        if (null === $path) {
            $path = self::DEFAULT_CONFIG_PATH;
        }
        return "{$this->baseConfigDir}/$path/$filename";
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
}
