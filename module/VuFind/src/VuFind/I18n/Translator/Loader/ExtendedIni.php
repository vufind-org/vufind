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

use Zend\I18n\Translator\Loader\RemoteLoaderInterface;
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
class ExtendedIni implements RemoteLoaderInterface
{
    const EXTEND = '@extend';

    const FALLBACK = '@fallback';

    const INFO = '@info';

    /**
     * List of absolute paths to directories where language files reside
     * ordered by descending priority.
     *
     * @var string[]
     */
    protected $dirs = [];

    /**
     * Loaded data indexed by absolute path to language files.
     *
     * @var array[]
     */
    protected $dict = [];

    /**
     * List of absolute paths to merged language files.
     *
     * @var string[]
     */
    protected $list = [];

    /**
     * Fallback language defined via directive.
     *
     * @var null|string
     */
    protected $fallback = null;

    /**
     * Fallback languages defined via configuration.
     *
     * @var string[]
     */
    protected $fallbacks = [];

    /**
     * Reader instance for parsing INI files.
     *
     * @var ExtendedIniReader
     */
    protected $reader;

    /**
     * ExtendedIni constructor.
     */
    public function __construct()
    {
        $this->reader = new ExtendedIniReader();
    }

    /**
     * Sets the list of base directories.
     *
     * @param string[] $dirs {@see $dirs}
     *
     * @return void
     */
    public function setDirs(array $dirs)
    {
        $this->dirs = $dirs;
    }

    /**
     * Sets the map of fallback languages.
     *
     * @param string[] $fallbacks {@see $fallbacks}
     *
     * @return void
     */
    public function setFallbacks(array $fallbacks)
    {
        $this->fallbacks = $fallbacks;
    }

    /**
     * Load method defined by RemoteLoaderInterface.
     *
     * @param string $locale     Locale
     * @param string $textDomain Text domain
     *
     * @return TextDomain
     */
    public function load($locale, $textDomain = 'default')
    {
        $this->dict = $this->list = [];
        $this->loadLocale($locale, $textDomain, $textDomain === 'default');

        return array_reduce(
            array_reverse($this->list),
            function (TextDomain $data, $filename) {
                return $data->merge($this->dict[$filename]['data']);
            }, new TextDomain(
                [
                    self::INFO => [
                        'dirs' => $this->dirs,
                        'list' => $this->list,
                        'dict' => $this->dict
                    ]
                ]
            )
        );
    }

    /**
     * Loads translations for a given locale, text domain.
     *
     * @param string $locale     Locale.
     * @param string $textDomain Text domain.
     * @param bool   $required   Whether to throw an exception in case locale,
     *                           text domain cannot be resolved to some file.
     *
     * @return void
     */
    protected function loadLocale($locale, $textDomain, $required = false)
    {
        $exists = false;
        $basename = "$locale.ini";
        $this->fallback = null;
        $relPath = $textDomain === 'default'
            ? $basename : "$textDomain/$basename";

        foreach ($this->dirs as $dir) {
            $this->loadFile($absPath = "$dir/$relPath");
            $exists |= $this->dict[$absPath]['exists'];
        }

        if ($required && !$exists) {
            throw new \RuntimeException("File '$relPath' not found.");
        }

        $catchAll = $this->fallbacks['*'] ?? null;
        $fallback = $this->fallback ?? $this->fallbacks[$locale]
            ?? ($locale === $catchAll ? null : $catchAll);

        if ($fallback) {
            $this->loadLocale($fallback, $textDomain);
        }
    }

    /**
     * Loads a file.
     *
     * @param string $path Absolute path to file.
     *
     * @return void
     */
    protected function loadFile($path)
    {
        if (in_array($path, $this->list)) {
            throw new \RuntimeException(
                "Circular chain of loaded language files."
            );
        }

        $this->list[] = $path;

        if (!isset($this->dict[$path])) {
            $this->readFile($path);
        }

        if ($extend = $this->dict[$path]['extend']) {
            $this->loadFile($extend);
        }
    }

    /**
     * Reads a language file.
     *
     * @param string $path Absolute path to file.
     *
     * @return void
     */
    protected function readFile($path)
    {
        $data = ($exists = is_file($path))
            ? $this->reader->getTextDomain($path) : new TextDomain();

        if ($fallback = $data[self::FALLBACK] ?? null) {
            $this->fallback = $this->fallback ?? $fallback;
            unset($data[self::FALLBACK]);
        }

        if ($extend = $data[self::EXTEND] ?? null) {
            $dir = $extend[0] === '/' ? APPLICATION_PATH : dirname($path);
            $extend = realpath("$dir/$extend");
            unset($data[self::EXTEND]);
        }

        $this->dict[$path] = compact(
            'data', 'exists', 'extend', 'path', 'fallback'
        );
    }
}
