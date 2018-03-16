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

use Symfony\Component\Yaml\Yaml as YamlParser;
use Zend\Config\Config;
use Zend\Config\Factory;
use Zend\Config\Reader\Ini as IniReader;
use Zend\Config\Reader\Yaml as YamlReader;

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
    const CACHE_PATH = CONFIG_CACHE_DIR . '/managed-config-cache.php';

    /**
     * Static reference to this
     *
     * @var Manager
     */
    protected static $manager;

    /**
     * Reference to the used INI reader
     *
     * @var IniReader
     */
    protected $iniReader;

    /**
     * Contains the entire aggregated configuration data to be loaded and looked
     * up only in case a look-up on the sparse configuration failed.
     *
     * @var Config
     */
    protected $entireConfig;

    /**
     * Contains only the demanded configuration
     */
    protected $managedConfig;

    /**
     * Enables to statically get the manager instance in providers.
     *
     * @return Manager
     */
    public static function getManager(): Manager
    {
        // see constructor for registration of custom readers etc.
        return static::$manager ?: static::$manager = new static;
    }

    /**
     * Gets the registered reader for INI configuration files.
     *
     * @return IniReader
     */
    public static function getIniReader(): IniReader
    {
        return static::getManager()->iniReader;
    }

    /**
     * Gets the configuration section at the specfied path.
     *
     * @param string $path
     *
     * @return Config
     */
    public function getConfig(string $path = '/'): Config
    {
        return new Config($this->getValue($path)->toArray());
    }

    /**
     * Gets the configuration value at the specified path.
     *
     * @param string $path
     *
     * @return mixed
     */
    public function getValue(string $path = '/')
    {
        // normalize path into an array of segments
        $path = ($path = trim($path, '/')) ? explode('/', $path) : [];

        $managedConfig = $this->getManagedConfig();

        // return the configuration if already loaded
        if ($this->trueOn($managedConfig, 'demanded', ...$path)) {
            return $this->getAt($managedConfig, 'content', ...$path);
        }

        // otherwise look-up the entire configuration
        $data = $this->getAt($this->getEntireConfig(), ...$path);
        // store the data in the sparse configuration
        $this->setAt($managedConfig, $data, 'content', ...$path);
        // flag the data as «loaded» for this path
        $this->setAt($managedConfig, true, 'demanded', ...$path);
        // write sparse configuration to cache file
        Factory::toFile(static::CACHE_PATH, $managedConfig);
        // finally return the configuration data
        return $data;
    }

    /**
     * Deletes cached configuration files and resets the manager accordingly.
     */
    public function reset()
    {
        $this->managedConfig = $this->entireConfig = null;

        if (file_exists(CONFIG_CACHE_PATH)) {
            unlink(CONFIG_CACHE_PATH);
        }

        if (file_exists(static::CACHE_PATH)) {
            unlink(static::CACHE_PATH);
        }

    }

    protected function __construct()
    {
        // register custom readers
        $this->iniReader = new IniReader;
        $yamlReader = new YamlReader([YamlParser::class, 'parse']);
        Factory::registerReader('ini', $this->iniReader);
        Factory::registerReader('yaml', $yamlReader);

        // delete the cache files if caching is disabled
        if (!CONFIG_CACHE_ENABLED) {
            $this->reset();
        }
    }

    /**
     * Checks if some value on the given path strictly equals true.
     *
     * @param Config $config
     * @param array  $path
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
     * @param Config $config
     * @param mixed  $value
     * @param array  ...$path
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
     * Gets a configuration value at the specified path.
     *
     * @param Config $config
     * @param array  $path
     *
     * @return mixed
     */
    protected function getAt(Config $config, ...$path)
    {
        $head = $config->{array_shift($path)};
        return $path ? $this->getAt($head, ...$path) : $head;
    }

    /**
     * Gets the required configuration.
     *
     * @return Config
     */
    protected function getManagedConfig(): Config
    {
        return $this->managedConfig ?: $this->loadManagedConfig();
    }

    /**
     * Loads the required configuration.
     *
     * @return Config
     */
    protected function loadManagedConfig(): Config
    {
        $data = CONFIG_CACHE_ENABLED && file_exists(static::CACHE_PATH)
            ? Factory::fromFile(static::CACHE_PATH)
            : ['demanded' => [], 'content' => []];
        return $this->managedConfig = new Config($data, true);
    }

    /**
     * Gets the entire configuration.
     *
     * @return Config
     */
    protected function getEntireConfig(): Config
    {
        return $this->entireConfig ?: $this->loadEntireConfig();
    }

    /**
     * Loads the entire configuration.
     *
     * @return Config
     */
    protected function loadEntireConfig(): Config
    {
        $data = (require CONFIG_PATH)->getMergedConfig();
        return $this->entireConfig = new Config($data, true);
    }
}
