<?php

/**
 * Class to compile a theme hierarchy into a single flat theme.
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

use function is_array;

/**
 * Class to compile a theme hierarchy into a single flat theme.
 *
 * @category VuFind
 * @package  Theme
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class ThemeCompiler extends AbstractThemeUtility
{
    /**
     * Compile from $source theme into $target theme.
     *
     * @param string $source         Name of source theme
     * @param string $target         Name of target theme
     * @param bool   $forceOverwrite Should we overwrite the target if it exists?
     *
     * @return bool
     */
    public function compile($source, $target, $forceOverwrite = false)
    {
        // Validate input:
        try {
            $this->info->setTheme($source);
        } catch (\Exception $ex) {
            return $this->setLastError($ex->getMessage());
        }
        // Validate output:
        $baseDir = $this->info->getBaseDir();
        $targetDir = "$baseDir/$target";
        if (file_exists($targetDir)) {
            if (!$forceOverwrite) {
                return $this->setLastError(
                    'Cannot overwrite ' . $targetDir . ' without --force switch!'
                );
            }
            if (!$this->deleteDir($targetDir)) {
                return false;
            }
        }
        if (!mkdir($targetDir)) {
            return $this->setLastError("Cannot create $targetDir");
        }

        // Copy all the files, relying on the fact that the output of getThemeInfo
        // includes the entire theme inheritance chain in the appropriate order:
        $info = $this->info->getThemeInfo();
        $config = [];
        foreach ($info as $source => $currentConfig) {
            $config = $this->mergeConfig($currentConfig, $config);
            if (!$this->copyDir("$baseDir/$source", $targetDir)) {
                return false;
            }
        }
        $configFile = "$targetDir/theme.config.php";
        $configContents = '<?php return ' . var_export($config, true) . ';';
        if (!file_put_contents($configFile, $configContents)) {
            return $this->setLastError("Problem exporting $configFile.");
        }
        return true;
    }

    /**
     * Remove a theme directory (used for cleanup in testing).
     *
     * @param string $theme Name of theme to remove.
     *
     * @return bool
     */
    public function removeTheme($theme)
    {
        return $this->deleteDir($this->info->getBaseDir() . '/' . $theme);
    }

    /**
     * Merge configurations from $src into $dest; return the result.
     *
     * @param array $src  Source configuration
     * @param array $dest Destination configuration
     *
     * @return array
     */
    protected function mergeConfig($src, $dest)
    {
        foreach ($src as $key => $value) {
            switch ($key) {
                case 'extends':
                    // always set "extends" to false; we're flattening, after all!
                    $dest[$key] = false;
                    break;
                case 'helpers':
                    // Call this function recursively to deal with the helpers
                    // sub-array:
                    $dest[$key] = $this
                        ->mergeConfig($value, $dest[$key] ?? []);
                    break;
                case 'mixins':
                    // Omit mixin settings entirely
                    break;
                default:
                    // Default behavior: merge arrays, let existing flat settings
                    // trump new incoming ones:
                    if (!isset($dest[$key])) {
                        $dest[$key] = $value;
                    } elseif (is_array($dest[$key])) {
                        $dest[$key] = array_merge($value, $dest[$key]);
                    }
                    break;
            }
        }
        return $dest;
    }
}
