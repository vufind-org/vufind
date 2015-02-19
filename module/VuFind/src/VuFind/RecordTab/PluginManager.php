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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  RecordTabs
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_tabs Wiki
 */
namespace VuFind\RecordTab;

/**
 * Record tab plugin manager
 *
 * @category VuFind2
 * @package  RecordTabs
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_tabs Wiki
 */
class PluginManager extends \VuFind\ServiceManager\AbstractPluginManager
{
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
     * @param \VuFind\RecordDriver\AbstractBase $driver Record driver
     * @param array                             $config Tab configuration
     * (associative array including 'tabs' array mapping driver class => tab service
     * name)
     *
     * @return array
     */
    protected function getTabServiceNames(\VuFind\RecordDriver\AbstractBase $driver,
        array $config
    ) {
        // Get the current record driver's class name, then start a loop
        // in case we need to use a parent class' name to find the appropriate
        // setting.
        $className = get_class($driver);
        while (true) {
            if (isset($config[$className]['tabs'])) {
                return $config[$className]['tabs'];
            }
            $className = get_parent_class($className);
            if (empty($className)) {
                // No setting found...
                return [];
            }
        }
    }

    /**
     * Get an array of valid tabs for the provided record driver.
     *
     * @param \VuFind\RecordDriver\AbstractBase $driver  Record driver
     * @param array                             $config  Tab configuration (map of
     * driver class => tab service name
     * @param \Zend\Http\Request                $request User request (optional)
     *
     * @return array                                    service name => tab object
     */
    public function getTabsForRecord(\VuFind\RecordDriver\AbstractBase $driver,
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