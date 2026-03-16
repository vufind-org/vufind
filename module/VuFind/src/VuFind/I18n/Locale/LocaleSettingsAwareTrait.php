<?php

/**
 * Default implementation of LocaleSettingsAwareTrait.
 *
 * PHP version 8
 *
 * Copyright (C) Hebis Verbundzentrale 2026.
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
 * @package  I18n\Locale
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\I18n\Locale;

use function in_array;

/**
 * Default implementation of LocaleSettingsAwareTrait.
 *
 * @category VuFind
 * @package  I18n\Locale
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
trait LocaleSettingsAwareTrait
{
    /**
     * Locale settings.
     *
     * @var LocaleSettings
     */
    protected LocaleSettings $localeSettings;

    /**
     * Set locale settings
     *
     * @param LocaleSettings $localeSettings Locale Settings
     *
     * @return LocaleSettingsAwareInterface
     */
    public function setLocaleSettings(LocaleSettings $localeSettings): LocaleSettingsAwareInterface
    {
        $this->localeSettings = $localeSettings;
        return $this;
    }

    /**
     * Get translation from array based on the current selected language.
     *
     * @param array $translations      Associative array of translations (key = locale, value = translation)
     * @param bool  $ignoreMissingKeys If translation should fall back to other locale when user locale does not
     * exist in keys.
     *
     * @return ?string
     */
    public function getActiveTranslation(array $translations, bool $ignoreMissingKeys = false): ?string
    {
        $currentLocale = $this->localeSettings->getUserLocale();
        // only get a translation if user locale exists in the keys
        if ($ignoreMissingKeys && !in_array($currentLocale, array_keys($translations))) {
            return null;
        }
        $translations = array_filter($translations);
        $translation = $translations[$currentLocale] ?? null;
        if ($translation !== null) {
            return $translation;
        }
        foreach ($this->localeSettings->getFallbackLocales() as $fallbackLanguage) {
            $translation = $translations[$fallbackLanguage] ?? null;
            if ($translation !== null) {
                return $translation;
            }
        }
        return array_values($translations)[0] ?? null;
    }
}
