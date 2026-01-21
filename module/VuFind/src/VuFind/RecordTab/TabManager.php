<?php

/**
 * Record tab manager
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2019.
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
 * @package  RecordTabs
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_tabs Wiki
 */

namespace VuFind\RecordTab;

use VuFind\Config\ConfigManagerInterface;
use VuFind\RecordDriver\AbstractBase as AbstractRecordDriver;
use VuFind\RecordTab\PluginManager as RecordTabPluginManager;

use function in_array;

/**
 * Record tab manager
 *
 * @category VuFind
 * @package  RecordTabs
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_tabs Wiki
 */
class TabManager
{
    /**
     * Settings for different tab contexts.
     *
     * @var array
     */
    protected array $contextSettings = [
        'record' => [
            'configFile' => 'RecordTabs',
            'legacyConfigSection' => 'recorddriver_tabs',
        ],
        'collection' => [
            'configFile' => 'CollectionTabs',
            'legacyConfigSection' => 'recorddriver_collection_tabs',
        ],
    ];

    /**
     * Tab configurations
     *
     * @var array
     */
    protected array $config = [];

    /**
     * Overall framework configuration (legacy way for fetching configurations).
     *
     * @var array
     *
     * @deprecated
     */
    protected array $legacyConfig;

    /**
     * Current active context (defaults to 'record')
     *
     * @var string
     */
    protected string $context = 'record';

    /**
     * Constructor
     *
     * @param RecordTabPluginManager $recordTabPluginManager RecordTab plugin manager
     * @param ConfigManagerInterface $configManager          Configuration manager
     * @param array                  $legacyConfig           Overall framework configuration
     * (legacy way for fetching configurations).
     */
    public function __construct(
        protected RecordTabPluginManager $recordTabPluginManager,
        protected ConfigManagerInterface $configManager,
        array $legacyConfig = []
    ) {
        $this->legacyConfig = $legacyConfig;
        // Initialize default context.
        $this->initializeCurrentContext();
    }

    /**
     * Set and (if necessary) initialize the context.
     *
     * @param string $context Context to initialize
     *
     * @return void
     * @throws \Exception
     */
    public function setContext(string $context): void
    {
        if (!in_array($context, array_keys($this->contextSettings))) {
            throw new \Exception("Unsupported context: $context");
        }
        $this->context = $context;
        $this->initializeCurrentContext();
    }

    /**
     * Initialize the current context (if not already initialized).
     *
     * @return void
     */
    protected function initializeCurrentContext(): void
    {
        if (!isset($this->config[$this->context])) {
            $key = $this->contextSettings[$this->context]['legacyConfigSection']
                ?? 'recorddriver_tabs';
            $legacyConfig = $this->legacyConfig['vufind'][$key] ?? [];
            $configFile = $this->contextSettings[$this->context]['configFile'];
            if (!empty($legacyConfig)) {
                trigger_error(
                    'Using deprecated way of fetching tab configuration! ' .
                    'Use ' . $configFile . '.ini instead.',
                    E_USER_WARNING
                );
            }
            $iniConfig = $this->configManager->getConfigArray(
                $configFile
            );
            $this->config[$this->context] = array_merge($legacyConfig, $iniConfig);
        }
    }

    /**
     * Load the specified key from the configuration array using the best
     * available match to the class of the provided driver. Return the default
     * value if no match is found.
     *
     * @param AbstractRecordDriver $driver  Record driver
     * @param string               $setting Key to load from configuration
     * @param array|string|null    $default Default to use if no setting found
     *
     * @return array|string|null
     */
    protected function getConfigByClass(
        AbstractRecordDriver $driver,
        string $setting,
        array|string|null $default
    ): array|string|null {
        // Get the current record driver's class name, then start a loop
        // in case we need to use a parent class' name to find the appropriate
        // setting.
        $className = $driver::class;
        do {
            if (isset($this->config[$this->context][$className][$setting])) {
                return $this->config[$this->context][$className][$setting];
            }
        } while ($className = get_parent_class($className));
        // No setting found...
        return $default;
    }

