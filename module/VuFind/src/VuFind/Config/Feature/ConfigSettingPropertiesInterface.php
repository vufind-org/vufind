<?php

/**
 * Interface for exposing setting properties in a configuration class.
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

/**
 * Interface for exposing setting properties in a configuration class.
 *
 * @category VuFind
 * @package  Config
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
interface ConfigSettingPropertiesInterface
{
    /**
     * Default context key.
     *
     * Contexts other than the default context are implementation
     * (configuration) specific.
     */
    public const DEFAULT_CONTEXT = '__default';

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
    ): array;

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
     */
    public function isRequiredSetting(
        string $setting,
        array $context = [],
        string $contextKey = self::DEFAULT_CONTEXT
    ): bool;

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
    ): array;

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
    ): bool;
}
