<?php
/**
 * Class to compile LESS into CSS within a theme.
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2014.
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

use ScssPhp\ScssPhp\Compiler;
use Zend\Console\Console;

/**
 * Class to compile SCSS into CSS within a theme.
 *
 * @category VuFind
 * @package  Theme
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class ScssCompiler
{
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
     * Console log?
     *
     * @var bool
     */
    protected $verbose;

    /**
     * Constructor
     *
     * @param bool $verbose Display messages while compiling?
     */
    public function __construct($verbose = false)
    {
        $this->basePath = realpath(__DIR__ . '/../../../../');
        $this->tempPath = sys_get_temp_dir();
        $this->verbose = $verbose;
    }

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
     * Compile scripts for the specified theme.
     *
     * @param string $theme Theme name
     *
     * @return void
     */
    protected function processTheme($theme)
    {
        $scss = new Compiler();
        $scss->setImportPaths($this->basePath . '/themes/' . $theme . '/scss/');
        $files = $this->getAllScssFiles($theme);
        if (empty($files)) {
            $this->logMessage("No SCSS in " . $theme);
            return;
        }
        $this->logMessage('Processing ' . $theme);
        $finalOutDir = $this->basePath . '/themes/' . $theme . '/css/';
        foreach ($files as $key => $file) {
            if ($key === 'active') {
                continue;
            }
            $this->logMessage("\t" . $file);
            $start = microtime(true);
            $finalFile = $finalOutDir . str_replace('.scss', '.css', $file) . '.css';
            if (!is_dir(dirname($finalFile))) {
                mkdir(dirname($finalFile));
            }
            file_put_contents(
                $finalOutDir . str_replace('.scss', '.css', $file),
                $scss->compile('@import "' . $file . '";')
            );
            $this->logMessage("\t\t" . (microtime(true) - $start) . ' sec');
        }
    }

    /**
     * Get all less files that might exist in a theme.
     *
     * @param string $theme Theme to retrieve files from
     *
     * @return array
     */
    protected function getAllScssFiles($theme)
    {
        $config = $this->basePath . '/themes/' . $theme . '/theme.config.php';
        if (!file_exists($config)) {
            return [];
        }
        $configArr = include $config;
        $base = (isset($configArr['extends']))
            ? $this->getAllScssFiles($configArr['extends'])
            : [];
        $current = $configArr['scss'] ?? [];
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
            if (is_dir($baseDir . $line)
                && file_exists($baseDir . $line . '/theme.config.php')
            ) {
                $list[] = $line;
            }
        }
        closedir($dir);
        return $list;
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
        if ($this->verbose) {
            Console::writeLine($str);
        }
    }
}
