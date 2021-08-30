<?php
/**
 * Logic for initializing a language within a translator used by VuFind.
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2019.
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
namespace VuFind\I18n\Translator;

use Laminas\I18n\Translator\TranslatorInterface;
use VuFind\I18n\Locale\LocaleSettings;

/**
 * Logic for initializing a language within a translator used by VuFind.
 *
 * @category VuFind
 * @package  Translator
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
trait LanguageInitializerTrait
{
    /**
     * Look up all text domains.
     *
     * @return array
     */
    protected function getTextDomains()
    {
        $base = APPLICATION_PATH;
        $local = LOCAL_OVERRIDE_DIR;
        $languagePathParts = ["$base/languages"];
        if (!empty($local)) {
            $languagePathParts[] = "$local/languages";
        }
        $languagePathParts[] = "$base/themes/*/languages";

        $domains = [];
        foreach ($languagePathParts as $current) {
            $places = glob($current . '/*', GLOB_ONLYDIR | GLOB_NOSORT);
            $domains = array_merge($domains, array_map('basename', $places));
        }

        return array_unique($domains);
    }

    /**
     * Configure a translator to support the requested language.
     *
     * @param TranslatorInterface $translator Translator
     * @param LocaleSettings      $settings   Locale settings
     * @param string              $language   Language to set up
     *
     * @return void
     */
    protected function addLanguageToTranslator(
        TranslatorInterface $translator,
        LocaleSettings $settings,
        string $language
    ): void {
        // Don't double-initialize languages:
        if ($settings->isLocaleInitialized($language)) {
            return;
        }
        $settings->markLocaleInitialized($language);

        // If we got this far, we need to set everything up:
        $translator->addTranslationFile('ExtendedIni', null, 'default', $language);
        foreach ($this->getTextDomains() as $domain) {
            // Set up text domains using the domain name as the filename;
            // this will help the ExtendedIni loader dynamically locate
            // the appropriate files.
            $translator->addTranslationFile(
                'ExtendedIni',
                $domain,
                $domain,
                $language
            );
        }
    }
}
