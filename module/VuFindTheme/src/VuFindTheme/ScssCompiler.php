<?php

/**
 * Class to compile SCSS into CSS within a theme.
 *
 * PHP version 8
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

use function dirname;

/**
 * Class to compile SCSS into CSS within a theme.
 *
 * @category VuFind
 * @package  Theme
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class ScssCompiler extends AbstractCssPreCompiler
{
    /**
     * Key in theme.config.php that lists all files
     *
     * @var string
     */
    protected $themeConfigKey = 'scss';

    /**
     * Compile scripts for the specified theme.
     *
     * @param string $theme Theme name
     *
     * @return void
     */
    protected function processTheme($theme)
    {
        // Get files
        $files = $this->getAllFiles($theme);
        if (empty($files)) {
            $this->logMessage('No SCSS in ' . $theme);
            return;
        }

        // Build parent stack
        $themeInfo = new ThemeInfo($this->basePath . '/themes', $theme);
        $importPaths = [];
        foreach (array_keys($themeInfo->getThemeInfo()) as $currTheme) {
            $importPaths[] = $this->basePath . '/themes/' . $currTheme . '/scss/';
        }

        // Compile
        $scss = new \ScssPhp\ScssPhp\Compiler();
        $scss->setImportPaths($importPaths);
        $this->logMessage('Processing ' . $theme);
        $finalOutDir = $this->basePath . '/themes/' . $theme . '/css/';
        foreach ($files as $key => $file) {
            if ($key === 'active') {
                continue;
            }

            $this->logMessage("\t" . $file);

            // Check importPaths for file
            $exists = false;
            foreach ($importPaths as $path) {
                if (file_exists($path . $file)) {
                    $exists = true;
                    break;
                }
            }
            if (!$exists) {
                $this->logMessage("\t\tnot found; skipping.");
                continue;
            }

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
}
