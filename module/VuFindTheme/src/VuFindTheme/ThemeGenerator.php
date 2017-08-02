<?php
/**
 * Class to generate a new theme from a template and reconfigure VuFind to use it.
 *
 * PHP version 5
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
 * @author   Chris Hallberg <challber@villanova.edu>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
namespace VuFindTheme;
use VuFind\Config\Locator as ConfigLocator;
use VuFind\Config\Writer as ConfigWriter;
use Zend\Config\Config;
use Zend\Console\Console;

/**
 * Class to generate a new theme from a template and reconfigure VuFind to use it.
 *
 * @category VuFind
 * @package  Theme
 * @author   Chris Hallberg <challber@villanova.edu>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class ThemeGenerator
{
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
     * Generate a new theme from a template.
     *
     * @param string $name          Name of theme to generate.
     * @param string $themeTemplate Name of template theme
     *
     * @return bool
     */
    public function generate($name, $themeTemplate = 'local_theme_example')
    {
        // Check for existing theme
        $baseDir = $this->info->getBaseDir() . '/';
        if (realpath($baseDir . $name)) {
            return $this->setLastError('Theme "' . $name . '" already exists');
        }
        Console::writeLine('Creating new theme: "' . $name . '"');
        $source = $this->getAbsolutePath($baseDir . $themeTemplate);
        $dest = $this->getAbsolutePath($baseDir . $name);
        Console::writeLine("\tCopying $themeTemplate");
        Console::writeLine("\t\tFrom: " . $source);
        Console::writeLine("\t\tTo: " . $dest);
        if (!$this->copyDirectory($source, $dest)) {
            return $this->setLastError("Copy failed.");
        }
        return true;
    }

    /**
     * Configure the specified theme as VuFind's new default theme (and one of
     * the alternatives).
     *
     * @param Config $config Existing VuFind configuration
     * @param string $name   Theme name to add to configuration.
     *
     * @return bool
     */
    public function configure(Config $config, $name)
    {
        // Enable theme
        $configPath = ConfigLocator::getLocalConfigPath('config.ini', null, true);
        Console::writeLine("\tUpdating $configPath...");
        Console::writeLine("\t\t[Site] > theme = $name");
        $writer = new ConfigWriter($configPath);
        $writer->set('Site', 'theme', $name);
        // Enable dropdown
        $settingPrefixes = [
            'bootstrap' => 'bs3',
            'custom' => strtolower(str_replace(' ', '', $name))
        ];
        // - Set alternate_themes
        Console::writeLine("\t\t[Site] > alternate_themes");
        $altSetting = [];
        if (isset($config->Site->alternate_themes)) {
            $alts = explode(',', $config->Site->alternate_themes);
            foreach ($alts as $a) {
                $parts = explode(':', $a);
                if ($parts[1] === 'bootstrap3') {
                    $settingPrefixes['bootstrap'] = $parts[0];
                } elseif ($parts[1] === $name) {
                    $settingPrefixes['custom'] = $parts[0];
                } else {
                    $altSetting[] = $a;
                }
            }
        }
        $altSetting[] = $settingPrefixes['bootstrap'] . ':bootstrap3';
        $altSetting[] = $settingPrefixes['custom'] . ':' . $name;
        $writer->set('Site', 'alternate_themes', implode(',', $altSetting));
        // - Set selectable_themes
        Console::writeLine("\t\t[Site] > selectable_themes");
        $dropSetting = [
            $settingPrefixes['bootstrap'] . ':Bootstrap',
            $settingPrefixes['custom'] . ':' . ucwords($name)
        ];
        if (isset($config->Site->selectable_themes)) {
            $themes = explode(',', $config->Site->selectable_themes);
            foreach ($themes as $t) {
                $parts = explode(':', $t);
                if ($parts[0] !== $settingPrefixes['bootstrap']
                    && $parts[0] !== $settingPrefixes['custom']
                ) {
                    $dropSetting[] = $t;
                }
            }
        }
        $writer->set('Site', 'selectable_themes', implode(',', $dropSetting));
        // Save
        if (!$writer->save()) {
            return $this->setLastError("\tConfiguration saving failed!");
        }
        return true;
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
     * Copies contents from $source to $dest
     *
     * @param string $source full path to source directory
     * @param string $dest   full path to copy destination
     *
     * @return boolean true on success false otherwise
     */
    protected function copyDirectory($source, $dest)
    {
        $sourceHandle = opendir($source);
        if (!file_exists($dest)) {
            mkdir($dest, 0755);
        }

        if (!$sourceHandle) {
            return false;
        }

        $success = true;
        while ($file = readdir($sourceHandle)) {
            if ($file == '.' || $file == '..') {
                continue;
            }

            if (is_dir($source . '/' . $file)) {
                if (!file_exists($dest . '/' . $file)) {
                    mkdir($dest . '/' . $file, 0755);
                }
                if (!$this->copyDirectory("$source/$file", "$dest/$file")) {
                    $success = false;
                    break;
                }
            } else {
                copy($source . '/' . $file, $dest . '/' . $file);
            }
        }
        closedir($sourceHandle);

        return $success;
    }

    /**
     * Removes // and /./ in paths and collapses /../
     * Same as realpath, but doesn't check for file existence
     *
     * @param string $path full path to condense
     *
     * @return string
     */
    protected function getAbsolutePath($path)
    {
        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        $parts = array_filter(explode(DIRECTORY_SEPARATOR, $path), 'strlen');
        $absolutes = [];
        foreach ($parts as $part) {
            if ('.' == $part) {
                continue;
            }
            if ('..' == $part) {
                array_pop($absolutes);
            } else {
                $absolutes[] = $part;
            }
        }
        if (substr($path, 0, 1) === DIRECTORY_SEPARATOR) {
            return DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $absolutes);
        }
        return implode(DIRECTORY_SEPARATOR, $absolutes);
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
