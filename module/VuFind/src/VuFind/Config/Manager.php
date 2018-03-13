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
    const CACHE_ENABLED = APPLICATION_ENV !== 'development';
    const CONFIG_PATH = APPLICATION_PATH . '/config/config.php';
    const CONFIG_CACHE_DIR = LOCAL_CACHE_DIR . '/config';
    const ENTIRE_CONFIG_PATH = self::CONFIG_CACHE_DIR . '/entire.php';
    const SPARSE_CONFIG_PATH = self::CONFIG_CACHE_DIR . '/sparse.php';

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
     * Contains only the required configuration data.
     *
     * @var Config
     */
    protected $sparseConfig;

    /**
     * Enables to statically get the manager instance in providers.
     *
     * @return Manager
     */
    public static function getInstance(): Manager
    {
        return static::$manager ?: static::$manager = new static;
    }

    protected function __construct()
    {
        $this->iniReader = new IniReader;
        $yamlReader = new YamlReader([YamlParser::class, 'parse']);
        Factory::registerReader('ini', $this->iniReader);
        Factory::registerReader('yaml', $yamlReader);

        if (!file_exists(static::CONFIG_CACHE_DIR)) {
            mkdir(static::CONFIG_CACHE_DIR, 0700);
        }

        if (!static::CACHE_ENABLED) {
            $this->reset();
        }
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
        $path = trim($path, '/');
        $keys = $path ? explode('/', $path) : [];

        $config = $this->getSparseConfig();

        if ($this->trueOn($config->loaded, ...$keys)) {
            return $this->getAt($config->content, ...$keys);
        }

        $data = $this->getAt($this->getEntireConfig(), ...$keys);
        $this->setAt($config, $data, 'content', ...$keys);
        $this->setAt($config, true, 'loaded', ...$keys);

        Factory::toFile(static::SPARSE_CONFIG_PATH, $config);

        return $data;
    }

    /**
     * Gets the reader for INI files registered with the configuration factory.
     *
     * @return IniReader
     */
    public function getIniReader(): IniReader
    {
        return $this->iniReader;
    }

    public function reset()
    {
        $this->sparseConfig = $this->entireConfig = null;
        if (file_exists(static::SPARSE_CONFIG_PATH)) {
            unlink(static::SPARSE_CONFIG_PATH);
        }
        if (file_exists(static::ENTIRE_CONFIG_PATH)) {
            unlink(static::ENTIRE_CONFIG_PATH);
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
    protected function trueOn(Config $config, ...$path) : bool
    {
        $head = $config->{array_pop($path)};
        return $head instanceof Config ?
            $this->trueOn($head, ...$path) : $head === true;
    }

    /**
     * Sets configuration value at the specified path automatically creating
     * nested configurations for each non-existing path segment.
     *
     * @param Config $config
     * @param mixed  $value
     * @param array  $path
     *
     * @return mixed
     */
    protected function setAt(Config $config, $value, ...$path)
    {
        $head = $config->{array_pop($path)} ?: new Config([], true);
        return $head = $path ? $this->setAt($head, $value, ...$path) : $value;
    }

    /**
     * Gets a configuation value at the specified path.
     *
     * @param Config $config
     * @param array  $path
     *
     * @return mixed
     */
    protected function getAt(Config $config, ...$path)
    {
        $head = $config->{array_pop($path)};
        return $path ? $this->getAt($head, ...$path) : $head;
    }

    /**
     * Gets the required configuration.
     *
     * @return Config
     */
    protected function getSparseConfig(): Config
    {
        return $this->sparseConfig ?: $this->loadSparseConfig();
    }

    /**
     * Loads the required configuration.
     *
     * @return Config
     */
    protected function loadSparseConfig(): Config
    {
        $data = static::CACHE_ENABLED && file_exists(static::SPARSE_CONFIG_PATH)
            ? Factory::fromFile(static::SPARSE_CONFIG_PATH)
            : ['loaded' => [], 'content' => []];
        return $this->sparseConfig = new Config($data, true);
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
        $merger = require static::CONFIG_PATH;
        $data = $merger->getMergedConfig();
        return $this->entireConfig = new Config($data, true);
    }
}
