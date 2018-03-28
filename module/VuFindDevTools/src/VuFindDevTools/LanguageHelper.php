<?php
/**
 * Language Helper for Development Tools Controller
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2015.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.    See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  DevTools
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/indexing:alphabetical_heading_browse Wiki
 */
namespace VuFindDevTools;

use VuFind\I18n\Translator\Loader\ExtendedIni;
use Zend\Config\Config;
use Zend\I18n\Translator\TextDomain;

/**
 * Language Helper for Development Tools Controller
 *
 * @category VuFind
 * @package  DevTools
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/indexing:alphabetical_heading_browse Wiki
 */
class LanguageHelper
{
    /**
     * Language loader
     *
     * @var ExtendedIni
     */
    protected $loader;

    /**
     * Configuration
     *
     * @var Config
     */
    protected $config;

    /**
     * Constructor
     *
     * @param ExtendedIni $loader Language loader
     * @param Config      $config Config
     */
    public function __construct(ExtendedIni $loader, Config $config)
    {
        $this->loader = $loader;
        $this->config = $config;
    }

    /**
     * Get a list of help files in the specified language.
     *
     * @param string $language Language to check.
     *
     * @return array
     */
    protected function getHelpFiles($language)
    {
        $dir = APPLICATION_PATH
            . '/themes/root/templates/HelpTranslations/' . $language;
        if (!file_exists($dir) || !is_dir($dir)) {
            return [];
        }
        $handle = opendir($dir);
        $files = [];
        while ($file = readdir($handle)) {
            if (substr($file, -6) == '.phtml') {
                $files[] = $file;
            }
        }
        closedir($handle);
        return $files;
    }

    /**
     * Get a list of languages supported by VuFind:
     *
     * @return array
     */
    protected function getLanguages()
    {
        $langs = [];
        $dir = opendir(APPLICATION_PATH . '/languages');
        while ($file = readdir($dir)) {
            if (substr($file, -4) == '.ini') {
                $lang = current(explode('.', $file));
                if ('native' != $lang) {
                    $langs[] = $lang;
                }
            }
        }
        closedir($dir);
        return $langs;
    }

    /**
     * Find strings that are absent from a language file.
     *
     * @param TextDomain $lang1 Left side of comparison
     * @param TextDomain $lang2 Right side of comparison
     *
     * @return array
     */
    protected function findMissingLanguageStrings($lang1, $lang2)
    {
        // Find strings missing from language 2:
        return array_values(
            array_diff(array_keys((array)$lang1), array_keys((array)$lang2))
        );
    }

    /**
     * Compare two languages and return an array of details about how they differ.
     *
     * @param TextDomain $lang1 Left side of comparison
     * @param TextDomain $lang2 Right side of comparison
     *
     * @return array
     */
    public function compareLanguages($lang1, $lang2)
    {
        return [
            'notInL1' => $this->findMissingLanguageStrings($lang2, $lang1),
            'notInL2' => $this->findMissingLanguageStrings($lang1, $lang2),
            'l1Percent' => number_format(count($lang1) / count($lang2) * 100, 2),
            'l2Percent' => number_format(count($lang2) / count($lang1) * 100, 2),
        ];
    }

    /**
     * Get English name of language
     *
     * @param string $lang Language code
     *
     * @return string
     */
    public function getLangName($lang)
    {
        if (isset($this->config->Languages->$lang)) {
            return $this->config->Languages->$lang;
        }
        switch ($lang) {
        case 'en-gb':
            return 'British English';
        case 'pt-br':
            return 'Brazilian Portuguese';
        default:
            return $lang;
        }
    }

    /**
     * Get text domains for a language.
     *
     * @return array
     */
    protected function getTextDomains()
    {
        static $domains = false;
        if (!$domains) {
            $base = APPLICATION_PATH . '/languages';
            $dir = opendir($base);
            $domains = [];
            while ($current = readdir($dir)) {
                if ($current != '.' && $current != '..'
                    && is_dir("$base/$current")
                ) {
                    $domains[] = $current;
                }
            }
            closedir($dir);
        }
        return $domains;
    }

    /**
     * Load a language, including text domains.
     *
     * @param string $lang Language to load
     *
     * @return array
     */
    protected function loadLanguage($lang)
    {
        $base = $this->loader->load($lang, null);
        foreach ($this->getTextDomains() as $domain) {
            $current = $this->loader->load($lang, $domain);
            foreach ($current as $k => $v) {
                if ($k != '@parent_ini') {
                    $base["$domain::$k"] = $v;
                }
            }
        }
        if (isset($base['@parent_ini'])) {
            // don't count macros in comparison:
            unset($base['@parent_ini']);
        }
        return $base;
    }

    /**
     * Return details on how $langCode differs from $main.
     *
     * @param array  $main     The main language (full details)
     * @param string $langCode The code of a language to compare against $main
     *
     * @return array
     */
    protected function getLanguageDetails($main, $langCode)
    {
        $lang = $this->loadLanguage($langCode);
        $details = $this->compareLanguages($main, $lang);
        $details['object'] = $lang;
        $details['name'] = $this->getLangName($langCode);
        $details['helpFiles'] = $this->getHelpFiles($langCode);
        return $details;
    }

    /**
     * Return details on how all languages differ from $main.
     *
     * @param array $main The main language (full details)
     *
     * @return array
     */
    protected function getAllLanguageDetails($main)
    {
        $details = [];
        $allLangs = $this->getLanguages();
        sort($allLangs);
        foreach ($allLangs as $langCode) {
            $details[$langCode] = $this->getLanguageDetails($main, $langCode);
        }
        return $details;
    }

    /**
     * Return language comparison information, using $mainLanguage as the
     * baseline.
     *
     * @param string $mainLanguage Language code
     *
     * @return array
     */
    public function getAllDetails($mainLanguage)
    {
        $main = $this->loadLanguage($mainLanguage);
        return [
            'details' => $this->getAllLanguageDetails($main),
            'mainCode' => $mainLanguage,
            'mainName' => $this->getLangName($mainLanguage),
            'main' => $main,
        ];
    }
}
