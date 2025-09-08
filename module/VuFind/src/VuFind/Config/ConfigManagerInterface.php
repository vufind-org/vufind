<?php

/**
 * Configuration manager interface.
 * Note: Used to smoothen the transition from \VuFind\Config\PluginManager to \VuFind\Config\ConfigManager.
 * Use ConfigManger if possible.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2025.
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
 * @package  Config
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\Config;

/**
 * Configuration manager interface.
 * Note: Used to smoothen the transition from \VuFind\Config\PluginManager to \VuFind\Config\ConfigManager.
 * Use ConfigManger if possible.
 *
 * @category VuFind
 * @package  Config
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 *
 * @deprecated Use \VuFind\Config\ConfigManager instead
 */
interface ConfigManagerInterface
{
    /**
     * Get config by path.
     *
     * The path consists of a base configuration name and a path to a subsection of that configuration.
     *
     * @param string $configPath     Config path
     * @param bool   $forceReload    If cache should be ignored
     * @param bool   $useLocalConfig Use local configuration if available
     *
     * @return mixed
     */
    public function getConfig(string $configPath, bool $forceReload = false, bool $useLocalConfig = true): mixed;

    /**
     * Get config as array by path.
     *
     * @param string $configPath     Config path
     * @param bool   $forceReload    If cache should be ignored
     * @param bool   $useLocalConfig Use local configuration if available
     *
     * @return array
     */
    public function getConfigArray(string $configPath, bool $forceReload = false, bool $useLocalConfig = true): array;

    /**
     * Get config as object by path.
     *
     * @param string $configPath     Config path
     * @param bool   $forceReload    If cache should be ignored
     * @param bool   $useLocalConfig Use local configuration if available
     *
     * @return Config
     *
     * @deprecated Use getConfigArray or getConfig instead
     */
    public function getConfigObject(string $configPath, bool $forceReload = false, bool $useLocalConfig = true): Config;

    /**
     * Get config in PluginManager style.
     *
     * @param string $name    Service name of plugin to retrieve.
     * @param ?array $options Options to use when creating the instance.
     *
     * @return mixed
     *
     * @deprecated Use getConfigArray or getConfig instead
     */
    public function get($name, ?array $options = null);
}
