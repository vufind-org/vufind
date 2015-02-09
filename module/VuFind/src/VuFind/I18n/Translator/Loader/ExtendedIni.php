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
 * @package  Translator
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace VuFind\I18n\Translator\Loader;
use Zend\I18n\Exception\InvalidArgumentException,
    Zend\I18n\Translator\Loader\FileLoaderInterface,
    Zend\I18n\Translator\TextDomain;

/**
 * Handles the language loading and language file parsing
 *
 * @category VuFind2
 * @package  Translator
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class ExtendedIni implements FileLoaderInterface
{
    /**
     * List of directories to search for language files.
     *
     * @var array
     */
    protected $pathStack;

    /**
     * Fallback locales to use for language strings missing from selected file.
     *
     * @var string[]
     */
    protected $fallbackLocales;

    /**
     * List of files loaded during the current run -- avoids infinite loops and
     * duplicate loading.
     *
     * @var array
     */
    protected $loadedFiles = array();

    /**
     * Helper for reading .ini files from disk.
     *
     * @var ExtendedIniReader
     */
    protected $reader;

    /**
     * Constructor
     *
     * @param array             $pathStack       List of directories to search for
     * language files.
     * @param string|string[]   $fallbackLocales Fallback locale(s) to use for
     * language strings missing from selected file.
     * @param ExtendedIniReader $reader          Helper for reading .ini files from
     * disk.
     */
    public function __construct($pathStack = array(), $fallbackLocales = null,
        ExtendedIniReader $reader = null
    ) {
        $this->pathStack = $pathStack;
        $this->fallbackLocales = $fallbackLocales;
        if (!empty($this->fallbackLocales) && !is_array($this->fallbackLocales)) {
            $this->fallbackLocales = array($this->fallbackLocales);
        }
        $this->reader = ($reader === null) ? new ExtendedIniReader() : $reader;
    }

    /**
     * load(): defined by LoaderInterface.
     *
     * @param string $locale   Locale to read from language file
     * @param string $filename Language file to read (not used)
     *
     * @return TextDomain
     * @throws InvalidArgumentException
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function load($locale, $filename)
    {
        // Reset the loaded files list:
        $this->resetLoadedFiles();

        // Load base data:
        $data = $this->loadLanguageFile($locale . '.ini');

        // Load fallback data, if any:
        if (!empty($this->fallbackLocales)) {
            foreach ($this->fallbackLocales as $fallbackLocale) {
                $newData = $this->loadLanguageFile($fallbackLocale . '.ini');
                $newData->merge($data);
                $data = $newData;
            }
        }

        return $data;
    }

    /**
     * Reset the loaded file list.
     *
     * @return void
     */
    protected function resetLoadedFiles()
    {
        $this->loadedFiles = array();
    }

    /**
     * Check if a file has already been loaded; mark it loaded if it is not already.
     *
     * @param string $filename Name of file to check and mark as loaded.
     *
     * @return bool True if loaded, false if new.
     */
    protected function checkAndMarkLoadedFile($filename)
    {
        if (isset($this->loadedFiles[$filename])) {
            return true;
        }
        $this->loadedFiles[$filename] = true;
        return false;
    }

    /**
     * Search the path stack for language files and merge them together.
     *
     * @param string $filename Name of file to search path stack for.
     *
     * @return TextDomain
     */
    protected function loadLanguageFile($filename)
    {
        // Don't load a file that has already been loaded:
        if ($this->checkAndMarkLoadedFile($filename)) {
            return new TextDomain();
        }

        $data = false;
        foreach ($this->pathStack as $path) {
            if (file_exists($path . '/' . $filename)) {
                $current = $this->reader->getTextDomain($path . '/' . $filename);
                if ($data === false) {
                    $data = $current;
                } else {
                    $data->merge($current);
                }
            }
        }
        if ($data === false) {
            throw new InvalidArgumentException("Ini file '{$filename}' not found");
        }

        // Load parent data, if necessary:
        return $this->loadParentData($data);
    }

    /**
     * Support method for loadLanguageFile: retrieve parent data.
     *
     * @param TextDomain $data TextDomain to populate with parent information.
     *
     * @return TextDomain
     */
    protected function loadParentData($data)
    {
        if (!isset($data['@parent_ini'])) {
            return $data;
        }
        $parent = $this->loadLanguageFile($data['@parent_ini']);
        $parent->merge($data);
        return $parent;
    }
}
