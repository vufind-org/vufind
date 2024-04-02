<?php

/**
 * Class to represent currently-selected theme and related information.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010-2023.
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

use Laminas\Cache\Storage\StorageInterface;
use Webmozart\Glob\Glob;

use function is_array;
use function strlen;

/**
 * Class to represent currently-selected theme and related information.
 *
 * @category VuFind
 * @package  Theme
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class ThemeInfo
{
    use \VuFind\Feature\MergeRecursiveTrait;

    /**
     * Base directory for theme files
     *
     * @var string
     */
    protected $baseDir;

    /**
     * Current selected theme
     *
     * @var string
     */
    protected $currentTheme;

    /**
     * A safe theme (guaranteed to exist) that can be loaded if an invalid
     * configuration is passed in
     *
     * @var string
     */
    protected $safeTheme;

    /**
     * Theme configuration cache
     *
     * @var array
     */
    protected $allThemeInfo = null;

    /**
     * Cache for merged configs
     *
     * @var StorageInterface
     */
    protected $cache = null;

    // Constant for use with findContainingTheme:
    public const RETURN_ALL_DETAILS = 'all';

    /**
     * Constructor
     *
     * @param string $baseDir   Base directory for theme files.
     * @param string $safeTheme Theme that should be guaranteed to exist.
     */
    public function __construct($baseDir, $safeTheme)
    {
        $this->baseDir = $baseDir;
        $this->currentTheme = $this->safeTheme = $safeTheme;
    }

    /**
     * Provide cache and activate info caching
     *
     * @param StorageInterface $cache cache object
     *
     * @return void
     */
    public function setCache(StorageInterface $cache)
    {
        $this->cache = $cache;
    }

    /**
     * Get the base directory for themes.
     *
     * @return string
     */
    public function getBaseDir()
    {
        return $this->baseDir;
    }

    /**
     * Get the configuration file for the specified mixin.
     *
     * @param string $mixin Mixin name
     *
     * @return string
     */
    protected function getMixinConfig($mixin)
    {
        return $this->baseDir . "/$mixin/mixin.config.php";
    }

    /**
     * Get the configuration file for the specified theme.
     *
     * @param string $theme Theme name
     *
     * @return string
     */
    protected function getThemeConfig($theme)
    {
        return $this->baseDir . "/$theme/theme.config.php";
    }

    /**
     * Set the current theme.
     *
     * @param string $theme Theme to set.
     *
     * @return void
     * @throws \Exception
     */
    public function setTheme($theme)
    {
        // If the configured theme setting is illegal, throw an exception without
        // making any changes.
        if (!file_exists($this->getThemeConfig($theme))) {
            throw new \Exception('Cannot load theme: ' . $theme);
        }
        if ($theme != $this->currentTheme) {
            // Clear any cached theme information when we change themes:
            $this->allThemeInfo = null;
            $this->currentTheme = $theme;
        }
    }

    /**
     * Get the current theme.
     *
     * @return string
     */
    public function getTheme()
    {
        return $this->currentTheme;
    }

    /**
     * Load configuration for the specified theme (and its mixins, if any) into the
     * allThemeInfo property.
     *
     * @param string $theme Name of theme to load
     *
     * @return void
     */
    protected function loadThemeConfig($theme)
    {
        // Load theme configuration...
        $this->allThemeInfo[$theme] = include $this->getThemeConfig($theme);
        // ..and if there are mixins, load those too!
        if (isset($this->allThemeInfo[$theme]['mixins'])) {
            foreach ($this->allThemeInfo[$theme]['mixins'] as $mix) {
                $this->allThemeInfo[$mix] = include $this->getMixinConfig($mix);
            }
        }
    }

    /**
     * Get all the configuration details related to the current theme.
     *
     * @return array
     */
    public function getThemeInfo()
    {
        // Fill in the theme info cache if it is not already populated:
        if ($this->allThemeInfo === null) {
            // Build an array of theme information by inheriting up the theme tree:
            $this->allThemeInfo = [];
            $currentTheme = $this->getTheme();
            do {
                $this->loadThemeConfig($currentTheme);
                $currentTheme = $this->allThemeInfo[$currentTheme]['extends'];
            } while ($currentTheme);
        }

        return $this->allThemeInfo;
    }

    /**
     * Get a configuration element, merged to reflect theme inheritance.
     *
     * @param string $key Configuration key to retrieve (or empty string to
     * retrieve full configuration)
     *
     * @return array|string
     */
    public function getMergedConfig(string $key = '')
    {
        $currentTheme = $this->getTheme();
        $allThemeInfo = $this->getThemeInfo();

        $cacheKey = $currentTheme . '_' . $key;

        if ($this->cache !== null) {
            $cached = $this->cache->getItem($cacheKey);

            if ($cached !== null) {
                return $cached;
            }
        }

        $merged = [];

        while (!empty($currentTheme)) {
            $currentThemeSet = array_merge(
                (array)$currentTheme,
                $allThemeInfo[$currentTheme]['mixins'] ?? [],
            );

            // from child to parent
            foreach ($currentThemeSet as $theme) {
                if (
                    isset($allThemeInfo[$theme])
                    && (empty($key) || isset($allThemeInfo[$theme][$key]))
                ) {
                    $current = empty($key)
                        ? $allThemeInfo[$theme]
                        : $allThemeInfo[$theme][$key];

                    $merged = $this->mergeRecursive($current, $merged);
                }
            }

            $currentTheme = $allThemeInfo[$currentTheme]['extends'];
        }

        if ($this->cache !== null) {
            $this->cache->setItem($cacheKey, $merged);
        }

        return $merged;
    }

    /**
     * Search the themes for a particular file. If it exists, return the
     * first matching theme name; otherwise, return null.
     *
     * @param string|array $relativePath Relative path (or array of paths) to
     * search within themes
     * @param string|bool  $returnType   If boolean true, return full file path;
     * if boolean false, return containing theme name; if self::RETURN_ALL_DETAILS,
     * return an array containing both values (keyed with 'path', 'theme' and
     * 'relativePath').
     *
     * @return string|array|null
     */
    public function findContainingTheme($relativePath, $returnType = false)
    {
        $basePath = $this->getBaseDir();
        $allPaths = is_array($relativePath)
            ? $relativePath : [$relativePath];

        $currentTheme = $this->getTheme();
        $allThemeInfo = $this->getThemeInfo();

        while (!empty($currentTheme)) {
            $currentThemeSet = array_merge(
                (array)$currentTheme,
                $allThemeInfo[$currentTheme]['mixins'] ?? []
            );
            foreach ($currentThemeSet as $theme) {
                foreach ($allPaths as $currentPath) {
                    $path = "$basePath/$theme/$currentPath";
                    if (file_exists($path)) {
                        // Depending on return type, send back the requested data:
                        if (self::RETURN_ALL_DETAILS === $returnType) {
                            $relativePath = $currentPath;
                            return compact('path', 'theme', 'relativePath');
                        }
                        return $returnType ? $path : $theme;
                    }
                }
            }
            $currentTheme = $allThemeInfo[$currentTheme]['extends'];
        }

        return null;
    }

    /**
     * Search the themes for a file pattern. Returns all matching files.
     *
     * Note that for any matching file the last match in the theme hierarchy is
     * returned.
     *
     * @param string|array $relativePathPattern Relative path pattern (or array of
     * patterns) to search within themes
     *
     * @return array
     */
    public function findInThemes($relativePathPattern)
    {
        $basePath = $this->getBaseDir();
        $allPaths = (array)$relativePathPattern;

        $currentTheme = $this->getTheme();
        $allThemeInfo = $this->getThemeInfo();

        $allThemes = [];
        while (!empty($currentTheme)) {
            $allThemes = array_merge(
                $allThemes,
                (array)$currentTheme,
                $allThemeInfo[$currentTheme]['mixins'] ?? []
            );
            $currentTheme = $allThemeInfo[$currentTheme]['extends'];
        }

        // Start from the base theme so that we can find any overrides properly
        $allThemes = array_reverse($allThemes);
        $results = [];
        foreach ($allThemes as $theme) {
            $themePath = "$basePath/$theme/";
            foreach ($allPaths as $currentPath) {
                $path = $themePath . $currentPath;
                foreach (Glob::glob($path) as $file) {
                    if (filetype($file) === 'dir') {
                        continue;
                    }
                    $relativeFile = substr($file, strlen($themePath));
                    $results[$relativeFile] = [
                        'theme' => $theme,
                        'file' => $file,
                        'relativeFile' => $relativeFile,
                    ];
                }
            }
        }
        return array_values($results);
    }
}
