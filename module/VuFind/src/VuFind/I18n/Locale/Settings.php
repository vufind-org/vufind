<?php
/**
 * VuFind I18n Initializer
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2018,
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

namespace VuFind\I18n\Locale;

use VuFind\I18n\Translator\TranslatorException;
use Zend\Config\Config;


/**
 * Handles I18n initialization.
 *
 * @category VuFind
 * @package  Translator
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Sebastian Kehr <kehr@ub.uni-leipzig.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class Settings
{
    /**
     * @var string
     */
    protected $defaultLocale;

    /**
     * @var string[]
     */
    protected $enabledLanguages;

    /**
     * @var string[]
     */
    protected $enabledLocales;

    /**
     * @var string[]
     */
    protected $fallbackLocales;

    /**
     * @var string[]
     */
    protected $mappedLocales;

    /**
     * @var string[]
     */
    protected $rightToLeftLocales;

    /**
     * @var string
     */
    protected $userLocale;

    public function __construct(Config $config)
    {
        $this->enabledLanguages = $config->Languages->toArray();
        $this->enabledLocales = array_keys($this->enabledLanguages);
        $this->defaultLocale = $this->parseDefaultLocale($config);
        $this->fallbackLocales = $this->parseFallbackLocales($config);
        $this->mappedLocales = $this->parseMappedLocales($config);
        $this->rightToLeftLocales = $this->parseRightToLeftLocales($config);
    }

    public function isRightToLeftLocale(string $locale): bool
    {
        return in_array($locale, $this->rightToLeftLocales);
    }

    public function getUserLocale(): string
    {
        return \Locale::getDefault();
    }

    /**
     * @return string
     */
    public function getDefaultLocale(): string
    {
        return $this->defaultLocale;
    }

    /**
     * @return string[]
     */
    public function getEnabledLanguages(): array
    {
        return $this->enabledLanguages;
    }

    /**
     * @return string[]
     */
    public function getEnabledLocales(): array
    {
        return $this->enabledLocales;
    }

    /**
     * @return string[]
     */
    public function getFallbackLocales(): array
    {
        return $this->fallbackLocales;
    }

    /**
     * @return string[]
     */
    public function getMappedLocales(): array
    {
        return $this->mappedLocales;
    }

    /**
     * @return string[]
     */
    public function getRightToLeftLocales(): array
    {
        return $this->rightToLeftLocales;
    }

    protected function parseDefaultLocale(Config $config): string
    {
        if (!in_array($locale = $config->Site->language, $this->enabledLocales)) {
            throw new TranslatorException("Configured default locale '$locale' not enabled!");
        }
        return $locale;
    }

    /**
     * Parses the configured language fallbacks.
     *
     * @param Config $config Configuration
     *
     * @return array
     */
    protected function parseFallbackLocales(Config $config)
    {
        $value = $config->LanguageSettings->fallbacks ?? '';
        preg_match_all("#([*a-z-]+):([a-z-]+)#", $value, $matches, PREG_SET_ORDER);

        $locales = iterator_to_array(
            (function () use ($matches) {
                foreach ($matches as list(, $locale, $fallbackLocale)) {
                    yield $locale => $fallbackLocale;
                }
            })()
        );

        if ($locale = $locales['*'] ?? null) {
            unset($locales['*']);

            foreach ($this->enabledLocales as $enabledLocale) {
                if ($enabledLocale !== $locale) {
                    $locales[$enabledLocale] = $locales[$enabledLocale] ?? $locale;
                }
            }
        }
        return $locales;
//        return array_intersect_key($locales, $this->enabledLanguages);

    }

    protected function parseMappedLocales(Config $config): array
    {
        return [];
    }

    protected function parseRightToLeftLocales(Config $config): array
    {
        $value = trim($config->LanguageSettings->rtl_langs ?? '', ',');
        return $value ? array_map('trim', explode(',', $value)) : [];
    }
}
