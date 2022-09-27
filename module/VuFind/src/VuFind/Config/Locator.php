<?php
/**
 * VF Configuration Locator
 *
 * PHP version 7
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Config
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
namespace VuFind\Config;

/**
 * Class to find VuFind configuration files
 *
 * @category VuFind
 * @package  Config
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class Locator
{
    /**
     * Mode for getConfigPath: try to find a local file but fall back to base file
     * if not available.
     *
     * @const int
     */
    public const MODE_AUTO = 0;

    /**
     * Mode for getConfigPath: try to find a local file.
     *
     * @const int
     */
    public const MODE_LOCAL = 1;

    /**
     * Mode for getConfigPath: get the base configuration file path.
     *
     * @const int
     */
    public const MODE_BASE = 2;

    /**
     * Get the file path to the local configuration file (null if none found).
     *
     * @param string $filename config file name
     * @param string $path     path relative to VuFind base (optional; defaults
     * to config/vufind if set to null)
     * @param bool   $force    force method to return path even if file does not
     * exist (default = false, do not force)
     *
     * @return string
     */
    public static function getLocalConfigPath(
        $filename,
        $path = null,
        $force = false
    ) {
        if (null === $path) {
            $path = 'config/vufind';
        }
        if (defined('LOCAL_OVERRIDE_DIR') && strlen(trim(LOCAL_OVERRIDE_DIR)) > 0) {
            $path = LOCAL_OVERRIDE_DIR . '/' . $path . '/' . $filename;
            if ($force || file_exists($path)) {
                return $path;
            }
        }
        return null;
    }

    /**
     * Get the file path to the base configuration file.
     *
     * @param string $filename config file name
     * @param string $path     path relative to VuFind base (optional; defaults
     * to config/vufind
     *
     * @return string
     */
    public static function getBaseConfigPath($filename, $path = 'config/vufind')
    {
        return APPLICATION_PATH . '/' . $path . '/' . $filename;
    }

    /**
     * Get the file path to a config file.
     *
     * @param string  $filename Config file name
     * @param ?string $path     Path relative to VuFind base (optional; defaults
     * to config/vufind
     * @param int     $mode     Whether to check for local file, base file or both
     *
     * @return ?string
     */
    public static function getConfigPath(
        $filename,
        $path = null,
        int $mode = self::MODE_AUTO
    ) {
        if (null === $path) {
            $path = 'config/vufind';
        }
        if (self::MODE_BASE !== $mode) {
            // Check if config exists in local dir:
            $local = static::getLocalConfigPath($filename, $path);
            // Return local config if found or $mode requires:
            if (!empty($local) || self::MODE_LOCAL === $mode) {
                return $local;
            }
        }

        // Return base version:
        return static::getBaseConfigPath($filename, $path);
    }
}
