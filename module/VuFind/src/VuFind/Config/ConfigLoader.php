<?php

/**
 * Configuration loader
 * Note: This class supports the ConfigManager but should not be called directly except in rare situations
 * where the ConfigManager must be bypassed to avoid circular dependencies (as when setting up the
 * Cache Manager). Unless you have a very good reason, always use the ConfigManager instead.
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

use VuFind\Config\Handler\PluginManager as HandlerPluginManager;
use VuFind\Config\Location\ConfigLocationInterface;
use VuFind\Exception\ConfigException;
use VuFind\Feature\MergeRecursiveTrait;

use function in_array;
use function is_array;
use function is_string;

/**
 * Configuration loader
 * Note: This class supports the ConfigManager but should not be called directly except in rare situations
 * where the ConfigManager must be bypassed to avoid circular dependencies (as when setting up the
 * Cache Manager). Unless you have a very good reason, always use the ConfigManager instead.
 *
 * @category VuFind
 * @package  Config
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class ConfigLoader
{
    use MergeRecursiveTrait;

    /**
     * Simple configuration cache.
     *
     * @var array
     */
    protected array $configCache = [];

    /**
     * Constructor
     *
     * @param HandlerPluginManager $configHandlerManager Config handler plugin manager
     * @param PathResolver         $pathResolver         Path resolver
     */
    public function __construct(
        protected HandlerPluginManager $configHandlerManager,
        protected PathResolver $pathResolver
    ) {
    }

    /**
     * Get config location by config path.
     *
     * @param string $configPath     Config path
     * @param bool   $useLocalConfig Use local configuration if available
     *
     * @return ?ConfigLocationInterface
     */
    public function getConfigLocation(string $configPath, bool $useLocalConfig = true): ?ConfigLocationInterface
    {
        $subsection = explode('/', $configPath);
        $configName = array_shift($subsection);
        $configLocation = $useLocalConfig
            ? $this->pathResolver->getConfigLocation($configName)
            : $this->pathResolver->getBaseConfigLocation($configName);
        if ($configLocation === null) {
            return null;
        }
        $configLocation->setSubsection($subsection);
        return $configLocation;
    }

    /**
     * Get cached config from config location.
     *
     * @param ConfigLocationInterface $configLocation Config location
     *
     * @return mixed
     */
    public function getCachedConfigFromLocation(ConfigLocationInterface $configLocation): mixed
    {
        return $this->configCache[$configLocation->getCacheKey()] ?? null;
    }

    /**
     * Set cached config for config location.
     *
     * @param ConfigLocationInterface $configLocation Config location
     * @param mixed                   $config         Config
     *
     * @return void
     */
    public function setCachedConfigForLocation(ConfigLocationInterface $configLocation, mixed $config): void
    {
        $this->configCache[$configLocation->getCacheKey()] = $config;
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
        $cacheKey = $configLocation->getCacheKey();
        if (!$forceReload && isset($this->configCache[$cacheKey])) {
            return $this->configCache[$cacheKey];
        }

        $loadedConfigPaths = [];

        $configs = [];

        $currentConfigLocation = $configLocation;

        do {
            // check if config was already loaded to avoid infinite loop
            $currentConfigLocationPath = realpath($currentConfigLocation->getPath());
            if (!$currentConfigLocationPath) {
                throw new ConfigException('Configuration does not exist: ' . $currentConfigLocationPath);
            }
            if (in_array($currentConfigLocationPath, $loadedConfigPaths)) {
                throw new ConfigException(
                    "Configuration already loaded: $currentConfigLocationPath\n"
                    . "Loaded config stack: \n  " . implode("\n  ", $loadedConfigPaths)
                );
            }
            $loadedConfigPaths[] = $currentConfigLocationPath;
            $currentConfig = $this->configHandlerManager
                ->getForLocation($currentConfigLocation)
                ->parseConfig($currentConfigLocation, $handleParentConfig);
            $configs[] = $currentConfig;
            $currentConfigLocation = null;
            if ($handleParentConfig && $parentLocation = $currentConfig['parentLocation'] ?? null) {
                $currentConfigLocation = $parentLocation;
            }
        } while ($currentConfigLocation);

        $result = [];
        foreach (array_reverse($configs) as $config) {
            $data = $config['data'];
            if (is_array($data)) {
                $mergeFunction = $config['mergeCallback'] ?? [$this, 'mergeRecursive'];
                $result = $mergeFunction($result, $data);
            } elseif (empty($result) && is_string($data)) {
                return $data;
            }
        }
        foreach ($configLocation->getSubsection() as $subsectionPart) {
            $result = $result[$subsectionPart] ?? null;
        }
        $this->configCache[$cacheKey] = $result;
        return $result;
    }
}
