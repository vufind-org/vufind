<?php
/**
 * VuFind Translate Adapter ExtendedIni
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
 * @package  Support_Classes
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace VuFind\Translator\Adapter;
use Zend\Translator\Adapter\AbstractAdapter;

/**
 * Handles the language loading and language file parsing
 *
 * @category VuFind2
 * @package  Support_Classes
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class ExtendedIni extends AbstractAdapter
{
    protected $data = array();

    // @codingStandardsIgnoreStart
    /**
     * Load translation data
     *
     * @param string|array $data    Data file to parse
     * @param string       $locale  Locale/Language to add data for, identical
     * with locale identifier, see Zend_Locale for more information
     * @param array        $options OPTIONAL Options to use
     *
     * @throws Zend_Translate_Exception Ini file not found
     * @return array
     */
    protected function _loadTranslationData($data, $locale, array $options = array())
    {
        $this->data = array();
        if (!file_exists($data)) {
            throw new Zend_Translate_Exception("Ini file '".$data."' not found");
        }

        $inidata = $this->parseLanguageFile($data);
        if (!isset($this->data[$locale])) {
            $this->data[$locale] = array();
        }

        $this->data[$locale] = array_merge($this->data[$locale], $inidata);
        return $this->data;
    }
    // @codingStandardsIgnoreEnd

    /**
     * Parse a language file.
     *
     * @param string $file Filename to load
     *
     * @return array
     */
    protected function parseLanguageFile($file)
    {
        // Manually parse the language file:
        $words = array();
        $contents = file($file);
        if (is_array($contents)) {
            foreach ($contents as $current) {
                // Split the string on the equals sign, keeping a max of two chunks:
                $parts = explode('=', $current, 2);
                $key = trim($parts[0]);
                if (!empty($key) && substr($key, 0, 1) != ';') {
                    // Trim outermost double quotes off the value if present:
                    if (isset($parts[1])) {
                        $value = preg_replace(
                            '/^\"?(.*?)\"?$/', '$1', trim($parts[1])
                        );

                        // Store the key/value pair (allow empty values -- sometimes
                        // we want to replace a language token with a blank string):
                        $words[$key] = $value;
                    }
                }
            }
        }
        
        return $words;
    }

    /**
     * returns the adapter's name
     *
     * @return string
     */
    public function toString()
    {
        return "ExtendedIni";
    }
}
