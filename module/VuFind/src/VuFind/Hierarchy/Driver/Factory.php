<?php
/**
 * Hierarchy Driver Factory Class
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
 * @package  Hierarchy_Drivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:hierarchy_components Wiki
 */
namespace VuFind\Hierarchy\Driver;
use Zend\ServiceManager\ServiceManager;

/**
 * Hierarchy Driver Factory Class
 *
 * This is a factory class to build objects for managing hierarchies.
 *
 * @category VuFind2
 * @package  Hierarchy_Drivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:hierarchy_components Wiki
 *
 * @codeCoverageIgnore
 */
class Factory
{
    /**
     * This constructs a hierarchy driver using VuFind's service setup.
     *
     * @param \Zend\ServiceManager\ServiceManager $sm     Top-level service manager
     * @param string                              $config Name of config to load
     * @param string                              $class  Name of driver class
     *
     * @return object
     */
    public static function get(ServiceManager $sm, $config,
        $class = 'VuFind\Hierarchy\Driver\ConfigurationBased'
    ) {
        // Set up options based on global VuFind settings:
        $configReader = $sm->get('VuFind\Config');
        $globalConfig = $configReader->get('config');
        $options = [
            'enabled' => isset($globalConfig->Hierarchy->showTree)
                ? $globalConfig->Hierarchy->showTree : false
        ];

        // Load driver-specific configuration:
        $driverConfig = $configReader->get($config);

        // Build object:
        return new $class(
            $driverConfig,
            $sm->get('VuFind\HierarchyTreeDataSourcePluginManager'),
            $sm->get('VuFind\HierarchyTreeRendererPluginManager'),
            $options
        );
    }

    /**
     * Factory for HierarchyDefault to be called from module.config.php.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return HierarchyDefault
     */
    public static function getHierarchyDefault(ServiceManager $sm)
    {
        return static::get($sm->getServiceLocator(), 'HierarchyDefault');
    }

    /**
     * Factory for HierarchyFlat to be called from module.config.php.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return HierarchyFlat
     */
    public static function getHierarchyFlat(ServiceManager $sm)
    {
        return static::get($sm->getServiceLocator(), 'HierarchyFlat');
    }
}