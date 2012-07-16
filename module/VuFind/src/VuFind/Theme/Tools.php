<?php
/**
 * VuFind Theme Support Methods
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
 * @package  Support_Classes
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace VuFind\Theme;
use Zend\Session\Container as SessionContainer;

/**
 * VuFind Theme Support Methods
 *
 * @category VuFind2
 * @package  Support_Classes
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class Tools
{
    /**
     * Get the base directory for themes.
     *
     * @return string
     */
    public static function getBaseDir()
    {
        return APPLICATION_PATH . '/themes';
    }

    /**
     * Get the container used for handling public resources for themes
     * (CSS, JS, etc.)
     *
     * @return Context
     */
    public static function getResourceContainer()
    {
        static $container = false;
        if (!$container) {
            $container = new ResourceContainer();
        }
        return $container;
    }

    /**
     * Get the container used for persisting theme-related settings from
     * page to page.
     *
     * @return SessionContainer
     */
    public static function getPersistenceContainer()
    {
        static $container = false;
        if (!$container) {
            $container = new SessionContainer('Theme');
        }
        return $container;
    }

    /**
     * Search the themes for a particular file.  If it exists, return the
     * first matching theme name; otherwise, return null.
     *
     * @param string|array $relativePath Relative path (or array of paths) to
     * search within themes
     * @param bool         $returnFile   If true, return full file path instead
     * of theme name
     *
     * @return string
     */
    public static function findContainingTheme($relativePath, $returnFile = false)
    {
        $session = static::getPersistenceContainer();
        $basePath = static::getBaseDir();
        $allPaths = is_array($relativePath)
            ? $relativePath : array($relativePath);

        $currentTheme = $session->currentTheme;

        while (!empty($currentTheme)) {
            foreach ($allPaths as $currentPath) {
                $file = "$basePath/$currentTheme/$currentPath";
                if (file_exists($file)) {
                    return $returnFile ? $file : $currentTheme;
                }
            }
            $currentTheme = $session->allThemeInfo[$currentTheme]->extends;
        }

        return null;
    }
}