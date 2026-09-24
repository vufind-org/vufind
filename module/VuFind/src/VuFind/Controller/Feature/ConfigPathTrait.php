<?php

/**
 * VuFind Action Feature Trait - Configuration file path methods.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2022.
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
 * @package  Controller_Plugins
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Controller\Feature;

use VuFind\Config\ConfigManagerInterface;
use VuFind\Config\PathResolver;

/**
 * VuFind Action Feature Trait - Configuration file path methods.
 *
 * @category VuFind
 * @package  Controller_Plugins
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
trait ConfigPathTrait
{
    /**
     * Get path to base configuration file.
     *
     * @param string $configName Configuration name
     *
     * @return string
     */
    protected function getBaseConfigFilePath(string $configName): string
    {
        return $this->getService(PathResolver::class)
            ->getBaseConfigLocation($configName)
            ->getPath();
    }

    /**
     * Get path to local configuration file (even if it does not yet exist).
     *
     * @param string $configName Configuration name
     *
     * @return string
     */
    protected function getForcedLocalConfigPath(string $configName): string
    {
        return $this->getService(PathResolver::class)
            ->getForcedLocalConfigLocation($configName)
            ->getPath();
    }

    /**
     * Change configuration.
     *
     * @param string $configName Config name
     * @param array  $config     Config to change
     *
     * @return void
     */
    protected function changeConfig(string $configName, array $config): void
    {
        $pathResolver = $this->getService(PathResolver::class);
        $currentConfig = $this->getService(ConfigManagerInterface::class)
            ->getConfigArray($configName);
        foreach ($config as $section => $sectionConfig) {
            foreach ($sectionConfig as $setting => $value) {
                if ($value === null) {
                    unset($currentConfig[$section][$setting]);
                } else {
                    $currentConfig[$section][$setting] = $value;
                }
            }
        }
        $configLocation = $pathResolver->getForcedLocalConfigLocation($configName);
        $baseConfigLocation = file_exists($configLocation->getPath())
            ? $configLocation
            : $pathResolver->getBaseConfigLocation($configName);
        $this->getService(ConfigManagerInterface::class)
            ->writeConfig($configLocation, $currentConfig, $baseConfigLocation);
    }
}
