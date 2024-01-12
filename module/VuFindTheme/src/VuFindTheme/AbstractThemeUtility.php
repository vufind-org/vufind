<?php

/**
 * Abstract base class to hold shared logic for theme utilities.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2017.
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
 * @package  Theme
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFindTheme;

/**
 * Abstract base class to hold shared logic for theme utilities.
 *
 * @category VuFind
 * @package  Theme
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
abstract class AbstractThemeUtility
{
    /**
     * Theme info object
     *
     * @var ThemeInfo
     */
    protected $info;

    /**
     * Last error message
     *
     * @var string
     */
    protected $lastError = null;

    /**
     * Constructor
     *
     * @param ThemeInfo $info Theme info object
     */
    public function __construct(ThemeInfo $info)
    {
        $this->info = $info;
    }

    /**
     * Get last error message.
     *
     * @return string
     */
    public function getLastError()
    {
        return $this->lastError;
    }

    /**
     * Copy the contents of $src into $dest if no matching files already exist.
     *
     * @param string $src  Source directory
     * @param string $dest Target directory
     *
     * @return bool
     */
    protected function copyDir($src, $dest)
    {
        if (!is_dir($dest)) {
            if (!mkdir($dest)) {
                return $this->setLastError("Cannot create $dest");
            }
        }
        $dir = opendir($src);
        while ($current = readdir($dir)) {
            if ($current === '.' || $current === '..') {
                continue;
            }
            if (is_dir("$src/$current")) {
                if (!$this->copyDir("$src/$current", "$dest/$current")) {
                    return false;
                }
            } elseif (
                !file_exists("$dest/$current")
                && !copy("$src/$current", "$dest/$current")
            ) {
                return $this->setLastError(
                    "Cannot copy $src/$current to $dest/$current."
                );
            }
        }
        closedir($dir);
        return true;
    }

    /**
     * Recursively delete a directory and its contents.
     *
     * @param string $path Directory to delete.
     *
     * @return bool
     */
    protected function deleteDir($path)
    {
        $dir = opendir($path);
        while ($current = readdir($dir)) {
            if ($current === '.' || $current === '..') {
                continue;
            }
            if (is_dir("$path/$current")) {
                if (!$this->deleteDir("$path/$current")) {
                    return false;
                }
            } elseif (!unlink("$path/$current")) {
                return $this->setLastError("Cannot delete $path/$current");
            }
        }
        closedir($dir);
        return rmdir($path);
    }

    /**
     * Set last error message and return a boolean false.
     *
     * @param string $error Error message.
     *
     * @return bool
     */
    protected function setLastError($error)
    {
        $this->lastError = $error;
        return false;
    }
}
