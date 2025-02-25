<?php

/**
 * VuFind Translate Adapter ExtendedIni
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
 * @package  Translator
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\I18n\Translator\Loader;

use Laminas\I18n\Exception\InvalidArgumentException;
use Laminas\I18n\Exception\RuntimeException;
use Laminas\I18n\Translator\Loader\FileLoaderInterface;
use Laminas\I18n\Translator\TextDomain;

use function count;
use function dirname;
use function in_array;

/**
 * Handles the language loading and language file parsing
 *
 * @category VuFind
 * @package  Translator
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
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
    protected $loadedFiles = [];

    /**
     * Helper for reading .ini files from disk.
     *
     * @var ExtendedIniReader
     */
    protected $reader;

    /**
     * Is aliasing enabled?
     *
     * @var bool
     */
    protected $useAliases = true;

    /**
     * Map of translation aliases.
     *
     * @var array
     */
    protected $aliases = [];

    /**
     * List of loaded alias configuration files.
     *
     * @var array
     */
    protected $loadedAliasFiles = [];

    /**
     * Loaded TextDomains used for resolving aliases.
     *
     * @var array
     */
    protected $aliasDomains = [];

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
    public function __construct(
        $pathStack = [],
        $fallbackLocales = null,
        ExtendedIniReader $reader = null
    ) {
        $this->pathStack = $pathStack;
        $this->fallbackLocales = $fallbackLocales ? (array)$fallbackLocales : [];
        $this->reader = $reader ?? new ExtendedIniReader();
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
     * loading text domains; optional)
     *
     * @return TextDomain
     * @throws InvalidArgumentException
     */
    public function load($locale, $filename)
    {
        if ($locale == 'debug') {
            return null;
        }

        // Reset loaded aliases:
        $this->resetAliases();

        // Reset the loaded files list:
        $this->resetLoadedFiles();

        // Identify the current TextDomain name:
        $currentDomain = empty($filename) ? 'default' : $filename;

        // Load base data:
        $data = $this->loadLanguageLocale($locale, $filename, $this->useAliases);

        // Set up a reference to the current domain for use in alias processing:
        $this->aliasDomains[$currentDomain] = $data;

        // Apply aliases:
        if ($this->useAliases) {
            $this->applyAliases($data, $locale, $currentDomain);
        }

        // Load fallback data, if any:
        foreach ($this->fallbackLocales as $fallbackLocale) {
            $newData = $this->loadLanguageLocale($fallbackLocale, $filename);
            $newData->merge($data);
            $data = $newData;
        }

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
        return (empty($domain) || $domain === 'default')
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
        $this->loadedFiles = [];
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
     * Load the language file for a given locale and domain.
     *
     * @param string $locale         Locale name
     * @param string $domain         Text domain (if any)
     * @param bool   $processAliases Should we process alias data?
     *
     * @return TextDomain
     */
    protected function loadLanguageLocale($locale, $domain, $processAliases = false)
    {
        $filename = $this->getLanguageFilename($locale, $domain);
        // Load the language file, and throw a fatal exception if it's missing
        // and we're not dealing with text domains. A missing base file is an
        // unexpected, fatal error; a missing domain-specific file is more likely
        // due to the possibility of incomplete translations.
        return $this->loadLanguageFile(
            $filename,
            empty($domain),
            $processAliases ? (empty($domain) ? 'default' : $domain) : null
        );
    }

    /**
     * Resolve a single alias (or return null if it cannot be resolved)
     *
     * @param array  $alias         The [domain, key] or [key] alias array
     * @param string $defaultDomain The domain to use if $alias does not specify one
     * @param string $locale        The locale currently being loaded
     * @param array  $breadcrumbs   Previously-resolved aliases (to prevent infinite loops)
     *
     * @return ?string
     * @throws \Exception
     */
    protected function resolveAlias(
        array $alias,
        string $defaultDomain,
        string $locale,
        array $breadcrumbs = []
    ): ?string {
        // If the current alias target does not include a TextDomain part, assume it refers
        // to the current active TextDomain:
        if (count($alias) < 2) {
            array_unshift($alias, $defaultDomain);
        }
        [$domain, $key] = $alias;

        // If the alias references another TextDomain, we need to load that now.
        if (!isset($this->aliasDomains[$domain])) {
            $this->aliasDomains[$domain] = $this->loadLanguageLocale($locale, $domain, true);
        }
        if ($this->aliasDomains[$domain]->offsetExists($key)) {
            return $this->aliasDomains[$domain]->offsetGet($key);
        } elseif (isset($this->aliases[$domain][$key])) {
            // Circular alias infinite loop prevention:
            $breadcrumbKey = "$domain::$key";
            if (in_array($breadcrumbKey, $breadcrumbs)) {
                throw new \Exception("Circular alias detected resolving $breadcrumbKey");
            }
            $breadcrumbs[] = $breadcrumbKey;
            return $this->resolveAlias($this->aliases[$domain][$key], $domain, $locale, $breadcrumbs);
        }
        return null;
    }

    /**
     * Apply loaded aliases to the provided TextDomain.
     *
     * @param TextDomain $data          Text domain to update
     * @param string     $currentLocale The locale currently being loaded
     * @param string     $currentDomain The name of the text domain currently being loaded
     *
     * @return void
     */
    protected function applyAliases(TextDomain $data, string $currentLocale, string $currentDomain): void
    {
        foreach ($this->aliases[$currentDomain] ?? [] as $alias => $target) {
            // Do not overwrite existing values with alias, and do not create aliases
            // when target values are missing.
            if (
                !$data->offsetExists($alias)
                && $aliasValue = $this->resolveAlias($target, $currentDomain, $currentLocale)
            ) {
                $data->offsetSet($alias, $aliasValue);
            }
        }
    }

    /**
     * Reset all collected alias data.
     *
     * @return void
     */
    protected function resetAliases(): void
    {
        $this->aliases = $this->loadedAliasFiles = [];
    }

    /**
     * Disable aliasing functionality.
     *
     * @return void
     */
    public function disableAliases(): void
    {
        $this->useAliases = false;
    }

    /**
     * Enable aliasing functionality.
     *
     * @return void
     */
    public function enableAliases(): void
    {
        $this->useAliases = true;
    }

    /**
     * Expand an alias string into an array (either [textdomain, key] or just [key]).
     *
     * @param string $alias String to parse
     *
     * @return string[]
     */
    protected function normalizeAlias(string $alias): array
    {
        return explode('::', $alias);
    }

    /**
     * Load an alias configuration (if not already loaded) and mark it loaded.
     *
     * @param string $aliasDomain Domain for which aliases are being loaded
     * @param string $filename    Filename to load
     *
     * @return void
     */
    protected function markAndLoadAliases(string $aliasDomain, string $filename): void
    {
        $loadedFiles = $this->loadedAliasFiles[$aliasDomain] ?? [];
        if (!in_array($filename, $loadedFiles)) {
            $this->loadedAliasFiles[$aliasDomain] = array_merge($loadedFiles, [$filename]);
            if (file_exists($filename)) {
                // Parse and normalize the alias configuration:
                $newAliases = array_map(
                    [$this, 'normalizeAlias'],
                    $this->reader->getTextDomain($filename)->getArrayCopy()
                );
                // Merge with pre-existing aliases:
                $this->aliases[$aliasDomain] = array_merge($this->aliases[$aliasDomain] ?? [], $newAliases);
            }
        }
    }

    /**
     * Search the path stack for language files and merge them together.
     *
     * @param string  $filename    Name of file to search path stack for.
     * @param bool    $failOnError If true, throw an exception when file not found.
     * @param ?string $aliasDomain Name of TextDomain for which we should process aliases
     * (or null to skip alias processing)
     *
     * @throws RuntimeException
     * @throws InvalidArgumentException
     * @return TextDomain
     */
    protected function loadLanguageFile($filename, $failOnError, ?string $aliasDomain)
    {
        // Don't load a file that has already been loaded:
        if ($this->checkAndMarkLoadedFile($filename)) {
            return new TextDomain();
        }

        $data = false;
        foreach ($this->pathStack as $path) {
            $fileOnPath = $path . '/' . $filename;
            if (file_exists($fileOnPath)) {
                // Load current file with parent data, if necessary:
                $current = $this->loadParentData(
                    $this->reader->getTextDomain($fileOnPath),
                    $aliasDomain
                );
                if ($data === false) {
                    $data = $current;
                } else {
                    $data->merge($current);
                }
            }
            if ($aliasDomain) {
                $this->markAndLoadAliases($aliasDomain, dirname($fileOnPath) . '/aliases.ini');
            }
        }
        if ($data === false) {
            // Should we throw an exception? If not, return an empty result:
            if ($failOnError) {
                throw new InvalidArgumentException(
                    "Ini file '{$filename}' not found"
                );
            }
            return new TextDomain();
        }

        return $data;
    }

    /**
     * Support method for loadLanguageFile: retrieve parent data.
     *
     * @param TextDomain $data        TextDomain to populate with parent information.
     * @param ?string    $aliasDomain Name of TextDomain for which we should process aliases
     * (or null to skip alias processing)
     *
     * @return TextDomain
     */
    protected function loadParentData($data, ?string $aliasDomain)
    {
        if (!isset($data['@parent_ini'])) {
            return $data;
        }
        $parent = $this->loadLanguageFile($data['@parent_ini'], true, $aliasDomain);
        $parent->merge($data);
        return $parent;
    }
}
