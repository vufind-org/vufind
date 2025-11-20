<?php

/**
 * VuFind Directory Utility Trait
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2025
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Feature
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Feature;

use VuFind\Exception\FileAccess;

/**
 * VuFind Directory Utility Trait
 *
 * @category VuFind
 * @package  Feature
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
trait DirUtilityTrait
{
    /**
     * Copy the contents of $src into $dest if no matching files already exist.
     *
     * @param string $src  Source directory
     * @param string $dest Target directory
     *
     * @return void
     *
     * @throws FileAccess
     */
    public static function cpDir(string $src, string $dest): void
    {
        if (!is_dir($dest)) {
            if (!mkdir($dest)) {
                throw new FileAccess("Cannot create $dest");
            }
        }
        $dir = opendir($src);
        while ($current = readdir($dir)) {
            if ($current === '.' || $current === '..') {
                continue;
            }
            if (is_dir("$src/$current")) {
                self::cpDir("$src/$current", "$dest/$current");
            } elseif (
                !file_exists("$dest/$current")
                && !copy("$src/$current", "$dest/$current")
            ) {
                throw new FileAccess("Cannot copy $src/$current to $dest/$current.");
            }
        }
        closedir($dir);
    }

    /**
     * Recursively delete a directory and its contents.
     *
     * @param string $path Directory to delete.
     *
     * @return void
     *
     * @throws FileAccess
     */
    public static function rmDir(string $path): void
    {
        $dir = opendir($path);
        while ($current = readdir($dir)) {
            if ($current === '.' || $current === '..') {
                continue;
            }
            if (is_dir("$path/$current")) {
                self::rmDir("$path/$current");
            } elseif (!unlink("$path/$current")) {
                throw new FileAccess("Cannot delete $path/$current");
            }
        }
        closedir($dir);
        if (!rmdir($path)) {
            throw new FileAccess("Cannot delete $path");
        }
    }
}
