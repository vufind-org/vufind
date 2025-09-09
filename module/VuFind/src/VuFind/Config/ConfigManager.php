<?php

/**
 * Configuration manager
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

use Laminas\Cache\Storage\StorageInterface;
use VuFind\Cache\KeyGeneratorTrait;
use VuFind\Cache\Manager as CacheManager;
use VuFind\Config\Handler\PluginManager as HandlerPluginManager;
use VuFind\Config\Location\ConfigFile;
use VuFind\Config\Location\ConfigLocationInterface;
use VuFind\Exception\ConfigException;

use function is_array;
use function strval;

/**
 * Configuration manager
 *
 * @category VuFind
 * @package  Config
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class ConfigManager implements ConfigManagerInterface
{
    use KeyGeneratorTrait;

    /**
     * Default cache (Required to avoid warnings using the KeyGeneratorTrait).
     */
    protected StorageInterface $cache;

    /**
     * Constructor
     *
     * @param ConfigLoader         $configLoader         Config loader
     * @param HandlerPluginManager $configHandlerManager Config handler plugin manager
     * @param CacheManager         $cacheManager         Cache manager
     */
    public function __construct(
        protected ConfigLoader $configLoader,
        protected HandlerPluginManager $configHandlerManager,
        protected CacheManager $cacheManager
    ) {
        $this->cache = $this->cacheManager->getCache('config');
    }

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
    public function getConfig(string $configPath, bool $forceReload = false, bool $useLocalConfig = true): mixed
    {
        $configLocation = $this->configLoader->getConfigLocation($configPath, $useLocalConfig);
        if (!$configLocation) {
            return [];
        }
        $config = $this->loadConfigFromLocation($configLocation, forceReload: $forceReload);
        return $config;
    }

    /**
     * Get config as array by path.
     *
     * @param string $configPath     Config path
     * @param bool   $forceReload    If cache should be ignored
     * @param bool   $useLocalConfig Use local configuration if available
     *
     * @return array
     */
    public function getConfigArray(string $configPath, bool $forceReload = false, bool $useLocalConfig = true): array
    {
        $config = $this->getConfig($configPath, $forceReload, $useLocalConfig);
        if (!is_array($config)) {
            throw new ConfigException('Configuration on path ' . $configPath . ' is not an array.');
        }
        return $config;
    }

    /**
     * Get config as object by path.
     *
     * @param string $configPath     Config path
     * @param bool   $forceReload    If cache should be ignored
     * @param bool   $useLocalConfig Use local configuration if available
     *
     * @return Config
     */
    public function getConfigObject(string $configPath, bool $forceReload = false, bool $useLocalConfig = true): Config
    {
        return new Config($this->getConfigArray($configPath, $forceReload, $useLocalConfig));
    }

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
    public function get($name, ?array $options = null)
    {
        return $this->getConfigObject(
            $name,
            forceReload: $options['forceReload'] ?? false
        );
    }

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
    ): mixed {
        $cacheConfig = $this->cacheManager->getConfig();
        $cacheOptions = array_merge(
            $cacheConfig['ConfigCache'] ?? [],
            $cacheConfig['CacheConfigHandler_' . $configLocation->getHandler()] ?? [],
            $cacheConfig['CacheConfigName_' . $configLocation->getConfigName()] ?? [],
        );
        $useAdvancedCache = ($configLocation instanceof ConfigFile) && !($cacheOptions['disabled'] ?? true);
        $cacheName = $cacheOptions['cacheName'] ?? 'config';
        $advancedCache = $this->cacheManager->getCache($cacheName);
        $configPath = $configLocation->getPath();
        $modificationTime = (($cacheOptions['reloadOnFileChange'] ?? true) && file_exists($configPath))
            ? strval(filemtime($configPath))
            : '';
        $cacheKey = $this->getCacheKey($configLocation->getCacheKey() . $modificationTime, $advancedCache);

        // check first if config was already loaded by the ConfigLoader
        // to avoid double filesystem access.
        $config = !$forceReload ? $this->configLoader->getCachedConfigFromLocation($configLocation) : null;

        // check if configuration was cached using the advanced caching.
        if ($config === null && $useAdvancedCache && !$forceReload) {
            $config = $advancedCache->getItem($cacheKey);
            if ($config !== null) {
                $this->configLoader->setCachedConfigForLocation($configLocation, $config);
                return $config;
            }
        }

        // load configuration if it was not cached yet.
        if ($config === null) {
            $config = $this->configLoader->loadConfigFromLocation($configLocation, $handleParentConfig, $forceReload);
        }

        if ($useAdvancedCache) {
            $advancedCache->setItem($cacheKey, $config);
        }
        return $config;
    }

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
    ): void {
        $this->configHandlerManager
            ->getForLocation($destinationLocation)
            ->writeConfig($destinationLocation, $config, $baseLocation);
    }
}
