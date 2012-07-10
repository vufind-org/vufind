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
namespace VuFind\Translator\Loader;
use Zend\I18n\Translator\Loader\Exception\InvalidArgumentException,
    Zend\I18n\Translator\Loader\LoaderInterface,
    Zend\I18n\Translator\TextDomain;

/**
 * Handles the language loading and language file parsing
 *
 * @category VuFind2
 * @package  Support_Classes
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class ExtendedIni implements LoaderInterface
{
    protected $data;

    /**
     * load(): defined by LoaderInterface.
     *
     * @param string $filename Language file to read
     * @param string $locale   Locale to read from language file
     *
     * @return TextDomain
     * @throws InvalidArgumentException
     */
    public function load($filename, $locale)
    {
        $this->data = new TextDomain();
        if (!file_exists($filename)) {
            throw new InvalidArgumentException("Ini file '".$data."' not found");
        }

        // Load base data:
        $this->loadLanguageFile($filename);

        // Load local overrides, if available:
        $localFile = LOCAL_OVERRIDE_DIR . '/languages/' . basename($filename);
        if (file_exists($localFile)) {
            $this->loadLanguageFile($localFile);
        }

        return $this->data;
    }

    /**
     * Parse a language file.
     *
     * @param string $file Filename to load
     *
     * @return void
     */
    protected function loadLanguageFile($file)
    {
        // Manually parse the language file:
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
                        $this->data[$key] = $value;
                    }
                }
            }
        }
    }
}
