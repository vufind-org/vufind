<?php
/**
 * Record tab plugin manager
 *
 * PHP version 5
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
 * @package  RecordTabs
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_tabs Wiki
 */
namespace VuFind\RecordTab;
use VuFind\RecordDriver\AbstractBase as AbstractRecordDriver;

/**
 * Record tab plugin manager
 *
 * @category VuFind
 * @package  RecordTabs
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_tabs Wiki
 */
class PluginManager extends \VuFind\ServiceManager\AbstractPluginManager
{
    /**
     * Load the specified key from the configuration array using the best
     * available match to the class of the provided driver. Return the default
     * value if no match is found.
     *
     * @param AbstractRecordDriver $driver  Record driver
     * @param array                $config  Tab configuration (map of
     * driver class => tab configuration)
     * @param string               $setting Key to load from configuration
     * @param string               $default Default to use if no setting found
     *
     * @return mixed
     */
    protected function getConfigByClass(AbstractRecordDriver $driver,
        array $config, $setting, $default
    ) {
        // Get the current record driver's class name, then start a loop
        // in case we need to use a parent class' name to find the appropriate
        // setting.
        $className = get_class($driver);
        do {
            if (isset($config[$className][$setting])) {
                return $config[$className][$setting];
            }
        } while ($className = get_parent_class($className));
        // No setting found...
        return $default;
    }

    /**
     * Return the name of the base class or interface that plug-ins must conform
     * to.
     *
     * @return string
     */
    protected function getExpectedInterface()
    {
        return 'VuFind\RecordTab\TabInterface';
    }

    /**
     * Get an array of service names by looking up the provided record driver in
     * the provided tab configuration array.
     *
     * @param AbstractRecordDriver $driver Record driver
     * @param array                $config Tab configuration (associative array
     * including 'tabs' array mapping driver class => tab service name)
     *
     * @return array
     */
    protected function getTabServiceNames(AbstractRecordDriver $driver,
        array $config
    ) {
        return $this->getConfigByClass($driver, $config, 'tabs', []);
    }

    /**
     * Get an array of tabs names configured to load via AJAX in the background
     *
     * @param AbstractRecordDriver $driver Record driver
     * @param array                $config Tab configuration (associative array
     * including 'tabs' array mapping driver class => tab service name)
     *
     * @return array
     */
    public function getBackgroundTabNames(AbstractRecordDriver $driver,
        array $config
    ) {
        return $this->getConfigByClass($driver, $config, 'backgroundLoadedTabs', []);
    }

    /**
     * Get a default tab by looking up the provided record driver in the tab
     * configuration array.
     *
     * @param AbstractRecordDriver $driver   Record driver
     * @param array                $config   Tab configuration (map of
     * driver class => tab configuration)
     * @param array                $tabs     Details on available tabs (returned
     * from getTabsForRecord()).
     * @param string               $fallback Fallback to use if no tab specified
     * or matched.
     *
     * @return string
     */
    public function getDefaultTabForRecord(AbstractRecordDriver $driver,
        array $config, array $tabs, $fallback = null
    ) {
        // Load default from module configuration:
        $default = $this->getConfigByClass($driver, $config, 'defaultTab', null);

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
     * @param AbstractRecordDriver $driver   Record driver
     * @param array                $config   Tab configuration (map of
     * driver class => tab configuration)
     * @param \Zend\Http\Request   $request  User request (optional)
     * @param string               $fallback Fallback default tab to use if no
     * tab specified or matched.
     *
     * @return array
     */
    public function getTabDetailsForRecord(AbstractRecordDriver $driver,
        array $config, $request = null, $fallback = null
    ) {
        $tabs = $this->getTabsForRecord($driver, $config, $request);
        $default = $this->getDefaultTabForRecord($driver, $config, $tabs, $fallback);
        return compact('tabs', 'default');
    }

    /**
     * Get an array of valid tabs for the provided record driver.
     *
     * @param AbstractRecordDriver $driver  Record driver
     * @param array                $config  Tab configuration (map of
     * driver class => tab configuration)
     * @param \Zend\Http\Request   $request User request (optional)
     *
     * @return array               service name => tab object
     */
    public function getTabsForRecord(AbstractRecordDriver $driver,
        array $config, $request = null
    ) {
        $tabs = [];
        foreach ($this->getTabServiceNames($driver, $config) as $tabKey => $svc) {
            if (!$this->has($svc)) {
                continue;
            }
            $newTab = $this->get($svc);
            if (method_exists($newTab, 'setRecordDriver')) {
                $newTab->setRecordDriver($driver);
            }
            if ($request instanceof \Zend\Http\Request
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
