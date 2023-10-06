<?php

/**
 * Abstract Multi Driver.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2012-2021.
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
 * @package  ILSdrivers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */

namespace VuFind\ILS\Driver;

use VuFind\Exception\ILS as ILSException;

use function array_key_exists;
use function is_callable;

/**
 * Abstract Multi Driver.
 *
 * This abstract driver defines some common methods for ILS drivers that use
 * multiple other ILS drivers.
 *
 * @category VuFind
 * @package  ILSdrivers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */

abstract class AbstractMultiDriver extends AbstractBase implements \Laminas\Log\LoggerAwareInterface
{
    use \VuFind\Log\LoggerAwareTrait {
        logError as error;
    }

    /**
     * The array of configured driver names.
     *
     * @var string[]
     */
    protected $drivers = [];

    /**
     * The path to the driver configurations relative to the config path
     *
     * @var string
     */
    protected $driversConfigPath;

    /**
     * The array of cached drivers
     *
     * @var object[]
     */
    protected $driverCache = [];

    /**
     * Configuration loader
     *
     * @var \VuFind\Config\PluginManager
     */
    protected $configLoader;

    /**
     * ILS driver manager
     *
     * @var PluginManager
     */
    protected $driverManager;

    /**
     * Constructor
     *
     * @param \VuFind\Config\PluginManager $configLoader Configuration loader
     * @param PluginManager                $dm           ILS driver manager
     */
    public function __construct(
        \VuFind\Config\PluginManager $configLoader,
        PluginManager $dm
    ) {
        $this->configLoader = $configLoader;
        $this->driverManager = $dm;
    }

    /**
     * Initialize the driver.
     *
     * Validate configuration and perform all resource-intensive tasks needed to
     * make the driver active.
     *
     * @throws ILSException
     * @return void
     */
    public function init()
    {
        if (empty($this->config)) {
            throw new ILSException('Configuration needs to be set.');
        }
        $this->drivers = $this->config['Drivers'];
        $this->driversConfigPath
            = $this->config['General']['drivers_config_path'] ?? null;
    }

    /**
     * Find the correct driver for the correct configuration file with the given name
     * and cache an initialized copy of it.
     *
     * @param string $name The name of the driver to get.
     *
     * @return mixed  On success a driver object, otherwise null.
     */
    protected function getDriver($name)
    {
        // Check for a cached driver
        if (!array_key_exists($name, $this->driverCache)) {
            // Create the driver
            $this->driverCache[$name] = $this->createDriver($name);
            if (null === $this->driverCache[$name]) {
                $this->debug("Could not initialize driver '$name'");
                return null;
            }
        }
        return $this->driverCache[$name];
    }

    /**
     * Create a driver with the given name.
     *
     * @param string $name Name of the driver.
     *
     * @return mixed On success a driver object, otherwise null.
     */
    protected function createDriver($name)
    {
        if (!isset($this->drivers[$name])) {
            return null;
        }
        $driver = $this->drivers[$name];
        $config = $this->getDriverConfig($name);
        if (!$config) {
            $this->error("No configuration found for driver '$name'");
            return null;
        }
        $driverInst = clone $this->driverManager->get($driver);
        $driverInst->setConfig($config);
        $driverInst->init();
        return $driverInst;
    }

    /**
     * Get configuration for the ILS driver.  We will load an .ini file named
     * after the driver class and number if it exists;
     * otherwise we will return an empty array.
     *
     * @param string $name The $name to use for determining the
     * configuration file
     *
     * @return array   The configuration of the driver
     */
    protected function getDriverConfig($name)
    {
        // Determine config file name based on class name:
        try {
            $path = empty($this->driversConfigPath)
                ? $name
                : $this->driversConfigPath . '/' . $name;
            $config = $this->configLoader->get($path);
        } catch (\Laminas\Config\Exception\RuntimeException $e) {
            // Configuration loading failed; probably means file does not
            // exist -- just return an empty array in that case:
            $this->error("Could not load config for $name");
            return [];
        }
        return $config->toArray();
    }

    /**
     * Check whether the given driver supports the given method
     *
     * @param object $driver ILS Driver
     * @param string $method Method name
     * @param array  $params Array of passed parameters
     *
     * @return bool
     */
    protected function driverSupportsMethod($driver, $method, $params = null)
    {
        if (is_callable([$driver, $method])) {
            if (method_exists($driver, 'supportsMethod')) {
                return $driver->supportsMethod($method, $params ?: []);
            }
            return true;
        }
        return false;
    }
}
