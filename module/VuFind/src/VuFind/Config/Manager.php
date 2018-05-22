<?php
/**
 * VuFind Configuration Manager
 *
 * Copyright (C) 2018 Leipzig University Library <info@ub.uni-leipzig.de>
 *
 * PHP version 7
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
 * along with this program; if not, write to the Free Software Foundation,
 * Inc. 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA
 *
 * @category VuFind
 * @package  Config
 * @author   Sebastian Kehr <kehr@ub.uni-leipzig.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU GPLv2
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace VuFind\Config;

use Zend\Config\Config;

/**
 * VuFind Configuration Manager
 *
 * @category VuFind
 * @package  Config
 * @author   Sebastian Kehr <kehr@ub.uni-leipzig.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Manager
{
    /**
     * Contains the all aggregated configuration data loaded and looked up only
     * in case a look-up on the already demanded configuration data has failed.
     *
     * @var Config
     */
    protected $aggregatedConfig;

    /**
     * Path to the PHP file which {@see \Zend\ConfigAggregator\ConfigAggregator}
     * will use for caching the aggregated configuration.
     *
     * @var string
     */
    protected $aggregatedConfigPath;

    /**
     * Path to PHP file returning a {@see \Closure} creating an instance
     * of {@see \Zend\ConfigAggregator\ConfigAggregator} when called.
     *
     * @var string
     */
    protected $configPath;

    /**
     * Contains only the demanded configuration data.
     *
     * @var Config
     */
    protected $demandedConfig;

    /**
     * Path to PHP file used for caching the demanded configuraton data.
     *
     * @var string
     */
    protected $demandedConfigPath;

    /**
     * Flag specifying whether the cached configuration data should be used.
     *
     * @var bool
     */
    protected $useCache;

    /**
     * Manager constructor.
     *
     * @param string $configPath {@see Manager::$configPath}
     * @param string $cacheDir   Base directory of
     *                           {@see Manager::$aggregatedConfigPath} and
     *                           {@see Manager::$demandedConfigPath}
     * @param bool   $useCache   {@see Manager::$useCache}
     */
    public function __construct(
        string $configPath,
        string $cacheDir,
        bool $useCache
    ) {
        $this->configPath = $configPath;
        $this->aggregatedConfigPath = "$cacheDir/aggregated.config.php";
        $this->demandedConfigPath = "$cacheDir/demanded.config.php";
        $this->useCache = $useCache;
        if (!$useCache) {
            $this->reset();
        }
    }

    /**
     * Gets the configuration section at the specified path or an empty
     * configuration in case the path does not exist.
     *
     * @param string $path Path expression using forward slashes to separate
     *                     sections.
     *
     * @return Config
     */
    public function getConfig(string $path = '/'): Config
    {
        $config = $this->getValue($path) ?? new Config([]);

        return new Config($config->toArray());
    }

    /**
     * Gets the configuration value at the specified path.
     *
     * @param string $path Path expression using forward slashes to separate
     *                     sections
     *
     * @return mixed The value
     */
    public function getValue(string $path = '/')
    {
        // normalize path into an array of segments
        $path = ($path = trim($path, '/')) ? explode('/', $path) : [];
        // get the already demanded configuration data
        $demandedConfig = $this->getDemandedConfig();
        // if the given path was already demanded return the corresponding data
        if ($this->trueOn($demandedConfig, 'demanded', ...$path)) {
            return $this->getAt($demandedConfig, 'content', ...$path);
        }
        // otherwise look-up the aggregated configuration,
        $data = $this->getAt($this->getAggregatedConfig(), ...$path);
        // then store the data in the demanded configuration object,
        $this->setAt($demandedConfig, $data, 'content', ...$path);
        // then flag the data as «demanded» for the specified path,
        $this->setAt($demandedConfig, true, 'demanded', ...$path);
        // then cache the demanded configuration
        Factory::toFile($this->demandedConfigPath, $demandedConfig);

        // finally return the configuration data
        return $data;
    }

    /**
     * Deletes cached configuration files and resets the manager accordingly.
     *
     * @return void
     */
    public function reset()
    {
        $this->demandedConfig = $this->aggregatedConfig = null;

        if (file_exists($this->aggregatedConfigPath)) {
            unlink($this->aggregatedConfigPath);
        }

        if (file_exists($this->demandedConfigPath)) {
            unlink($this->demandedConfigPath);
        }
    }

    /**
     * Checks if some nested configuration value reachable on a given path
     * strictly equals true.
     *
     * @param Config $config  The configuration object to check.
     * @param array  ...$path The path to check.
     *
     * @return bool
     */
    protected function trueOn(Config $config, ...$path): bool
    {
        $head = $config->{array_shift($path)};

        return $head instanceof Config ?
            $this->trueOn($head, ...$path) : $head === true;
    }

    /**
     * Sets a configuration value at the specified path recursively creating
     * a nested configuration for each non-existing intermediate path segment.
     *
     * @param Config $config  The configuration object to be mutated.
     * @param mixed  $value   The value to be set.
     * @param array  ...$path The path within the configuration to be altered.
     *
     * @return Config
     */
    protected function setAt(Config $config, $value, ...$path): Config
    {
        $head = array_shift($path);

        if ($path) {
            $headConfig = $config->$head ?: new Config([], true);
            $config->$head = $this->setAt($headConfig, $value, ...$path);
        } else {
            $config->$head = $value;
        }

        return $config;
    }

    /**
     * Gets a nested configuration value reachable via a given path.
     *
     * @param Config $config  The configuration object to look up.
     * @param array  ...$path The path to the nested value.
     *
     * @return mixed|null The value reachable at the given path or null
     *                    in case the path does not exist.
     */
    protected function getAt(Config $config, ...$path)
    {
        $head = $config->{array_shift($path)};

        return $path ? $this->getAt($head ?? new Config([]), ...$path) : $head;
    }

    /**
     * Gets the required configuration.
     *
     * @return Config
     */
    protected function getDemandedConfig(): Config
    {
        return $this->demandedConfig ?: $this->loadDemandedConfig();
    }

    /**
     * Loads the required configuration.
     *
     * @return Config
     */
    protected function loadDemandedConfig(): Config
    {
        $data = $this->useCache && file_exists($this->demandedConfigPath)
            ? Factory::fromFile($this->demandedConfigPath)
            : ['demanded' => [], 'content' => []];

        return $this->demandedConfig = new Config($data, true);
    }

    /**
     * Get the aggregated configuration.
     *
     * @return Config
     */
    protected function getAggregatedConfig(): Config
    {
        return $this->aggregatedConfig ?: $this->loadAggregatedConfig();
    }

    /**
     * Load the aggregated configuration.
     *
     * @return Config
     */
    protected function loadAggregatedConfig(): Config
    {
        $getAggregator = include $this->configPath;
        $data = $getAggregator($this->aggregatedConfigPath)->getMergedConfig();

        return $this->aggregatedConfig = new Config($data, true);
    }
}
