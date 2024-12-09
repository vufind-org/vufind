<?php

/**
 * VuFind dynamic role provider factory.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2007.
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
 * @package  Authorization
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Role;

use Laminas\ServiceManager\Config;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

use function in_array;

/**
 * VuFind dynamic role provider factory.
 *
 * @category VuFind
 * @package  Authorization
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class DynamicRoleProviderFactory implements FactoryInterface
{
    /**
     * Create service
     *
     * @param ContainerInterface $container Service container
     * @param string             $name      Requested service name (unused)
     * @param array              $options   Extra options (unused)
     *
     * @return object
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $name, array $options = null)
    {
        $config = $container->get('config');
        return new $name(
            $container->get(PermissionProvider\PluginManager::class),
            $this->getPermissionConfiguration($container, $config['lmc_rbac'])
        );
    }

    /**
     * Get a configuration array.
     *
     * @param ContainerInterface $container  Service container
     * @param array              $rbacConfig LmcRbacMvc configuration
     *
     * @return array
     */
    protected function getPermissionConfiguration(
        ContainerInterface $container,
        array $rbacConfig
    ) {
        // Get role provider settings from the LmcRbacMvc configuration:
        $config = $rbacConfig['role_provider']['VuFind\Role\DynamicRoleProvider'];

        // Load the permissions:
        $configLoader = $container->get(\VuFind\Config\PluginManager::class);
        $permissions = $configLoader->get('permissions')->toArray();

        // If we're configured to map legacy settings, do so now:
        if (
            isset($config['map_legacy_settings'])
            && $config['map_legacy_settings']
        ) {
            $permissions = $this->addLegacySettings($configLoader, $permissions);
        }

        return $permissions;
    }

    /**
     * Map legacy VuFind settings into the permissions.ini setup.
     *
     * @param \VuFind\Config\PluginManager $loader      Config loader
     * @param array                        $permissions Permissions to update
     *
     * @return array
     */
    protected function addLegacySettings(
        \VuFind\Config\PluginManager $loader,
        array $permissions
    ) {
        // Add admin settings if they are absent:
        if (!$this->permissionDefined($permissions, 'access.AdminModule')) {
            $config = $loader->get('config')->toArray();
            $permissions['legacy.AdminModule'] = [];
            if (isset($config['AdminAuth']['ipRegEx'])) {
                $permissions['legacy.AdminModule']['ipRegEx']
                    = $config['AdminAuth']['ipRegEx'];
            }
            if (isset($config['AdminAuth']['userWhitelist'])) {
                $permissions['legacy.AdminModule']['username']
                    = $config['AdminAuth']['userWhitelist'];
            }
            // If no settings exist in config.ini, we grant access to everyone
            // by allowing both logged-in and logged-out roles.
            if (empty($permissions['legacy.AdminModule'])) {
                $permissions['legacy.AdminModule']['role'] = ['guest', 'loggedin'];
            }
            $permissions['legacy.AdminModule']['permission'] = 'access.AdminModule';
        }

        // Add staff view setting it they are absent:
        if (!$this->permissionDefined($permissions, 'access.StaffViewTab')) {
            $permissions['legacy.StaffViewTab']['role'] = ['guest', 'loggedin'];
            $permissions['legacy.StaffViewTab']['permission']
                = 'access.StaffViewTab';
        }

        // Add EIT settings if they are absent:
        if (!$this->permissionDefined($permissions, 'access.EITModule')) {
            $permissions['legacy.EITModule'] = [
                'role' => 'loggedin',
                'permission' => 'access.EITModule',
            ];
        }

        // Add Summon settings if they are absent:
        // Check based on login status
        $defined = $this
            ->permissionDefined($permissions, 'access.SummonExtendedResults');
        if (!$defined) {
            $config = $loader->get('Summon');
            $permissions['legacy.SummonExtendedResults'] = [];
            if (isset($config->Auth->check_login) && $config->Auth->check_login) {
                $permissions['legacy.SummonExtendedResults']['role'] = ['loggedin'];
            }
            if (isset($config->Auth->ip_range)) {
                $permissions['legacy.SummonExtendedResults']['ipRegEx']
                    = $config->Auth->ip_range;
            }
            if (!empty($permissions['legacy.SummonExtendedResults'])) {
                $permissions['legacy.SummonExtendedResults']['require'] = 'ANY';
                $permissions['legacy.SummonExtendedResults']['permission']
                    = 'access.SummonExtendedResults';
            } else {
                unset($permissions['legacy.SummonExtendedResults']);
            }
        }

        return $permissions;
    }

    /**
     * Is the specified permission already defined in the provided configuration?
     *
     * @param array  $config     Configuration
     * @param string $permission Permission to check
     *
     * @return bool
     */
    protected function permissionDefined(array $config, $permission)
    {
        foreach ($config as $current) {
            if (
                isset($current['permission'])
                && in_array($permission, (array)$current['permission'])
            ) {
                return true;
            }
        }
        return false;
    }
}
