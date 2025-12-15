<?php

/**
 * Section service.
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
 * @package  Section
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Section;

use VuFind\Config\Feature\ConfigSettingPropertiesInterface;
use VuFind\Config\YamlReader;
use VuFind\Exception\BadConfig;
use VuFind\Exception\ConfigException;
use VuFind\Navigation\PluginManager as NavigationManager;
use VuFind\Section\PluginManager as SectionManager;

/**
 * Section service.
 *
 * @category VuFind
 * @package  Section
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class SectionService implements SectionServiceInterface
{
    /**
     * Constructor.
     *
     * @param YamlReader        $yamlReader        YAML reader
     * @param SectionManager    $sectionManager    Section plugin manager
     * @param NavigationManager $navigationManager Navigation plugin manager
     * @param string            $userLocale        User locale
     * @param array             $fallbackLocales   Fallback locales
     */
    public function __construct(
        protected YamlReader $yamlReader,
        protected SectionManager $sectionManager,
        protected NavigationManager $navigationManager,
        protected string $userLocale,
        protected array $fallbackLocales
    ) {
    }

    /**
     * Get section configuration.
     *
     * @param string $key        Section key in configuration
     * @param string $configPath Configuration path (optional)
     *
     * @return array
     */
    public function getSectionConfig(
        string $key,
        string $configPath = self::DEFAULT_CONFIG_PATH
    ): array {
        $config = $this->yamlReader->get($configPath . '.yaml');
        if (empty($config)) {
            throw new ConfigException(
                'Configuration path not found or empty: ' . $configPath
            );
        } elseif (!isset($config['Sections'])) {
            throw new BadConfig(
                'Sections key is missing from configuration file: ' . $configPath
            );
        }
        if (!$config = $config['Sections'][$key] ?? false) {
            throw new BadConfig('Section not found: ' . $key);
        }
        return $config;
    }

    /**
     * Get section.
     *
     * If configuration is not provided, calls SectionServiceInterface::getSectionConfig()
     * to get the configuration.
     *
     * @param string $key    Section key
     * @param ?array $config Configuration (optional)
     *
     * @return SectionInterface
     */
    public function getSection(
        string $key,
        ?array $config = null
    ): SectionInterface {
        if (null === $config) {
            $config = $this->getSectionConfig($key);
        }
        if (!$type = ($config['type'] ?? false)) {
            throw new BadConfig('Missing required setting: type');
        }

        // Type specific configuration processing.
        switch ($type) {
            case 'navigation':
                if (!$type = ($config['plugin'] ?? false)) {
                    throw new BadConfig('Missing required setting: plugin');
                }
                $pluginManager = $this->navigationManager;
                break;

            default:
                $pluginManager = $this->sectionManager;
        }

        // Get plugin and initialize with key and optionally configuration,
        // depending on plugin type.
        $plugin = $pluginManager->get($type);
        $plugin->setSectionKey($key);
        if (!$pluginManager instanceof NavigationManager) {
            $plugin->setSectionConfig($config);
        }

        return $plugin;
    }

    /**
     * Localize settings of the provided section.
     *
     * If settings are not provided, calls SectionInterface::getConfig() to get
     * the configuration to be localized.
     *
     * @param SectionInterface $section    Section
     * @param ?array           $settings   Settings to localize (optional)
     * @param string           $contextKey Key identifying the context (optional)
     * @param bool             $useFirst   Use first array item if item matching
     *                                     locale(s) was not found (optional)
     *
     * @return array
     */
    public function localizeSettings(
        SectionInterface $section,
        ?array $settings = null,
        string $contextKey = ConfigSettingPropertiesInterface::DEFAULT_CONTEXT,
        bool $useFirst = true
    ): array {
        return $section->localizeSettings(
            ($settings ?? $section->getSectionConfig()),
            $this->userLocale,
            $this->fallbackLocales,
            $contextKey,
            $useFirst
        );
    }
}
