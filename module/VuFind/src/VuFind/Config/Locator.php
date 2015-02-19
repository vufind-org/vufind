<?php
/**
 * VF Configuration Locator
 *
 * PHP version 5
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Config
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace VuFind\Config;

/**
 * Class to find VuFind configuration files
 *
 * @category VuFind2
 * @package  Config
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class Locator
{
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
    public static function getLocalConfigPath($filename, $path = null,
        $force = false
    ) {
        if (is_null($path)) {
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
     * @param string $filename config file name
     * @param string $path     path relative to VuFind base (optional; defaults
     * to config/vufind
     *
     * @return string
     */
    public static function getConfigPath($filename, $path = 'config/vufind')
    {
        // Check if config exists in local dir:
        $local = static::getLocalConfigPath($filename, $path);
        if (!empty($local)) {
            return $local;
        }

        // No local version?  Return default core version:
        return static::getBaseConfigPath($filename, $path);
    }
}