<?php

/**
 * VuFind Locale Settings
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2018,
 * Copyright (C) Leipzig University Library <info@ub.uni-leipzig.de> 2018.
 * Copyright (C) The National Library of Finland 2023.
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
 * @package  I18n\Locale
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Sebastian Kehr <kehr@ub.uni-leipzig.de>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\I18n\Locale;

use Laminas\Config\Config;

use function array_key_exists;
use function in_array;

/**
 * VuFind Locale Settings
 *
 * @category VuFind
 * @package  I18n\Locale
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Sebastian Kehr <kehr@ub.uni-leipzig.de>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class LocaleSettings
{
    /**
     * Default locale (code)
     *
     * @var string
     */
    protected $defaultLocale;

    /**
     * Associative (code => description) array of enabled locales.
     *
     * @var array
     */
    protected $enabledLocales;

    /**
     * Prioritized array of locales to use when strings are missing from the
     * primary language file.
     *
     * @var string[]
     */
    protected $fallbackLocales;

    /**
     * Array of locales that use right-to-left formatting.
     *
     * @var string[]
     */
    protected $rightToLeftLocales;

    /**
     * Array of locales that have been initialized.
     *
     * @var string[]
     */
    protected $initializedLocales = [];

    /**
     * Should we use auto-detect language based on browser settings?
     *
     * @var bool
     */
    protected $browserDetectLanguage;

    /**
     * Constructor
     *
     * @param Config $config Configuration object
     */
    public function __construct(Config $config)
    {
        $this->enabledLocales = $config->Languages ? $config->Languages->toArray()
            : [];
        $this->browserDetectLanguage
            = (bool)($config->Site->browserDetectLanguage ?? true);
        $this->defaultLocale = $this->parseDefaultLocale($config);
        $this->fallbackLocales = $this->parseFallbackLocales($config);
        $this->rightToLeftLocales = $this->parseRightToLeftLocales($config);
    }

    /**
     * Should we use auto-detect language based on browser settings?
     *
     * @return bool
     */
    public function browserLanguageDetectionEnabled(): bool
    {
        return $this->browserDetectLanguage;
    }

    /**
     * Identify whether a particular locale uses right-to-left layout.
     *
     * @param string $locale Locale to check
     *
     * @return bool
     */
    public function isRightToLeftLocale(string $locale): bool
    {
        return in_array($locale, $this->rightToLeftLocales);
    }

    /**
     * Get the current active locale.
     *
     * @return string
     */
    public function getUserLocale(): string
    {
        if (!class_exists(\Locale::class)) {
            error_log('Locale class is missing; please enable intl extension.');
            return $this->getDefaultLocale();
        }
        return \Locale::getDefault();
    }

    /**
     * Get default locale.
     *
     * @return string
     */
    public function getDefaultLocale(): string
    {
        return $this->defaultLocale;
    }

    /**
     * Get an associative (code => description) array of enabled locales.
     *
     * @return array
     */
    public function getEnabledLocales(): array
    {
        return $this->enabledLocales;
    }

    /**
     * Get a prioritized array of locales to use when strings are missing from the
     * primary language file.
     *
     * @return string[]
     */
    public function getFallbackLocales(): array
    {
        return $this->fallbackLocales;
    }

    /**
     * Get an array of locales that use right-to-left formatting.
     *
     * @return string[]
     */
    public function getRightToLeftLocales(): array
    {
        return $this->rightToLeftLocales;
    }

    /**
     * Extract and validate default locale from configuration.
     *
     * @param Config $config Configuration
     *
     * @return string
     * @throws \Exception
     */
    protected function parseDefaultLocale(Config $config): string
    {
        $locale = $config->Site->language ?? null;
        if (empty($locale)) {
            throw new \Exception('Default locale not configured!');
        }
        if (!array_key_exists($locale, $this->enabledLocales)) {
            throw new \Exception("Configured default locale '$locale' not enabled!");
        }
        return $locale;
    }

    /**
     * Parses the configured language fallbacks.
     *
     * @param Config $config Configuration
     *
     * @return string[]
     */
    protected function parseFallbackLocales(Config $config): array
    {
        $value = trim($config->Site->fallback_languages ?? '', ',');
        $languages = $value ? array_map('trim', explode(',', $value)) : [];
        return array_unique(
            [
                ...$languages,
                $config->Site->language,
                'en',
            ]
        );
    }

    /**
     * Parses the right-to-left language configuration.
     *
     * @param Config $config Configuration
     *
     * @return string[]
     */
    protected function parseRightToLeftLocales(Config $config): array
    {
        $value = trim($config->LanguageSettings->rtl_langs ?? '', ',');
        return $value ? array_map('trim', explode(',', $value)) : [];
    }

    /**
     * Mark a locale as initialized.
     *
     * @param string $locale Locale code
     *
     * @return void
     */
    public function markLocaleInitialized($locale)
    {
        $this->initializedLocales[] = $locale;
    }

    /**
     * Is the locale already initialized?
     *
     * @param string $locale Locale code
     *
     * @return bool
     */
    public function isLocaleInitialized($locale)
    {
        return in_array($locale, $this->initializedLocales);
    }
}
