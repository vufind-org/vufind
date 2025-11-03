<?php

/**
 * Trait implementing SettingPropertiesInterface
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

use VuFind\Exception\BadConfig;

use function array_key_exists;
use function in_array;
use function is_array;

/**
 * Trait implementing SettingPropertiesInterface
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
     * @var array
     */
    protected array $requiredSettings = [self::DEFAULT_CONTEXT => []];

    /**
     * Localizable settings.
     *
     * @var array
     */
    protected array $localizableSettings = [self::DEFAULT_CONTEXT => []];

    /**
     * Return required and conditionally required settings.
     *
     * @param string $contextKey Key identifying the context (optional)
     *
     * @return array
     */
    public function getRequiredSettings(
        string $contextKey = self::DEFAULT_CONTEXT
    ): array {
        return $this->requiredSettings[$contextKey];
    }

    /**
     * Is the setting required?
     *
     * The optional context and context key parameters are used to evaluate if a
     * conditionally required setting is required. If context is omitted returns
     * true for both required and conditionally required settings.
     *
     * @param string $setting    Setting
     * @param array  $context    Settings to be used in evaluation (optional)
     * @param string $contextKey Key identifying the context (optional)
     *
     * @return bool
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
     * @param string $contextKey Key identifying the context (optional)
     *
     * @return array
     */
    public function getLocalizableSettings(
        string $contextKey = self::DEFAULT_CONTEXT
    ): array {
        return $this->localizableSettings[$contextKey];
    }

    /**
     * Is the setting localizable?
     *
     * @param string $setting    Setting
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
     * @param array  $settings   Settings
     * @param string $contextKey Key identifying the context (optional)
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
     * @param array  $settings        Settings to localize
     * @param string $userLocale      User locale
     * @param array  $fallbackLocales Fallback locale(s) (optional)
     * @param string $contextKey      Key identifying the context (optional)
     * @param bool   $useFirst        Use first array item if item matching
     *                                locale(s) was not found (optional)
     *
     * @return array
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
     * @param string       $key             Key
     * @param string|array $value           Value
     * @param string       $userLocale      User locale
     * @param array        $fallbackLocales Fallback locale(s) (optional)
     * @param string       $contextKey      Key identifying the context (optional)
     * @param bool         $useFirst        Use first array item if item matching
     *                                      locale(s) was not found (optional)
     *
     * @return array|string
     */
    public function localizeSetting(
        string $key,
        string|array $value,
        string $userLocale,
        array $fallbackLocales = [],
        string $contextKey = self::DEFAULT_CONTEXT,
        bool $useFirst = true
    ): array|string {
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
     * @param array  $settings   Settings to add
     * @param string $contextKey Key identifying the context (optional)
     *
     * @return void
     */
    protected function addRequiredSettings(
        array $settings,
        string $contextKey = self::DEFAULT_CONTEXT,
    ): void {
        $this->requiredSettings[$contextKey] = array_merge(
            $this->requiredSettings[$contextKey] ?? [],
            $settings
        );
    }

    /**
     * Add localizable settings.
     *
     * @param array  $settings   Settings to add
     * @param string $contextKey Key identifying the context (optional)
     *
     * @return void
     */
    protected function addLocalizableSettings(
        array $settings,
        string $contextKey = self::DEFAULT_CONTEXT,
    ): void {
        $this->localizableSettings[$contextKey] = array_merge(
            $this->localizableSettings[$contextKey] ?? [],
            $settings
        );
    }
}
