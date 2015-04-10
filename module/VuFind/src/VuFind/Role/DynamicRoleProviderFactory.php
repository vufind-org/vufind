<?php
/**
 * VuFind dynamic role provider factory.
 *
 * PHP version 5
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Authorization
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
namespace VuFind\Role;
use Zend\ServiceManager\Config;
use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

/**
 * VuFind dynamic role provider factory.
 *
 * @category VuFind2
 * @package  Authorization
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class DynamicRoleProviderFactory implements FactoryInterface
{
    /**
     * Create the service.
     *
     * @param ServiceLocatorInterface $serviceLocator Service locator
     *
     * @return DynamicRoleProvider
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        $config = $serviceLocator->getServiceLocator()->get('config');
        $rbacConfig = $config['zfc_rbac'];
        return new DynamicRoleProvider(
            $this->getPermissionProviderPluginManager($serviceLocator, $rbacConfig),
            $this->getPermissionConfiguration($serviceLocator, $rbacConfig)
        );
    }

    /**
     * Create the supporting plugin manager.
     *
     * @param ServiceLocatorInterface $serviceLocator Service locator
     * @param array                   $rbacConfig     ZfcRbac configuration
     *
     * @return PermissionProviderPluginManager
     */
    protected function getPermissionProviderPluginManager(
        ServiceLocatorInterface $serviceLocator, array $rbacConfig
    ) {
        $pm = new PermissionProvider\PluginManager(
            new Config($rbacConfig['vufind_permission_provider_manager'])
        );
        $pm->setServiceLocator($serviceLocator->getServiceLocator());
        return $pm;
    }

    /**
     * Get a configuration array.
     *
     * @param ServiceLocatorInterface $serviceLocator Service locator
     * @param array                   $rbacConfig     ZfcRbac configuration
     *
     * @return array
     */
    protected function getPermissionConfiguration(
        ServiceLocatorInterface $serviceLocator, array $rbacConfig
    ) {
        // Get role provider settings from the ZfcRbac configuration:
        $config = $rbacConfig['role_provider']['VuFind\Role\DynamicRoleProvider'];

        // Load the permissions:
        $configLoader = $serviceLocator->getServiceLocator()->get('VuFind\Config');
        $permissions = $configLoader->get('permissions')->toArray();

        // If we're configured to map legacy settings, do so now:
        if (isset($config['map_legacy_settings'])
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
    protected function addLegacySettings(\VuFind\Config\PluginManager $loader,
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
            if (isset($current['permission'])
                && in_array($permission, (array)$current['permission'])
            ) {
                return true;
            }
        }
        return false;
    }
}
