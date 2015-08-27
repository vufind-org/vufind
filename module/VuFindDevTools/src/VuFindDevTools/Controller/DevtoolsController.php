<?php
/**
 * Development Tools Controller
 *
 * PHP Version 5
 *
 * Copyright (C) Villanova University 2011.
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA    02111-1307    USA
 *
 * @category VuFind2
 * @package  Controller
 * @author   Mark Triggs <vufind-tech@lists.sourceforge.net>
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/alphabetical_heading_browse Wiki
 */
namespace VuFindDevTools\Controller;
use VuFind\I18n\Translator\Loader\ExtendedIni;
use Zend\I18n\Translator\TextDomain;

/**
 * Development Tools Controller
 *
 * @category VuFind2
 * @package  Controller
 * @author   Mark Triggs <vufind-tech@lists.sourceforge.net>
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/alphabetical_heading_browse Wiki
 */
class DevtoolsController extends \VuFind\Controller\AbstractBase
{
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
    protected function compareLanguages($lang1, $lang2)
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
        $config = $this->getConfig();
        if (isset($config->Languages->$lang)) {
            return $config->Languages->$lang;
        }
        switch($lang) {
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
            $base = APPLICATION_PATH  . '/languages';
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
     * @param ExtendedIni $loader Language loader
     * @param string      $lang   Language to load
     *
     * @return array
     */
    protected function loadLanguage(ExtendedIni $loader, $lang)
    {
        $base = $loader->load($lang, null);
        foreach ($this->getTextDomains() as $domain) {
            $current = $loader->load($lang, $domain);
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
     * Language action
     *
     * @return array
     */
    public function languageAction()
    {
        // Test languages with no local overrides and no fallback:
        $loader = new ExtendedIni([APPLICATION_PATH  . '/languages']);
        $mainLanguage = $this->params()->fromQuery('main', 'en');
        $main = $this->loadLanguage($loader, $mainLanguage);

        $details = [];
        $allLangs = $this->getLanguages();
        sort($allLangs);
        foreach ($allLangs as $langCode) {
            $lang = $this->loadLanguage($loader, $langCode);
            $details[$langCode] = $this->compareLanguages($main, $lang);
            $details[$langCode]['object'] = $lang;
            $details[$langCode]['name'] = $this->getLangName($langCode);
            $details[$langCode]['helpFiles'] = $this->getHelpFiles($langCode);
        }

        return [
            'details' => $details,
            'mainCode' => $mainLanguage,
            'mainName' => $this->getLangName($mainLanguage),
            'main' => $main,
        ];
    }
}