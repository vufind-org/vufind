<?php

/**
 * Configuration manager interface.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Config
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\Config;

use VuFind\Config\Location\ConfigLocationInterface;

/**
 * Configuration manager interface.
 *
 * @category VuFind
 * @package  Config
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
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
     * @deprecated Use getConfigArray, getConfigObject or getConfig instead
     */
    public function get($name, ?array $options = null);

    /**
     * Load config from a specific location.
     *
     * @param ConfigLocationInterface $configLocation     Config location
     * @param bool                    $handleParentConfig If parent configuration should be handled
     * @param bool                    $forceReload        If cache should be ignored
     *
     * @return mixed
     */
    public function loadConfigFromLocation(
        ConfigLocationInterface $configLocation,
        bool $handleParentConfig = true,
        bool $forceReload = false
    ): mixed;

    /**
     * Write config to a specific location.
     *
     * @param ConfigLocationInterface  $destinationLocation Destination location
     * @param array|string             $config              Configuration
     * @param ?ConfigLocationInterface $baseLocation        Optional base location that can provide additional
     * structure (e.g. comments)
     *
     * @return void
     */
    public function writeConfig(
        ConfigLocationInterface $destinationLocation,
        array|string $config,
        ?ConfigLocationInterface $baseLocation
    ): void;
}
