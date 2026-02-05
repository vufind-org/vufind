<?php

/**
 * Functions for locale-specific processing in record drivers.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2026.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */

namespace VuFind\RecordDriver\Feature;

/**
 * Functions for locale-specific processing in record drivers.
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */
trait LocaleSupportTrait
{
    /**
     * Pick correct results from locale-specific results with fallback to all results.
     *
     * @param array        $localeResults Result(s) keyed by locale
     * @param array|string $allResults    All results
     *
     * @return array|string
     */
    protected function getLocaleSpecificResults(array $localeResults, array|string $allResults): array|string
    {
        if (null === $this->localeSettings) {
            return $allResults;
        }
        $userLocale = $this->localeSettings->getUserLocale();
        if (null !== ($results = $this->getBestLocaleMatch($userLocale, $localeResults))) {
            return $results;
        }
        // Check for matching language in locale-specific results:
        [$userLanguage] = explode('-', $userLocale);
        foreach ($localeResults as $locale => $results) {
            [$lang] = explode('-', $locale);
            if ($lang === $userLanguage) {
                return $results;
            }
        }
        // Check for match in default and fallback locales:
        $locales = [$this->localeSettings->getDefaultLocale(), ...$this->localeSettings->getFallbackLocales()];
        foreach ($locales as $locale) {
            if (null !== ($results = $this->getBestLocaleMatch($locale, $localeResults))) {
                return $results;
            }
        }
        // Could not find anything else, so return all:
        return $allResults;
    }

    /**
     * Pick best match for a locale from the results.
     *
     * @param string $locale        Locale
     * @param array  $localeResults Result(s) keyed by locale
     *
     * @return mixed
     */
    protected function getBestLocaleMatch(string $locale, array $localeResults): mixed
    {
        [$language] = explode('-', $locale);
        return $localeResults[$locale] ?? $localeResults[$language] ?? null;
    }
}
