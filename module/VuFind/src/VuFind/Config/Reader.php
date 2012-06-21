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
use Zend\Config\Config,
    Zend\Config\Reader\Ini as IniReader;

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
    protected static $searchSpecs = array();

    /**
     * Load the proper config file
     *
     * @param string $name        Config file name (no .ini; null for main config)
     * @param bool   $forceReload Reload config from disk even if already in cache
     *
     * @return Zend\Config\Config
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
     * Get the file path to the local configuration file (null if none found).
     *
     * @param string $filename config file name
     * @param string $path     path relative to VuFind base (optional; defaults
     * to config/vufind
     *
     * @return string
     */
    public static function getLocalConfigPath($filename,
        $path = 'config/vufind'
    ) {
        if (defined('LOCAL_OVERRIDE_DIR') && strlen(trim(LOCAL_OVERRIDE_DIR)) > 0) {
            $path = LOCAL_OVERRIDE_DIR . '/' . $path . '/' . $filename;
            if (file_exists($path)) {
                return $path;
            }
        }
        return null;
    }

    /**
     * Get the file path to the base configuration file.
     *
     * @param string $filename config file name
     * @param string $path     path relative to VuFind base (optional; defaults
     * to config/vufind
     *
     * @return string
     */
    public static function getBaseConfigPath($filename, $path='config/vufind')
    {
        return APPLICATION_PATH . '/' . $path . '/' . $filename;
    }

    /**
     * Get the file path to a config file.
     *
     * @param string $filename config file name
     * @param string $path     path relative to VuFind base (optional; defaults
     * to config/vufind
     *
     * @return string
     */
    public static function getConfigPath($filename, $path = 'config/vufind')
    {
        // Check if config exists in local dir:
        $local = self::getLocalConfigPath($filename, $path);
        if (!empty($local)) {
            return $local;
        }

        // No local version?  Return default core version:
        return self::getBaseConfigPath($filename, $path);
    }

    /**
     * Get the Ini Reader.
     *
     * @return Zend\Config\Reader\Ini
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
     *
     * @return Zend\Config\Config
     */
    public static function loadConfigFile($filename)
    {
        $configs = array();

        $fullpath = self::getConfigPath($filename);

        // Retrieve and parse at least one configuration file, and possibly a whole
        // chain of them if the Parent_Config setting is used:
        do {
            $configs[] = new Config(static::getIniReader()->fromFile($fullpath));

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

        return $config;
    }

    /**
     * Return search specs
     *
     * @param string $filename config file name
     *
     * @return array
     */
    public static function getSearchSpecs($filename)
    {/*
        // Load data if it is not already in the object's static cache:
        if (!isset(self::$searchSpecs[$filename])) {
            // Connect to searchspecs cache:
            $manager = new VF_Cache_Manager();
            $cache = $manager->getCache('searchspecs');

            // Determine full configuration file path:
            $fullpath = self::getBaseConfigPath($filename);
            $local = self::getLocalConfigPath($filename);

            // Generate cache key:
            $key = $filename . '-' . filemtime($fullpath);
            if (!empty($local)) {
                $key .= '-local-' . filemtime($local);
            }
            $key = md5($key);

            // Generate data if not found in cache:
            if (!$cache || !($results = $cache->load($key))) {
                $results = Horde_Yaml::load(file_get_contents($fullpath));
                if (!empty($local)) {
                    $localResults = Horde_Yaml::load(file_get_contents($local));
                    foreach ($localResults as $key => $value) {
                        $results[$key] = $value;
                    }
                }
                if ($cache) {
                    $cache->save($results, $key);
                }
            }
            self::$searchSpecs[$filename] = $results;
        }

        return self::$searchSpecs[$filename];*/
    }

    /**
     * readIniComments
     *
     * Read the specified file and return an associative array of this format
     * containing all comments extracted from the file:
     *
     * array =>
     *   'sections' => array
     *     'section_name_1' => array
     *       'before' => string ("Comments found at the beginning of this section")
     *       'inline' => string ("Comments found at the end of the section's line")
     *       'settings' => array
     *         'setting_name_1' => array
     *           'before' => string ("Comments found before this setting")
     *           'inline' => string ("Comments found at the end of setting's line")
     *           ...
     *         'setting_name_n' => array (same keys as setting_name_1)
     *        ...
     *      'section_name_n' => array (same keys as section_name_1)
     *   'after' => string ("Comments found at the very end of the file")
     *
     * @param string $filename Name of ini file to read.
     *
     * @return array           Associative array as described above.
     */
    public static function extractComments($filename)
    {
        $lines = file($filename);

        // Initialize our return value:
        $retVal = array('sections' => array(), 'after' => '');

        // Initialize variables for tracking status during parsing:
        $section = $comments = '';

        foreach ($lines as $line) {
            // To avoid redundant processing, create a trimmed version of the current
            // line:
            $trimmed = trim($line);

            // Is the current line a comment?  If so, add to the currentComments
            // string. Note that we treat blank lines as comments.
            if (substr($trimmed, 0, 1) == ';' || empty($trimmed)) {
                $comments .= $line;
            } else if (substr($trimmed, 0, 1) == '['
                && ($closeBracket = strpos($trimmed, ']')) > 1
            ) {
                // Is the current line the start of a section?  If so, create the
                // appropriate section of the return value:
                $section = substr($trimmed, 1, $closeBracket - 1);
                if (!empty($section)) {
                    // Grab comments at the end of the line, if any:
                    if (($semicolon = strpos($trimmed, ';')) !== false) {
                        $inline = trim(substr($trimmed, $semicolon));
                    } else {
                        $inline = '';
                    }
                    $retVal['sections'][$section] = array(
                        'before' => $comments,
                        'inline' => $inline,
                        'settings' => array());
                    $comments = '';
                }
            } else if (($equals = strpos($trimmed, '=')) !== false) {
                // Is the current line a setting?  If so, add to the return value:
                $set = trim(substr($trimmed, 0, $equals));
                $set = trim(str_replace('[]', '', $set));
                if (!empty($section) && !empty($set)) {
                    // Grab comments at the end of the line, if any:
                    if (($semicolon = strpos($trimmed, ';')) !== false) {
                        $inline = trim(substr($trimmed, $semicolon));
                    } else {
                        $inline = '';
                    }
                    // Currently, this data structure doesn't support arrays very
                    // well, since it can't distinguish which line of the array
                    // corresponds with which comments.  For now, we just append all
                    // the preceding and inline comments together for arrays.  Since
                    // we rarely use arrays in the config.ini file, this isn't a big
                    // concern, but we should improve it if we ever need to.
                    if (!isset($retVal['sections'][$section]['settings'][$set])) {
                        $retVal['sections'][$section]['settings'][$set]
                            = array('before' => $comments, 'inline' => $inline);
                    } else {
                        $retVal['sections'][$section]['settings'][$set]['before']
                            .= $comments;
                        $retVal['sections'][$section]['settings'][$set]['inline']
                            .= "\n" . $inline;
                    }
                    $comments = '';
                }
            }
        }

        // Store any leftover comments following the last setting:
        $retVal['after'] = $comments;

        return $retVal;
    }
}