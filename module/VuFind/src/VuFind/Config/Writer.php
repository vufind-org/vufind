<?php
/**
 * VF Configuration Writer
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
 
/**
 * Class to update VuFind configuration settings
 *
 * @category VuFind2
 * @package  Config
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class VF_Config_Writer
{
    protected $filename;
    protected $content;

    /**
     * Constructor
     *
     * @param string $filename Configuration file to load
     *
     * @throws Exception
     */
    public function __construct($filename)
    {
        $this->filename = $filename;
        $this->content = file_get_contents($filename);
        if (!$this->content) {
            throw new Exception('Could not read ' . $filename);
        }
    }

    /**
     * Change/add a setting
     *
     * @param string $section Section to change/add
     * @param string $setting Setting within section to change/add
     * @param string $value   Value to set
     *
     * @return void
     */
    public function set($section, $setting, $value)
    {
        // Break the configuration file into lines:
        $lines = explode("\n", $this->content);

        // Reset some flags and prepare to rewrite the content:
        $settingSet= false;
        $currentSection = "";
        $this->content = "";

        // Process one line at a time...
        foreach ($lines as $line) {
            // Once the setting is set, we can stop doing fancy processing -- it's
            // just a matter of writing lines through unchanged:
            if (!$settingSet) {
                // Separate comments from content:
                $parts = explode(';', trim($line), 2);
                $content = trim($parts[0]);
                $comment = isset($parts[1]) ? $parts[1] : '';

                // Is this a section heading?
                if (preg_match('/^\[(.+)\]$/', trim($content), $matches)) {
                    // If we just left the target section and didn't find the
                    // desired setting, we should write it to the end.
                    if ($currentSection == $section && !$settingSet) {
                        $line = $setting . ' = "' . $value . '"' . "\n\n" . $line;
                        $settingSet = true;
                    }
                    $currentSection = $matches[1];
                } else if (strstr($content, '=')) {
                    list($key, $oldValue) = explode('=', $content, 2);
                    if ($currentSection == $section && trim($key) == $setting) {
                        $line = $setting . ' = "' . $value . '"';
                        if (!empty($comment)) {
                            $line .= ' ;' . $comment;
                        }
                        $settingSet = true;
                    }
                }
            }

            // Save the current line:
            $this->content .= $line . "\n";
        }

        // Did we loop through everything without finding a place to put the setting?
        if (!$settingSet) {
            // We never found the target section?
            if ($currentSection != $section) {
                $this->content .= '[' . $section . "]\n";
            }
            $this->content .= $setting . ' = "' . $value . '"' . "\n";
        }
    }

    /**
     * Save the modified file to disk.  Return true on success, false on error.
     *
     * @return bool
     */
    public function save()
    {
        return file_put_contents($this->filename, $this->content);
    }

    /**
     * support method for writeFile -- format a value
     *
     * @param mixed $e Value to format
     *
     * @return string  Value formatted for output to ini file.
     */
    protected static function writeValue($e)
    {
        if ($e === true) {
            return 'true';
        } else if ($e === false) {
            return 'false';
        } else if ($e == "") {
            return '';
        } else {
            return '"' . $e . '"';
        }
    }

    /**
     * support method for writeFile -- format a line
     *
     * @param string $key   Configuration key
     * @param mixed  $value Configuration value
     * @param int    $tab   Tab size to help values line up
     *
     * @return string       Formatted line
     */
    protected static function writeLine($key, $value, $tab = 17)
    {
        // Build a tab string so the equals signs line up attractively:
        $tabStr = '';
        for ($i = strlen($key); $i < $tab; $i++) {
            $tabStr .= ' ';
        }
    
        return $key . $tabStr . "= ". self::writeValue($value);
    }

    /**
     * write an ini file, adapted from
     * http://php.net/manual/function.parse-ini-file.php
     *
     * @param array  $assoc_arr Array to output
     * @param array  $comments  Comments to inject
     * @param string $path      File to write
     *
     * @return bool             True on success, false on error.
     */
    public static function writeFile($assoc_arr, $comments, $path)
    {
        $content = "";
        foreach ($assoc_arr as $key=>$elem) {
            if (isset($comments['sections'][$key]['before'])) {
                $content .= $comments['sections'][$key]['before'];
            }
            $content .= "[".$key."]";
            if (!empty($comments['sections'][$key]['inline'])) {
                $content .= "\t" . $comments['sections'][$key]['inline'];
            }
            $content .= "\n";
            foreach ($elem as $key2=>$elem2) {
                if (isset($comments['sections'][$key]['settings'][$key2])) {
                    $settingComments
                        = $comments['sections'][$key]['settings'][$key2];
                    $content .= $settingComments['before'];
                } else {
                    $settingComments = array();
                }
                if (is_array($elem2)) {
                    for ($i = 0; $i < count($elem2); $i++) {
                        $content
                            .= self::writeLine($key2 . "[]", $elem2[$i]) . "\n";
                    }
                } else {
                    $content .= self::writeLine($key2, $elem2);
                }
                if (!empty($settingComments['inline'])) {
                    $content .= "\t" . $settingComments['inline'];
                }
                $content .= "\n";
            }
        }
    
        $content .= $comments['after'];
    
        if (!$handle = fopen($path, 'w')) {
            return false;
        }
        if (!fwrite($handle, $content)) {
            return false;
        }
        fclose($handle);
        return true;
    }
}