<?php
/**
 * Class to compile LESS into CSS within a theme.
 *
 * PHP version 5
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Theme
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace VuFindTheme;
use Zend\Console\Console;

/**
 * Class to compile LESS into CSS within a theme.
 *
 * @category VuFind2
 * @package  Theme
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class LessCompiler
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
        $lessFiles = $this->getAllLessFiles($theme);
        if (empty($lessFiles)) {
            $this->logMessage("No LESS in " . $theme);
            return;
        }
        $this->logMessage("Processing " . $theme);
        foreach ($lessFiles as $less) {
            if (is_string($less)) {
                $this->compileFile($theme, $less);
            }
        }
    }

    /**
     * Get all less files that might exist in a theme.
     *
     * @param string $theme Theme to retrieve files from
     *
     * @return array
     */
    protected function getAllLessFiles($theme)
    {
        $config = $this->basePath . '/themes/' . $theme . '/theme.config.php';
        if (!file_exists($config)) {
            return [];
        }
        $configArr = include $config;
        $base = (isset($configArr['extends']))
            ? $this->getAllLessFiles($configArr['extends'])
            : [];
        $current = isset($configArr['less']) ? $configArr['less'] : [];
        return array_merge($base, $current);
    }

    /**
     * Compile a LESS file inside a theme.
     *
     * @param string $theme Theme containing file
     * @param string $less  Relative path to LESS file
     *
     * @return void
     */
    protected function compileFile($theme, $less)
    {
        $parts = explode(':', $less);
        $less = $parts[0];

        $finalOutDir = $this->basePath . '/themes/' . $theme . '/css/';
        list($fileName, ) = explode('.', $less);
        $finalFile = $finalOutDir . $fileName . '.css';

        $this->logMessage("\tcompiling '" . $less .  "' into '" . $finalFile . "'");
        $start = microtime(true);

        $directories = [];
        $info = new ThemeInfo($this->basePath . '/themes', $theme);
        foreach (array_keys($info->getThemeInfo()) as $curTheme) {
            $directories["{$this->basePath}/themes/$curTheme/less/"]
                = $this->fakePath . "themes/$curTheme/css/less";
        }
        $lessDir = $this->basePath . '/themes/' . $theme . '/less/';
        if (!file_exists($lessDir . $less)) {
            $this->logMessage(
                "\t\t" . $lessDir . $less . ' does not exist; skipping.'
            );
            return;
        }
        $outFile = \Less_Cache::Get(
            [$lessDir . $less => $this->fakePath . "themes/$theme/css/less"],
            [
                'cache_dir' => $this->tempPath,
                'cache_method' => 'php',
                'compress' => true,
                'import_dirs' => $directories
            ]
        );
        $css = file_get_contents($this->tempPath . '/' . $outFile);
        if (!is_dir(dirname($finalFile))) {
            mkdir(dirname($finalFile));
        }
        file_put_contents($finalFile, $this->makeRelative($css, $less));

        $this->logMessage("\t\t" . (microtime(true) - $start) . ' sec');
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