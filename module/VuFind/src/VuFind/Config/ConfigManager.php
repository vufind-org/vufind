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

use VuFind\Config\Handler\PluginManager as HandlerPluginManager;
use VuFind\Config\Location\ConfigLocationInterface;
use VuFind\Exception\ConfigException;
use VuFind\Feature\MergeRecursiveTrait;

use function in_array;
use function is_array;
use function is_string;

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
class ConfigManager
{
    use MergeRecursiveTrait;

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
     * Get config by path.
     *
     * The path consists of a base configuration name and a path to a subsection of that configuration.
     *
     * @param string $configPath Config path
     *
     * @return mixed
     */
    public function getConfig(string $configPath): mixed
    {
        $subsection = explode('/', $configPath);
        $configName = array_shift($subsection);
        $configLocation = $this->pathResolver->getConfigLocation($configName);
        if (!$configLocation) {
            return [];
        }
        $configLocation->setSubsection($subsection);
        return $this->loadConfigFromLocation($configLocation);
    }

    /**
     * Get config as array by path.
     *
     * @param string $configPath Config path
     *
     * @return array
     */
    public function getConfigArray(string $configPath): array
    {
        $config = $this->getConfig($configPath);
        if (!is_array($config)) {
            throw new ConfigException('Configuration on path ' . $configPath . ' is not an array.');
        }
        return $config;
    }

    /**
     * Get config as object by path.
     *
     * @param string $configPath Config path
     *
     * @return Config
     */
    public function getConfigObject(string $configPath): Config
    {
        return new Config($this->getConfigArray($configPath));
    }

    /**
     * Load config from a specific location.
     *
     * @param ConfigLocationInterface $configLocation Config location
     *
     * @return mixed
     */
    public function loadConfigFromLocation(ConfigLocationInterface $configLocation): mixed
    {
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
                ->parseConfig($currentConfigLocation);
            $configs[] = $currentConfig;
            $currentConfigLocation = null;
            if ($parentLocation = $currentConfig['parentLocation'] ?? null) {
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
        return $result;
    }
}
