<?php
/**
 * VF Configuration Reader
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
 * @package  Config
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace VuFind\Config;
use Zend\Config\Config, Zend\Config\Reader\Ini as IniReader;

/**
 * Class to digest VuFind configuration settings
 *
 * @category VuFind2
 * @package  Config
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class Reader
{
    protected static $configs = array();

    /**
     * Load the proper config file
     *
     * @param string $name        Config file name (no .ini; null for main config)
     * @param bool   $forceReload Reload config from disk even if already in cache
     *
     * @return \Zend\Config\Config
     */
    public static function getConfig($name = null, $forceReload = false)
    {
        if (is_null($name)) {
            $name = 'config';
        }
        // Not already cached?  Load it now.
        if ($forceReload || !isset(self::$configs[$name])) {
            self::$configs[$name] = self::loadConfigFile($name . '.ini');
        }
        return self::$configs[$name];
    }

    /**
     * Get the Ini Reader.
     *
     * @return \Zend\Config\Reader\Ini
     */
    protected static function getIniReader()
    {
        static $iniReader = false;

        // Set up reader if it is not already present in the static variable:
        if (!$iniReader) {
            // Use ASCII 0 as a nest separator; otherwise some of the unusual
            // key names we have (i.e. in WorldCat.ini search options) will get
            // parsed in unexpected ways.
            $iniReader = new IniReader();
            $iniReader->setNestSeparator(chr(0));
        }

        return $iniReader;
    }

    /**
     * Load the specified configuration file.
     *
     * @param string $filename config file name
     * @param string $path     path relative to VuFind base (optional; defaults
     * to config/vufind
     *
     * @return \Zend\Config\Config
     */
    public static function loadConfigFile($filename, $path = 'config/vufind')
    {
        $configs = array();

        $fullpath = Locator::getConfigPath($filename, $path);

        // Retrieve and parse at least one configuration file, and possibly a whole
        // chain of them if the Parent_Config setting is used:
        do {
            $configs[]
                = new Config(static::getIniReader()->fromFile($fullpath), true);

            $i = count($configs) - 1;
            $fullpath = isset($configs[$i]->Parent_Config->path)
                ? $configs[$i]->Parent_Config->path : false;
        } while ($fullpath);

        // The last element in the array will be the top of the inheritance tree.
        // Let's establish a baseline:
        $config = array_pop($configs);

        // Now we'll pull all the children down one at a time and override settings
        // as appropriate:
        while (!is_null($child = array_pop($configs))) {
            $overrideSections = isset($child->Parent_Config->override_full_sections)
                ? explode(
                    ',', str_replace(
                        ' ', '', $child->Parent_Config->override_full_sections
                    )
                )
                : array();
            foreach ($child as $section => $contents) {
                if (in_array($section, $overrideSections)
                    || !isset($config->$section)
                ) {
                    $config->$section = $child->$section;
                } else {
                    foreach ($contents as $key => $value) {
                        $config->$section->$key = $child->$section->$key;
                    }
                }
            }
        }

        $config->setReadOnly();
        return $config;
    }
}