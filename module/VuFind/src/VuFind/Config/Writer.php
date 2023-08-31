<?php

/**
 * VF Configuration Writer
 *
 * PHP version 8
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Config
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Config;

use function dirname;
use function is_array;
use function is_int;
use function strlen;

/**
 * Class to update VuFind configuration settings
 *
 * @category VuFind
 * @package  Config
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class Writer
{
    /**
     * Configuration file to write
     *
     * @var string
     */
    protected $filename;

    /**
     * Content of file
     *
     * @var string
     */
    protected $content;

    /**
     * Constructor
     *
     * @param string            $filename Configuration file to write
     * @param string|array|null $content  Content to load into file (set to null to
     * load contents of existing file specified by $filename; set to array to build
     * string in combination with $comments; set to string to use raw config string)
     * @param array             $comments Comments to associate with content (ignored
     * if $content is not an array).
     *
     * @throws \Exception
     */
    public function __construct($filename, $content = null, $comments = [])
    {
        $this->filename = $filename;
        if (null === $content) {
            $this->content = file_get_contents($filename);
            if (false === $this->content) {
                throw new \Exception('Could not read ' . $filename);
            }
        } elseif (is_array($content)) {
            $this->content = $this->buildContent($content, $comments);
        } else {
            $this->content = $content;
        }
    }

    /**
     * Change/add a setting
     *
     * @param string $section Section to change/add
     * @param string $setting Setting within section to change/add
     * @param string $value   Value to set (or null to unset)
     *
     * @return void
     */
    public function set($section, $setting, $value)
    {
        // Break the configuration file into lines:
        $lines = explode("\n", $this->content);

        // Reset some flags and prepare to rewrite the content:
        $settingSet = false;
        $currentSection = '';
        $this->content = '';

        // Process one line at a time...
        foreach ($lines as $line) {
            // Separate comments from content:
            $parts = explode(';', trim($line), 2);
            $content = trim($parts[0]);
            $comment = $parts[1] ?? '';

            // Is this a section heading?
            if (preg_match('/^\[(.+)\]$/', trim($content), $matches)) {
                // If we just left the target section and didn't find the
                // desired setting, we should write it to the end.
                if (
                    $currentSection == $section && !$settingSet
                    && $value !== null
                ) {
                    $line = $this->buildContentLine($setting, $value, 0)
                        . "\n\n" . $line;
                    $settingSet = true;
                }
                $currentSection = $matches[1];
            } elseif (strstr($content, '=')) {
                $contentParts = explode('=', $content, 2);
                $key = trim($contentParts[0]);
                // If the key we are trying to set is already present as an array,
                // we need to clear out the multiple existing values before writing
                // in a new one:
                if ($key == $setting . '[]') {
                    continue;
                }
                // Standard case for match on section + key:
                if ($currentSection == $section && $key == $setting) {
                    $settingSet = true;
                    if ($value === null) {
                        continue;
                    } else {
                        $line = $this->buildContentLine($setting, $value, 0);
                    }
                    if (!empty($comment)) {
                        $line .= ' ;' . $comment;
                    }
                }
            }

            // Save the current line:
            $this->content .= $line . "\n";
        }

        // Did we loop through everything without finding a place to put the setting?
        if (!$settingSet && $value !== null) {
            // We never found the target section?
            if ($currentSection != $section) {
                $this->content .= '[' . $section . "]\n";
            }
            $this->content .= $this->buildContentLine($setting, $value, 0) . "\n";
        }
    }

    /**
     * Remove a setting (convenience wrapper around set to null).
     *
     * @param string $section Section to change/add
     * @param string $setting Setting within section to change/add
     *
     * @return void
     */
    public function clear($section, $setting)
    {
        $this->set($section, $setting, null);
    }

    /**
     * Get the modified file's contents as a string.
     *
     * @return string
     */
    public function getContent()
    {
        return $this->content;
    }

    /**
     * Save the modified file to disk. Return true on success, false on error.
     *
     * @return bool
     */
    public function save()
    {
        // Create parent directory structure if necessary:
        $stack = [];
        $dirname = dirname($this->filename);
        while (!empty($dirname) && !is_dir($dirname)) {
            $stack[] = $dirname;
            $dirname = dirname($dirname);
        }
        foreach (array_reverse($stack) as $dir) {
            if (!mkdir($dir)) {
                return false;
            }
        }

        // Write the file:
        return file_put_contents($this->filename, $this->getContent());
    }

    /**
     * Support method for buildContent -- format a value
     *
     * @param mixed $e Value to format
     *
     * @return string  Value formatted for output to ini file.
     */
    protected function buildContentValue($e)
    {
        if ($e === true) {
            return 'true';
        } elseif ($e === false) {
            return 'false';
        } elseif ($e == '') {
            return '';
        } else {
            return '"' . str_replace('"', '\"', $e) . '"';
        }
    }

    /**
     * Support method for buildContent -- format a line
     *
     * @param string $key   Configuration key
     * @param mixed  $value Configuration value
     * @param int    $tab   Tab size to help values line up
     *
     * @return string       Formatted line
     */
    protected function buildContentLine($key, $value, $tab = 17)
    {
        // Build a tab string so the equals signs line up attractively:
        $tabStr = '';
        for ($i = strlen($key) + 1; $i < $tab; $i++) {
            $tabStr .= ' ';
        }

        // Special case: if value is an array, we need to adjust the key
        // accordingly:
        if (is_array($value)) {
            $retVal = '';
            // TODO: replace $autoIndex code with array_is_list() check
            // when supported (after PHP 8.1 is minimum required version).
            $autoIndex = 0;
            foreach ($value as $i => $current) {
                // If the array indices are a numeric sequence starting at 0,
                // omit them from the key names; any other index should be
                // explicitly set:
                $currentIndex = ($i === $autoIndex) ? '' : $i;
                $retVal .= $key . '[' . $currentIndex . ']' . $tabStr . ' = '
                    . $this->buildContentValue($current) . "\n";
                $autoIndex++;
            }
            return rtrim($retVal);
        }

        // Standard case: value is not an array:
        return $key . $tabStr . ' = ' . $this->buildContentValue($value);
    }

    /**
     * Support method for buildContent -- format an array into lines
     *
     * @param string $key   Configuration key
     * @param array  $value Configuration value
     *
     * @return string       Formatted line
     */
    protected function buildContentArrayLines($key, $value)
    {
        $expectedKey = 0;
        $content = '';
        foreach ($value as $key2 => $subValue) {
            // We just want to use "[]" if this is a standard array with consecutive
            // keys; however, if we have non-numeric keys or out-of-order keys, we
            // want to retain those values as-is.
            $subKey = (is_int($key2) && $key2 == $expectedKey)
                ? ''
                : (is_int($key2) ? $key2 : "'{$key2}'");    // quote string keys
            $content .= $this->buildContentLine("{$key}[{$subKey}]", $subValue);
            $content .= "\n";
            $expectedKey++;
        }
        return $content;
    }

    /**
     * Write an ini file, adapted from
     * http://php.net/manual/function.parse-ini-file.php
     *
     * @param array $assoc_arr Array to output
     * @param array $comments  Comments to inject
     *
     * @return string
     */
    protected function buildContent($assoc_arr, $comments)
    {
        $content = '';
        foreach ($assoc_arr as $key => $elem) {
            if (isset($comments['sections'][$key]['before'])) {
                $content .= $comments['sections'][$key]['before'];
            }
            $content .= '[' . $key . ']';
            if (!empty($comments['sections'][$key]['inline'])) {
                $content .= "\t" . $comments['sections'][$key]['inline'];
            }
            $content .= "\n";
            foreach ($elem as $key2 => $elem2) {
                if (isset($comments['sections'][$key]['settings'][$key2])) {
                    $settingComments
                        = $comments['sections'][$key]['settings'][$key2];
                    $content .= $settingComments['before'];
                } else {
                    $settingComments = [];
                }
                if (is_array($elem2)) {
                    $content .= $this->buildContentArrayLines($key2, $elem2);
                } else {
                    $content .= $this->buildContentLine($key2, $elem2);
                }
                if (!empty($settingComments['inline'])) {
                    $content .= "\t" . $settingComments['inline'];
                }
                $content .= "\n";
            }
        }
        if (isset($comments['after'])) {
            $content .= $comments['after'];
        }
        return $content;
    }
}
