<?php
/**
 * Hierarchy Factory Class
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
 * @author   Luke O'Sullivan <l.osullivan@swansea.ac.uk>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:hierarchy_components Wiki
 */
namespace VuFind\Hierarchy\Driver;

/**
 * Hierarchy Factory Class
 *
 * This is a factory class to build objects for managing hierarchies.
 *
 * @category VuFind2
 * @package  Hierarchy_Drivers
 * @author   Luke O'Sullivan <l.osullivan@swansea.ac.uk>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:hierarchy_components Wiki
 */
class Factory
{
    /**
     * This constructs a hierarchy driver using VuFind's service setup.
     *
     * @param \Zend\ServiceManager\ServiceManager $sm     Service manager
     * @param string                              $config Name of config to load
     * @param string                              $class  Name of driver class
     *
     * @return object
     */
    public static function get(\Zend\ServiceManager\ServiceManager $sm, $config,
        $class = 'VuFind\Hierarchy\Driver\ConfigurationBased'
    ) {
        // Set up options based on global VuFind settings:
        $globalConfig = \VuFind\Config\Reader::getConfig();
        $options = array(
            'enabled' => isset($globalConfig->Hierarchy->showTree)
                ? $globalConfig->Hierarchy->showTree : false
        );

        // Load driver-specific configuration:
        $driverConfig = \VuFind\Config\Reader::getConfig($config);

        // Build object:
        return new $class(
            $driverConfig,
            $sm->get('VuFind\HierarchyTreeDataSourcePluginManager'),
            $sm->get('VuFind\HierarchyTreeRendererPluginManager'),
            $options
        );
    }
}