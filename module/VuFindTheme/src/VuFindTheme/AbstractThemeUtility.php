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

use VuFind\Exception\FileAccess;
use VuFind\Feature\DirUtilityTrait;

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
    use DirUtilityTrait;

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
        try {
            self::cpDir($src, $dest);
        } catch (FileAccess $e) {
            $this->setLastError($e->getMessage());
            return false;
        }
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
        try {
            self::rmDir($path);
        } catch (FileAccess $e) {
            $this->setLastError($e->getMessage());
            return false;
        }
        return true;
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
