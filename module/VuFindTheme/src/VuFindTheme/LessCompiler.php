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

/**
 * Class to compile LESS into CSS within a theme.
 *
 * @category VuFind
 * @package  Theme
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class LessCompiler extends AbstractCssPreCompiler
{
    /**
     * Key in theme.config.php that lists all files
     *
     * @var string
     */
    protected $themeConfigKey = 'less';

    /**
     * Compile scripts for the specified theme.
     *
     * @param string $theme Theme name
     *
     * @return void
     */
    protected function processTheme($theme)
    {
        $lessFiles = $this->getAllFiles($theme);
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
        [$fileName, ] = explode('.', $less);
        $finalFile = $finalOutDir . $fileName . '.css';

        $this->logMessage("\tcompiling '" . $less . "' into '" . $finalFile . "'");
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
}
