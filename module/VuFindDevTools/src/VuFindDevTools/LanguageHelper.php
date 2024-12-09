<?php

/**
 * Language Helper for Development Tools Controller
 *
 * PHP version 8
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

use Laminas\I18n\Translator\TextDomain;
use VuFind\I18n\Translator\Loader\ExtendedIni;

use function count;
use function in_array;

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
     * Configured languages (code => description)
     *
     * @var string[]
     */
    protected $configuredLanguages;

    /**
     * Constructor
     *
     * @param ExtendedIni $loader Language loader
     * @param array       $langs  Configured languages (code => description)
     */
    public function __construct(ExtendedIni $loader, array $langs = [])
    {
        $this->loader = $loader;
        $this->configuredLanguages = $langs;
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
            if (str_ends_with($file, '.phtml')) {
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
    protected function getLanguages(): array
    {
        $langs = [];
        $dir = opendir(APPLICATION_PATH . '/languages');
        while ($file = readdir($dir)) {
            if (str_ends_with($file, '.ini')) {
                $lang = current(explode('.', $file));
                if ('native' != $lang && 'aliases' != $lang) {
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
    protected function findMissingLanguageStrings(TextDomain $lang1, TextDomain $lang2): array
    {
        // Find strings missing from language 2:
        return array_values(
            array_diff(array_keys((array)$lang1), array_keys((array)$lang2))
        );
    }

    /**
     * Compare two languages and return an array of details about how they differ.
     *
     * @param TextDomain $lang1          Left side of comparison
     * @param TextDomain $lang2          Right side of comparison
     * @param TextDomain $lang1NoAliases Left side of comparison (with aliases disabled)
     * @param TextDomain $lang2NoAliases Right side of comparison (with aliases disabled)
     *
     * @return array
     */
    public function compareLanguages(
        TextDomain $lang1,
        TextDomain $lang2,
        TextDomain $lang1NoAliases,
        TextDomain $lang2NoAliases
    ): array {
        // We don't want to double-count aliased terms, nor do we want to count alias
        // overrides as "extra lines". Thus, we find meaningful differences by subtracting
        // the aliased data of one language from the non-aliased data of the other.
        return [
            'notInL1' => $this->findMissingLanguageStrings($lang2NoAliases, $lang1),
            'notInL2' => $this->findMissingLanguageStrings($lang1NoAliases, $lang2),
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
        if (isset($this->configuredLanguages[$lang])) {
            return $this->configuredLanguages[$lang];
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
     * @param bool $includeOptional Include optional translations (e.g. DDC23)
     *
     * @return array
     */
    protected function getTextDomains($includeOptional)
    {
        static $domains = false;
        if (!$domains) {
            $filter = $includeOptional
                ? []
                : ['CallNumberFirst', 'CreatorRoles', 'DDC23', 'ISO639-3'];
            $base = APPLICATION_PATH . '/languages';
            $dir = opendir($base);
            $domains = [];
            while ($current = readdir($dir)) {
                if (
                    $current != '.' && $current != '..'
                    && is_dir("$base/$current")
                    && !in_array($current, $filter)
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
     * @param string $lang            Language to load
     * @param bool   $includeOptional Include optional translations (e.g. DDC23)
     * @param bool   $includeAliases  Include alias details
     *
     * @return array
     */
    protected function loadLanguage($lang, $includeOptional, $includeAliases = true)
    {
        $includeAliases ? $this->loader->enableAliases() : $this->loader->disableAliases();
        $base = $this->loader->load($lang, null);
        foreach ($this->getTextDomains($includeOptional) as $domain) {
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
     * Find duplicated values within the language.
     *
     * @param TextDomain $lang Language to analyze.
     *
     * @return array
     */
    protected function findDuplicatedValues(TextDomain $lang): array
    {
        $index = [];
        foreach ($lang as $key => $val) {
            $index[$val] = array_merge($index[$val] ?? [], [$key]);
        }
        $callback = function ($set) {
            return count($set) > 1;
        };
        return array_filter($index, $callback);
    }

    /**
     * Return details on how $langCode differs from $main.
     *
     * @param TextDomain $main            The main language (full details)
     * @param TextDomain $mainNoAliases   The main language (with aliases disabled)
     * @param string     $langCode        The code of a language to compare against $main
     * @param bool       $includeOptional Include optional translations (e.g. DDC23)
     *
     * @return array
     */
    protected function getLanguageDetails(
        TextDomain $main,
        TextDomain $mainNoAliases,
        string $langCode,
        bool $includeOptional
    ): array {
        $lang = $this->loadLanguage($langCode, $includeOptional);
        $langNoAliases = $this->loadLanguage($langCode, $includeOptional, false);
        $details = $this->compareLanguages($main, $lang, $mainNoAliases, $langNoAliases);
        $details['dupes'] = $this->findDuplicatedValues($langNoAliases);
        $details['object'] = $lang;
        $details['name'] = $this->getLangName($langCode);
        $details['helpFiles'] = $this->getHelpFiles($langCode);
        return $details;
    }

    /**
     * Return details on how all languages differ from $main.
     *
     * @param TextDomain $main            The main language (full details)
     * @param TextDomain $mainNoAliases   The main language (with aliases disabled)
     * @param bool       $includeOptional Include optional translations (e.g. DDC23)
     *
     * @return array
     */
    protected function getAllLanguageDetails(TextDomain $main, TextDomain $mainNoAliases, bool $includeOptional): array
    {
        $details = [];
        $allLangs = $this->getLanguages();
        sort($allLangs);
        foreach ($allLangs as $langCode) {
            $details[$langCode] = $this
                ->getLanguageDetails($main, $mainNoAliases, $langCode, $includeOptional);
        }
        return $details;
    }

    /**
     * Create summary data for use in the tabular display.
     *
     * @param array $details Full details from getAllLanguageDetails()
     *
     * @return array
     */
    protected function summarizeData($details)
    {
        $data = [];
        foreach ($details as $langCode => $diffs) {
            if ($diffs['l2Percent'] > 90) {
                $progressLevel = 'info';
            } elseif ($diffs['l2Percent'] > 70) {
                $progressLevel = 'warning';
            } else {
                $progressLevel = 'danger';
            }
            $data[] = [
                'lang' => $langCode,
                'name' => $diffs['name'],
                'dupes' => $diffs['dupes'],
                'langtitle' => $langCode . (($langCode != $diffs['name'])
                    ? ' (' . $diffs['name'] . ')' : ''),
                'missing' => count($diffs['notInL2']),
                'extra' => count($diffs['notInL1']),
                'percent' => $diffs['l2Percent'],
                'countfiles' => count($diffs['helpFiles']),
                'files' => $diffs['helpFiles'],
                'progresslevel' => $progressLevel,
            ];
        }
        return $data;
    }

    /**
     * Return language comparison information, using $mainLanguage as the
     * baseline.
     *
     * @param string $mainLanguage    Language code
     * @param bool   $includeOptional Include optional translations (e.g. DDC23)
     *
     * @return array
     */
    public function getAllDetails($mainLanguage, $includeOptional = true)
    {
        $main = $this->loadLanguage($mainLanguage, $includeOptional);
        $mainNoAliases = $this->loadLanguage($mainLanguage, $includeOptional, false);
        $details = $this->getAllLanguageDetails($main, $mainNoAliases, $includeOptional);
        $dirHelpParts = [
            APPLICATION_PATH, 'themes', 'root', 'templates', 'HelpTranslations',
        ];
        $dirLangParts = [APPLICATION_PATH, 'languages'];
        return compact('details', 'main', 'includeOptional') + [
            'dirHelp' => implode(DIRECTORY_SEPARATOR, $dirHelpParts)
                . DIRECTORY_SEPARATOR,
            'dirLang' => implode(DIRECTORY_SEPARATOR, $dirLangParts)
                . DIRECTORY_SEPARATOR,
            'mainCode' => $mainLanguage,
            'mainName' => $this->getLangName($mainLanguage),
            'summaryData' => $this->summarizeData($details),
        ];
    }
}
