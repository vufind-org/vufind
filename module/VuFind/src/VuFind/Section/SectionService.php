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

use VuFind\Config\Feature\SettingPropertiesInterface;
use VuFind\Config\YamlReader;
use VuFind\Exception\BadConfig;
use VuFind\Exception\ConfigException;
use VuFind\Navigation\PluginManager as NavigationPluginManager;
use VuFind\Section\PluginManager as SectionPluginManager;

use function is_string;

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
     * @param YamlReader              $yamlReader      YAML reader
     * @param SectionPluginManager    $sectionPm       Section plugin manager
     * @param NavigationPluginManager $navigationPm    Navigation plugin manager
     * @param string                  $userLocale      User locale
     * @param array                   $fallbackLocales Fallback locales
     */
    public function __construct(
        protected YamlReader $yamlReader,
        protected SectionPluginManager $sectionPm,
        protected NavigationPluginManager $navigationPm,
        protected string $userLocale,
        protected array $fallbackLocales
    ) {
    }

    /**
     * Get section.
     *
     * @param string       $key    Section key in configuration
     * @param array|string $config Configuration or configuration file name
     *                             (optional)
     *
     * @return ?SectionInterface
     * @throws ConfigException
     * @throws BadConfig
     */
    public function getSection(
        string $key,
        array|string $config = self::DEFAULT_CONFIG_FILE
    ): ?SectionInterface {
        if (is_string($config)) {
            $configFile = $this->getConfigFromFile($config);
            $config = $this->getSectionConfig($key, $configFile);
        }

        if (!$type = $config['type'] ?? false) {
            throw new BadConfig('Missing required setting: type');
        }

        $pluginManager = $this->sectionPm;

        // Type specific configuration processing.
        switch ($type) {
            case 'navigation':
                if (!$type = $config['plugin'] ?? false) {
                    throw new BadConfig('Missing required setting: plugin');
                }
                $pluginManager = $this->navigationPm;
                break;
        }

        // Get plugin and initialize with key and optionally configuration,
        // depending on plugin type.
        $plugin = $pluginManager->get($type);
        $plugin->setKey($key);
        if ($pluginManager !== $this->navigationPm) {
            $plugin->setConfig($config);
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
        string $contextKey = SettingPropertiesInterface::DEFAULT_CONTEXT,
        bool $useFirst = true
    ): array {
        return $section->localizeSettings(
            ($settings ?? $section->getConfig()),
            $this->userLocale,
            $this->fallbackLocales,
            $contextKey,
            $useFirst
        );
    }

    /**
     * Get sections configuration from file.
     *
     * @param string $file Configuration file name (optional)
     *
     * @return array
     */
    protected function getConfigFromFile(string $file = self::DEFAULT_CONFIG_FILE): array
    {
        $config = $this->yamlReader->get($file);
        if (empty($config)) {
            throw new ConfigException(
                'Configuration file not found or empty: ' . $file
            );
        } elseif (!isset($config['Sections'])) {
            throw new BadConfig(
                'Sections key is missing from configuration file: ' . $file
            );
        }
        return $config['Sections'];
    }

    /**
     * Get configuration of specified section from sections configuration.
     *
     * @param string $key    Key
     * @param array  $config Configuration
     *
     * @return array
     * @throws BadConfig
     */
    protected function getSectionConfig(string $key, array $config): array
    {
        if (!isset($config[$key])) {
            throw new BadConfig('Section not found: ' . $key);
        }
        return $config[$key];
    }
}
