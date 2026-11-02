<?php

/**
 * Trait implementing ConfigSettingPropertiesInterface.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2025.
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
 * @package  Config
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Config\Feature;

use Exception;
use VuFind\Exception\BadConfig;

use function array_key_exists;
use function in_array;
use function is_array;

/**
 * Trait implementing ConfigSettingPropertiesInterface.
 *
 * @category VuFind
 * @package  Config
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
trait ConfigSettingPropertiesTrait
{
    /**
     * Required and conditionally required settings.
     *
     * @var array<string>
     */
    protected array $requiredSettings = [self::DEFAULT_CONTEXT => []];

    /**
     * Localizable settings.
     *
     * @var array<string>
     */
    protected array $localizableSettings = [self::DEFAULT_CONTEXT => []];

    /**
     * Return required and conditionally required settings in the specified
     * context.
     *
     * The setting keys returned by this method need to be individually checked
     * using ConfigSettingPropertiesInterface::isRequiredSetting() to determine
     * if they are actually required when evaluated in their context.
     *
     * @param string $contextKey Key identifying the context (optional)
     *
     * @return array<string> Required and conditionally required settings
     * (setting keys) in the specified context.
     */
    public function getRequiredSettings(
        string $contextKey = self::DEFAULT_CONTEXT
    ): array {
        if (!array_key_exists($contextKey, $this->requiredSettings)) {
            throw new Exception('Unknown context key: ' . $contextKey);
        }
        return $this->requiredSettings[$contextKey];
    }

    /**
     * Is the setting required?
     *
     * The optional context and context key parameters are used to evaluate if a
     * conditionally required setting is required. If context is omitted returns
     * true for both required and conditionally required settings.
     *
     * @param string               $setting    Setting key
     * @param array<string, mixed> $context    Setting keys and values to be used in evaluation (optional)
     * @param string               $contextKey Key identifying the context (optional)
     *
     * @return bool
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function isRequiredSetting(
        string $setting,
        array $context = [],
        string $contextKey = self::DEFAULT_CONTEXT
    ): bool {
        // Default implementation does not handle conditional requirements.
        return in_array($setting, $this->getRequiredSettings($contextKey));
    }

    /**
     * Return settings that may be localized.
     *
     * Localizable settings have localized values in configuration.
     *
     * @param string $contextKey Key identifying the context (optional)
     *
     * @return array<string>
     */
    public function getLocalizableSettings(
        string $contextKey = self::DEFAULT_CONTEXT
    ): array {
        if (!array_key_exists($contextKey, $this->localizableSettings)) {
            throw new Exception('Unknown context key: ' . $contextKey);
        }
        return $this->localizableSettings[$contextKey];
    }

    /**
     * Is the setting localizable?
     *
     * A localizable setting has localized values in configuration.
     *
     * @param string $setting    Setting key
     * @param string $contextKey Key identifying the context (optional)
     *
     * @return bool
     */
    public function isLocalizableSetting(
        string $setting,
        string $contextKey = self::DEFAULT_CONTEXT
    ): bool {
        return in_array($setting, $this->getLocalizableSettings($contextKey));
    }

    /**
     * Validate settings.
     *
     * @param array<string, mixed> $settings   Setting keys and values
     * @param string               $contextKey Key identifying the context (optional)
     *
     * @return array
     * @throws BadConfig
     */
    public function validateSettings(
        array $settings,
        string $contextKey = self::DEFAULT_CONTEXT
    ): array {
        foreach ($this->getRequiredSettings($contextKey) as $requiredSetting) {
            if (
                !isset($settings[$requiredSetting])
                && $this->isRequiredSetting($requiredSetting, $settings, $contextKey)
            ) {
                throw new BadConfig(
                    'Missing required setting: ' . $requiredSetting
                );
            }
        }
        return $settings;
    }

    /**
     * Localize the settings if possible.
     *
     * @param array<string, mixed> $settings        Setting keys and values to localize
     * @param string               $userLocale      User locale
     * @param array                $fallbackLocales Fallback locale(s) (optional)
     * @param string               $contextKey      Key identifying the context (optional)
     * @param bool                 $useFirst        Use first array item if item matching locale(s) was not found
     *                                              (optional)
     *
     * @return array<string, string>
     */
    public function localizeSettings(
        array $settings,
        string $userLocale,
        array $fallbackLocales = [],
        string $contextKey = self::DEFAULT_CONTEXT,
        bool $useFirst = true
    ): array {
        foreach ($settings as $key => $value) {
            $settings[$key]
                = $this->localizeSetting(
                    $key,
                    $value,
                    $userLocale,
                    $fallbackLocales,
                    $contextKey,
                    $useFirst
                );
        }
        return $settings;
    }

    /**
     * Localize the setting if possible.
     *
     * @param string $key             Key
     * @param mixed  $value           Value
     * @param string $userLocale      User locale
     * @param array  $fallbackLocales Fallback locale(s) (optional)
     * @param string $contextKey      Key identifying the context (optional)
     * @param bool   $useFirst        Use first array item if item matching locale(s) was not found (optional)
     *
     * @return array|string
     */
    public function localizeSetting(
        string $key,
        mixed $value,
        string $userLocale,
        array $fallbackLocales = [],
        string $contextKey = self::DEFAULT_CONTEXT,
        bool $useFirst = true
    ): array|string {
        // Default implementation expects the localized values to be in an array
        // keyed by locale code.
        if (!$this->isLocalizableSetting($key, $contextKey) || !is_array($value)) {
            return $value;
        }
        foreach (array_merge([$userLocale], $fallbackLocales) as $locale) {
            if (array_key_exists($locale, $value)) {
                return $value[$locale];
            }
        }
        return $useFirst ? reset($value) : $value;
    }

    /**
     * Add required settings.
     *
     * @param array<string> $settings   Setting keys to add
     * @param string        $contextKey Key identifying the context (optional)
     *
     * @return void
     */
    protected function addRequiredSettings(
        array $settings,
        string $contextKey = self::DEFAULT_CONTEXT,
    ): void {
        $this->requiredSettings[$contextKey] = array_unique(array_merge(
            $this->requiredSettings[$contextKey] ?? [],
            $settings
        ));
    }

    /**
     * Add localizable settings.
     *
     * @param array<string> $settings   Setting keys to add
     * @param string        $contextKey Key identifying the context (optional)
     *
     * @return void
     */
    protected function addLocalizableSettings(
        array $settings,
        string $contextKey = self::DEFAULT_CONTEXT,
    ): void {
        $this->localizableSettings[$contextKey] = array_unique(array_merge(
            $this->localizableSettings[$contextKey] ?? [],
            $settings
        ));
    }
}
