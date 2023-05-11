<?php

/**
 * Abstract base class to precompile CSS within a theme.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2020.
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

use Symfony\Component\Console\Output\OutputInterface;

/**
 * Abstract base class to precompile CSS within a theme.
 *
 * @category VuFind
 * @package  Theme
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
abstract class AbstractCssPreCompiler
{
    /**
     * Key in theme.config.php that lists all files
     *
     * @var string
     */
    protected $themeConfigKey;

    /**
     * Base path of VuFind.
     *
     * @var string
     */
    protected $basePath;

    /**
     * Temporary directory for cached files.
     *
     * @var string
     */
    protected $tempPath;

    /**
     * Fake base path used for generating absolute paths in CSS.
     *
     * @var string
     */
    protected $fakePath = '/zzzz_basepath_zzzz/';

    /**
     * Output object (set for logging)
     *
     * @var OutputInterface
     */
    protected $output;

    /**
     * Constructor
     *
     * @param OutputInterface $output Output interface for logging (optional)
     */
    public function __construct(OutputInterface $output = null)
    {
        $this->basePath = realpath(__DIR__ . '/../../../../');
        $this->tempPath = sys_get_temp_dir();
        $this->output = $output;
    }

    /**
     * Compile scripts for the specified theme.
     *
     * @param string $theme Theme name
     *
     * @return void
     */
    abstract protected function processTheme($theme);

    /**
     * Set base path
     *
     * @param string $path Path to set
     *
     * @return void
     */
    public function setBasePath($path)
    {
        $this->basePath = $path;
    }

    /**
     * Set temporary directory
     *
     * @param string $path Path to set
     *
     * @return void
     */
    public function setTempPath($path)
    {
        $this->tempPath = rtrim($path, '/');
    }

    /**
     * Compile the scripts.
     *
     * @param array $themes Array of themes to process (empty for ALL themes).
     *
     * @return void
     */
    public function compile(array $themes)
    {
        if (empty($themes)) {
            $themes = $this->getAllThemes();
        }

        foreach ($themes as $theme) {
            $this->processTheme($theme);
        }
    }

    /**
     * Get all less files that might exist in a theme.
     *
     * @param string $theme Theme to retrieve files from
     *
     * @return array
     */
    protected function getAllFiles($theme)
    {
        $config = $this->basePath . '/themes/' . $theme . '/theme.config.php';
        if (!file_exists($config)) {
            return [];
        }
        $configArr = include $config;
        $base = (isset($configArr['extends']))
            ? $this->getAllFiles($configArr['extends'])
            : [];
        $current = $configArr[$this->themeConfigKey] ?? [];
        return array_merge($base, $current);
    }

    /**
     * Get a list of all available themes.
     *
     * @return array
     */
    protected function getAllThemes()
    {
        $baseDir = $this->basePath . '/themes/';
        $dir = opendir($baseDir);
        $list = [];
        while ($line = readdir($dir)) {
            if (
                is_dir($baseDir . $line)
                && file_exists($baseDir . $line . '/theme.config.php')
            ) {
                $list[] = $line;
            }
        }
        closedir($dir);
        return $list;
    }

    /**
     * Convert fake absolute paths to working relative paths.
     *
     * @param string $css  Generated CSS
     * @param string $less Relative LESS filename
     *
     * @return string
     *
     * @SuppressWarnings(PHPMD.UnusedLocalVariable)
     */
    protected function makeRelative($css, $less)
    {
        // Figure out how deep the LESS file is nested -- this will
        // affect our relative path. Note: we don't actually need
        // to use $matches for anything, but some versions of PHP
        // seem to be unhappy if we omit the parameter.
        $depth = preg_match_all('|/|', $less, $matches);
        $relPath = '../../../';
        for ($i = 0; $i < $depth; $i++) {
            $relPath .= '/../';
        }
        return str_replace($this->fakePath, $relPath, $css);
    }

    /**
     * Log a message to the console
     *
     * @param string $str message string
     *
     * @return void
     */
    protected function logMessage($str)
    {
        if ($this->output) {
            $this->output->writeln($str);
        }
    }
}
