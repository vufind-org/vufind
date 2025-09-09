<?php

/**
 * VuFind Config Manager
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
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
 * @package  ServiceManager
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\Config;

use Laminas\ServiceManager\AbstractPluginManager as Base;
use Psr\Container\ContainerInterface;
use VuFind\Config\Location\ConfigLocationInterface;

/**
 * VuFind Config Manager
 *
 * @category VuFind
 * @package  ServiceManager
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 *
 * @deprecated Use \VuFind\Config\ConfigManager instead
 */
class PluginManager extends Base implements ConfigManagerInterface
{
    /**
     * ConfigManager
     *
     * @var ConfigManagerInterface
     */
    protected ConfigManagerInterface $configManager;

    /**
     * Constructor
     *
     * Make sure plugins are properly initialized.
     *
     * @param mixed $configOrContainerInstance Configuration or container instance
     * @param array $v3config                  If $configOrContainerInstance is a
     * container, this value will be passed to the parent constructor.
     */
    public function __construct(
        $configOrContainerInstance = null,
        array $v3config = []
    ) {
        $this->addAbstractFactory(PluginFactory::class);
        // Disable caching in the plugin manager. This is handled by the \VuFind\Config\ConfigManager.
        if (!isset($v3config['shared_by_default'])) {
            $v3config['shared_by_default'] = false;
        }
        parent::__construct($configOrContainerInstance, $v3config);
        if (!$configOrContainerInstance instanceof ContainerInterface) {
            throw new \Exception('PluginManager needs to be constructed with container instance.');
        }
        $this->configManager = $configOrContainerInstance->get(ConfigManager::class);
    }

    /**
     * Validate the plugin
     *
     * Checks that the filter loaded is either a valid callback or an instance
     * of FilterInterface.
     *
     * @param mixed $plugin Plugin to validate
     *
     * @return void
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function validate($plugin)
    {
        // Assume everything is okay.
    }

    /**
     * Reload a configuration and return the new version
     *
     * @param string $id Service identifier
     *
     * @return \VuFind\Config\Config
     *
     * @deprecated Use \VuFind\Config\ConfigManager::getConfig with forceReload=true directly
     */
    public function reload($id)
    {
        $oldOverrideSetting = $this->getAllowOverride();
        $this->setAllowOverride(true);
        $this->setService($id, $this->build($id, ['forceReload' => true]));
        $this->setAllowOverride($oldOverrideSetting);
        return $this->get($id);
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
        return $this->configManager->getConfig($configPath, $forceReload, $useLocalConfig);
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
        return $this->configManager->getConfigArray($configPath, $forceReload, $useLocalConfig);
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
        return $this->configManager->getConfigObject($configPath, $forceReload, $useLocalConfig);
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
        return parent::get($name, $options);
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
        return $this->configManager->loadConfigFromLocation($configLocation, $handleParentConfig, $forceReload);
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
        $this->configManager->writeConfig($destinationLocation, $config, $baseLocation);
    }
}
