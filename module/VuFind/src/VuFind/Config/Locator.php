<?php

/**
 * VuFind Configuration Locator - A static compatibility wrapper around PathResolver
 *
 * PHP version 8
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

use function defined;
use function strlen;

/**
 * VuFind Configuration Locator - A static compatibility wrapper around PathResolver
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
     * Get the file path to the local configuration file (null if none found).
     *
     * @param string  $filename config file name
     * @param ?string $path     path relative to VuFind base (optional; use null for
     * default)
     * @param bool    $force    force method to return path even if file does not
     * exist (default = false, do not force)
     *
     * @return ?string
     *
     * @deprecated Use PathResolver instead
     */
    public static function getLocalConfigPath(
        $filename,
        $path = null,
        $force = false
    ) {
        return static::getPathResolver()
            ->getLocalConfigPath($filename, $path, $force);
    }

    /**
     * Get the file path to the base configuration file.
     *
     * @param string  $filename config file name
     * @param ?string $path     path relative to VuFind base (optional; use null for
     * default)
     *
     * @return string
     *
     * @deprecated Use PathResolver instead
     */
    public static function getBaseConfigPath($filename, $path = null)
    {
        return static::getPathResolver()->getBaseConfigPath($filename, $path);
    }

    /**
     * Get the file path to a config file.
     *
     * @param string  $filename Config file name
     * @param ?string $path     Path relative to VuFind base (optional; use null for
     * default)
     *
     * @return string
     *
     * @deprecated Use PathResolver instead
     */
    public static function getConfigPath($filename, $path = null)
    {
        return static::getPathResolver()->getConfigPath($filename, $path);
    }

    /**
     * Get a PathResolver with default configuration file paths
     *
     * @return PathResolver
     */
    protected static function getPathResolver(): PathResolver
    {
        $localDirs = defined('LOCAL_OVERRIDE_DIR')
            && strlen(trim(LOCAL_OVERRIDE_DIR)) > 0
            ? [
                [
                    'directory' => LOCAL_OVERRIDE_DIR,
                    'defaultConfigSubdir' => PathResolver::DEFAULT_CONFIG_SUBDIR,
                ],
            ] : [];
        return new \VuFind\Config\PathResolver(
            [
                'directory' => APPLICATION_PATH,
                'defaultConfigSubdir' => PathResolver::DEFAULT_CONFIG_SUBDIR,
            ],
            $localDirs
        );
    }
}
