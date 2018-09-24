<?php
/**
 * VuFind Translate Adapter ExtendedIni
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2010,
 *               Leipzig University Library <info@ub.uni-leipzig.de> 2018.
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
 * @package  Translator
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Sebastian Kehr <kehr@ub.uni-leipzig.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
namespace VuFind\I18n\Translator\Loader;

use Zend\I18n\Exception\InvalidArgumentException;
use Zend\I18n\Translator\Loader\FileLoaderInterface;
use Zend\I18n\Translator\TextDomain;

/**
 * Handles the language loading and language file parsing
 *
 * @category VuFind
 * @package  Translator
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Sebastian Kehr <kehr@ub.uni-leipzig.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class ExtendedIni implements FileLoaderInterface
{
    const TRACE = '__TRACE__';
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
     * @var TextDomain[]
     */
    protected $loadedFiles = [];

    /**
     * Trace of files correspending to merged text domains.
     *
     * @var string[]
     */
    protected $trace = [];

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
     *                                           language files.
     * @param string|string[]   $fallbackLocales Fallback locale(s) to use for
     *                                           language strings missing from
     *                                           selected file.
     * @param ExtendedIniReader $reader          Helper for reading .ini files from
     *                                           disk.
     */
    public function __construct(
        $pathStack = [],
        $fallbackLocales = null,
        ExtendedIniReader $reader = null
    ) {
        $this->pathStack = $pathStack;
        $this->fallbackLocales = $fallbackLocales;
        if (!empty($this->fallbackLocales)
            && !is_array($this->fallbackLocales)
        ) {
            $this->fallbackLocales = [$this->fallbackLocales];
        }
        $this->reader = ($reader === null) ? new ExtendedIniReader() : $reader;
    }

    /**
     * Add additional directories to the path stack.
     *
     * @param array|string $pathStack Path stack addition(s).
     *
     * @return void
     */
    public function addToPathStack($pathStack)
    {
        $this->pathStack = array_merge($this->pathStack, (array)$pathStack);
    }

    /**
     * Load method defined by FileLoaderInterface.
     *
     * @param string $locale   Locale to read from language file
     * @param string $filename Relative base path for language file (used for
     *                         loading text domains; optional)
     *
     * @return TextDomain
     * @throws InvalidArgumentException
     */
    public function load($locale, $filename)
    {
        // Reset the loaded files list:
        $this->resetLoadedFiles();

        // Load base data:
        $data = $this->loadLanguageLocale($locale, $filename);

        // Load fallback data, if any:
        if (!empty($this->fallbackLocales)) {
            foreach ($this->fallbackLocales as $fallbackLocale) {
                $newData = $this->loadLanguageLocale(
                    $fallbackLocale,
                    $filename
                );
                $newData->merge($data);
                $data = $newData;
            }
        }
        $data[self::TRACE] = implode(":", $this->trace);
        return $data;
    }

    /**
     * Get the language file name for a language and domain
     *
     * @param string $locale Locale name
     * @param string $domain Text domain (if any)
     *
     * @return string
     */
    public function getLanguageFilename($locale, $domain)
    {
        return empty($domain)
            ? $locale . '.ini'
            : $domain . '/' . $locale . '.ini';
    }

    /**
     * Reset the loaded file list.
     *
     * @return void
     */
    protected function resetLoadedFiles()
    {
        $this->trace = [];
        $this->loadedFiles = [];
    }

    /**
     * Load the language file for a given locale and domain.
     *
     * @param string $locale Locale name
     * @param string $domain Text domain (if any)
     *
     * @return TextDomain
     */
    protected function loadLanguageLocale($locale, $domain)
    {
        $filename = $this->getLanguageFilename($locale, $domain);
        // Load the language file, and throw a fatal exception if it's missing
        // and we're not dealing with text domains. A missing base file is an
        // unexpected, fatal error; a missing domain-specific file is more likely
        // due to the possibility of incomplete translations.
        return $this->loadLanguageFile($filename, empty($domain));
    }

    /**
     * Search the path stack for language files and merge them together.
     *
     * @param string $filename    Name of file to search path stack for.
     * @param bool   $failOnError If true, throw an exception when file not found.
     *
     * @return TextDomain
     */
    protected function loadLanguageFile($filename, $failOnError = true)
    {
        $data = array_reduce(
            $this->pathStack,
            function ($data, $basePath) use ($filename) {
                if (file_exists($path = "$basePath/$filename")) {
                    $data = $data ?: new TextDomain();
                    return $data->merge($this->loadLanguageData($path));
                }
                return $data;
            }, false
        );

        if ($data === false && $failOnError) {
            throw new InvalidArgumentException(
                "Ini file '{$filename}' not found"
            );
        }

        return $data;
    }

    /**
     * Support method for loadLanguageFile: recursively load language data.
     *
     * @param string $path Absolute path to next file in load chain.
     *
     * @return TextDomain
     */
    protected function loadLanguageData($path)
    {
        if (!isset($this->loadedFiles[$path])) {
            $this->loadedFiles[$path] = $this->reader->getTextDomain($path);
        }

        $data = $this->loadedFiles[$path];

        if (!isset($data['@parent_ini'])) {
            $this->trace[] = $path;
            return $data;
        }

        $parentPath = realpath(dirname($path) . '/' . $data['@parent_ini']);
        $data->offsetUnset('@parent_ini');
        $result = $this->loadLanguageData($parentPath)->merge($data);
        $this->trace[] = $path;
        return $result;
    }
}
