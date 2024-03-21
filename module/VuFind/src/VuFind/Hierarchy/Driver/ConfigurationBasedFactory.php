<?php

/**
 * Hierarchy Driver Factory Class
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
 * @package  Hierarchy_Drivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 */

namespace VuFind\Hierarchy\Driver;

use Psr\Container\ContainerInterface;

/**
 * Hierarchy Driver Factory Class
 *
 * This is a factory class to build objects for managing hierarchies.
 *
 * @category VuFind
 * @package  Hierarchy_Drivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 *
 * @codeCoverageIgnore
 */
class ConfigurationBasedFactory
{
    /**
     * This constructs a hierarchy driver using VuFind's service setup.
     *
     * @param ContainerInterface $container     Service container
     * @param string             $requestedName Service being built
     * @param array|null         $options       Name of driver class
     *
     * @return object
     *
     * @throws \Exception if options is populated
     */
    public function __invoke(
        ContainerInterface $container,
        $requestedName,
        array $options = null
    ) {
        if (!empty($options)) {
            throw new \Exception('Unexpected options passed to factory.');
        }
        // Get config name from requestedName
        $parts = explode('\\', $requestedName);
        $config = end($parts);
        // Set up options based on global VuFind settings:
        $configReader = $container->get(\VuFind\Config\PluginManager::class);
        $globalConfig = $configReader->get('config');
        $options = [
            'enabled' => $globalConfig->Hierarchy->showTree ?? false,
        ];

        // Load driver-specific configuration:
        $driverConfig = $configReader->get($config);

        // Build object:
        return new ConfigurationBased(
            $driverConfig,
            $container->get(\VuFind\Hierarchy\TreeDataSource\PluginManager::class),
            $container->get(\VuFind\Hierarchy\TreeRenderer\PluginManager::class),
            $options
        );
    }
}