    /**
     * Get an array of service names by looking up the provided record driver in
     * the provided tab configuration array.
     *
     * @param AbstractRecordDriver $driver Record driver
     *
     * @return array
     */
    protected function getTabServiceNames(AbstractRecordDriver $driver): array
    {
        return $this->getConfigByClass($driver, 'tabs', []);
    }

    /**
     * Get an array of tabs names configured to load via AJAX in the background
     *
     * @param AbstractRecordDriver $driver Record driver
     *
     * @return array
     */
    public function getBackgroundTabNames(AbstractRecordDriver $driver): array
    {
        return $this->getConfigByClass($driver, 'backgroundLoadedTabs', []);
    }

    /**
     * Get an array of extra JS scripts by looking up the provided record driver in
     * the provided tab configuration array.
     *
     * @return array
     */
    public function getExtraScripts(): array
    {
        return $this->config[$this->context]['TabScripts'] ?? [];
    }

    /**
     * Get a default tab by looking up the provided record driver in the tab
     * configuration array.
     *
     * @param AbstractRecordDriver $driver   Record driver
     * @param array                $tabs     Details on available tabs (returned
     * from getTabsForRecord()).
     * @param ?string              $fallback Fallback to use if no tab specified
     * or matched.
     *
     * @return string
     */
    public function getDefaultTabForRecord(
        AbstractRecordDriver $driver,
        array $tabs,
        ?string $fallback = null
    ): string {
        // Load default from module configuration:
        $default = $this->getConfigByClass($driver, 'defaultTab', null);

        // Missing/invalid record driver configuration? Fall back to provided
        // default:
        if ((!$default || !isset($tabs[$default])) && isset($tabs[$fallback])) {
            $default = $fallback;
        }

        // Is configured tab still invalid? If so, pick first existing tab:
        if ((!$default || !isset($tabs[$default])) && !empty($tabs)) {
            $keys = array_keys($tabs);
            $default = $keys[0];
        }

        return $default;
    }

    /**
     * Convenience method to load tab information, including default, in a
     * single pass. Returns an associative array with 'tabs' and 'default' keys.
     *
     * @param AbstractRecordDriver   $driver   Record driver
     * @param ?\Laminas\Http\Request $request  User request (optional)
     * @param ?string                $fallback Fallback default tab to use if no
     * tab specified or matched.
     *
     * @return array
     */
    public function getTabDetailsForRecord(
        AbstractRecordDriver $driver,
        ?\Laminas\Http\Request $request = null,
        ?string $fallback = null
    ): array {
        $tabs = $this->getTabsForRecord($driver, $request);
        $default = $this->getDefaultTabForRecord($driver, $tabs, $fallback);
        return compact('tabs', 'default');
    }

    /**
     * Get an array of valid tabs for the provided record driver.
     *
     * @param AbstractRecordDriver   $driver  Record driver
     * @param ?\Laminas\Http\Request $request User request (optional)
     *
     * @return array               service name => tab object
     */
    public function getTabsForRecord(
        AbstractRecordDriver $driver,
        ?\Laminas\Http\Request $request = null
    ): array {
        $tabs = [];
        foreach ($this->getTabServiceNames($driver) as $tabKey => $svc) {
            if (!$this->recordTabPluginManager->has($svc)) {
                continue;
            }
            $newTab = $this->recordTabPluginManager->get($svc);
            if (method_exists($newTab, 'setRecordDriver')) {
                $newTab->setRecordDriver($driver);
            }
            if (
                $request instanceof \Laminas\Http\Request
                && method_exists($newTab, 'setRequest')
            ) {
                $newTab->setRequest($request);
            }
            if ($newTab->isActive()) {
                $tabs[$tabKey] = $newTab;
            }
        }
        return $tabs;
    }
}
