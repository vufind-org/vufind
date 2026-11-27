<?php

/**
 * Module manager.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2026.
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
 * @package  ModuleManager
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\ModuleManager;

use Laminas\ServiceManager\ServiceManager;
use Laminas\Stdlib\ArrayUtils;

use function is_callable;

/**
 * Module manager.
 *
 * @category VuFind
 * @package  ModuleManager
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class ModuleManager implements ModuleManagerInterface
{
    /**
     * All loaded modules.
     *
     * @var array
     */
    protected array $loadedModules = [];

    /**
     * All module-specific configs.
     *
     * @var array
     */
    protected array $allConfigs = [];

    /**
     * Have modules been initialized?
     *
     * @var bool
     */
    protected bool $initialized = false;

    /**
     * Constructor.
     *
     * @param ServiceManager $serviceManager  Service manager
     * @param array          $modules         List of modules
     * @param array          $configProviders List of additional configuration providers
     * @param ?string        $configCacheFile Module configuration cache file, or null to disable caching
     */
    public function __construct(
        protected ServiceManager $serviceManager,
        protected array $modules,
        protected array $configProviders,
        protected ?string $configCacheFile,
    ) {
    }

    /**
     * Load and initialize modules.
     *
     * @return void
     */
    public function initialize(): void
    {
        if ($this->initialized || (!$this->modules && !$this->configProviders)) {
            return;
        }
        $this->loadedModules = [];
        $this->allConfigs = [];

        $this->loadModules();
        $this->updateServiceManager();
        $this->initializeModules();
    }

    /**
     * Load modules.
     *
     * @return void
     */
    public function loadModules(): void
    {
        foreach (array_diff($this->modules, $this->loadedModules) as $moduleName) {
            $this->loadModule($moduleName);
        }
    }

    /**
     * Load a specific module by name.
     *
     * @param string $moduleName Module name
     *
     * @return mixed Module's Module class or ConfigProvider
     */
    public function loadModule(string $moduleName): mixed
    {
        $module = null;
        // Check for a VuFind module first:
        $moduleClass = "$moduleName\Module";
        if (class_exists($moduleClass)) {
            $module = new $moduleClass();
            $this->allConfigs[$moduleName] = $module->getConfig();
            $this->loadedModules[$moduleName] = $module;
        } else {
            // Not a VuFind module; check for ConfigProvider:
            $configProviderClass = "$moduleName\ConfigProvider";
            if (class_exists($configProviderClass)) {
                $configProvider = new $configProviderClass();
                $config = $configProvider();
                // Map configuration from ConfigProvider to Laminas Service Manager:
                if ($dependencies = $config['dependencies'] ?? null) {
                    $config['service_manager'] = $dependencies;
                    unset($config['dependencies']);
                }
                $this->allConfigs[$moduleName] = $config;
                $this->loadedModules[$moduleName] = $configProvider;
            }
        }
        return $module;
    }

    /**
     * Get an array of loaded modules.
     *
     * @param bool $loadModules If true, load modules if they're not already loaded
     *
     * @return array An array of Module objects, keyed by module name
     */
    public function getLoadedModules(bool $loadModules): array
    {
        if ($loadModules) {
            $this->loadModules();
        }
        return $this->loadedModules;
    }

    /**
     * Get the list of modules.
     *
     * @return array
     */
    public function getModules(): array
    {
        return $this->modules;
    }

    /**
     * Set the list of modules.
     *
     * @param array $modules Module names
     *
     * @return static
     */
    public function setModules(array $modules): static
    {
        $this->modules = $modules;
        return $this;
    }

    /**
     * Get merged module configuration.
     *
     * @return array
     */
    public function getMergedConfig(): array
    {
        if ($this->configCacheFile && file_exists($this->configCacheFile)) {
            try {
                return unserialize(file_get_contents($this->configCacheFile));
            } catch (\Exception $e) {
                // Could not read or unserialize the cache file, pass through
            }
        }
        $merged = [];
        foreach ($this->allConfigs as $config) {
            $merged = ArrayUtils::merge($merged, $config);
        }
        if ($this->configCacheFile) {
            file_put_contents($this->configCacheFile, serialize($merged));
        }
        return $merged;
    }

    /**
     * Update service manager with merged module configuration.
     *
     * @return void
     */
    protected function updateServiceManager(): void
    {
        $mergedConfig = $this->getMergedConfig();

        $saveOverride = $this->serviceManager->getAllowOverride();
        $this->serviceManager->setAllowOverride(true);
        $this->serviceManager->configure($mergedConfig['service_manager'] ?? []);
        $this->serviceManager->setService('Config', $mergedConfig);
        $this->serviceManager->setAllowOverride($saveOverride);
    }

    /**
     * Initialize loaded modules.
     *
     * @return void
     */
    protected function initializeModules(): void
    {
        foreach ($this->loadedModules as $module) {
            if ($module instanceof ModuleInterface) {
                $module->initialize($this->serviceManager);
            } elseif (is_callable([$module, 'init'])) {
                $module->init($this);
            }
        }
    }
}
