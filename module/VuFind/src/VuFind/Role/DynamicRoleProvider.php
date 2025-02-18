<?php

/**
 * VuFind dynamic role provider.
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

use LmcRbacMvc\Role\RoleProviderInterface;
use Rbac\Role\Role;

/**
 * VuFind dynamic role provider.
 *
 * @category VuFind
 * @package  Authorization
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class DynamicRoleProvider implements RoleProviderInterface
{
    /**
     * Cache of role objects.
     *
     * @var array
     */
    protected $roles = false;

    /**
     * Constructor
     *
     * @param PermissionProvider\PluginManager $manager Permission provider manager
     * @param array                            $config  Configuration for determining
     * permissions
     */
    public function __construct(
        protected PermissionProvider\PluginManager $manager,
        protected array $config
    ) {
    }

    /**
     * Get the roles from the provider
     *
     * @param string[] $roleNames Role(s) to look up.
     *
     * @return \Rbac\Role\RoleInterface[]
     */
    public function getRoles(array $roleNames)
    {
        return array_map([$this, 'getRole'], $roleNames);
    }

    /**
     * Get a role object by name.
     *
     * @param string $name Role name
     *
     * @return Role
     */
    protected function getRole($name)
    {
        // Retrieve permissions from providers if not already done:
        if (false === $this->roles) {
            $this->populateRoles();
        }

        // Create role object if missing:
        if (!isset($this->roles[$name])) {
            $this->roles[$name] = new Role($name);
        }

        return $this->roles[$name];
    }

    /**
     * Populate the internal role array.
     *
     * @return void
     */
    protected function populateRoles()
    {
        // Reset internal role array:
        $this->roles = [];

        // Map permission configuration to the expected output format.
        foreach ($this->getPermissionsArray() as $roleName => $permissionArr) {
            $role = $this->getRole($roleName);
            foreach ($permissionArr as $permission) {
                $role->addPermission($permission);
            }
        }
    }

    /**
     * Get an associative array of role name => permissions using the provided
     * configuration.
     *
     * @return array
     */
    protected function getPermissionsArray()
    {
        // Loop through all of the permissions:
        $retVal = [];
        foreach ($this->config as $settings) {
            $current = $this->getRolesForSettings($settings);
            if (null !== $current['roles']) {
                foreach ($current['roles'] as $role) {
                    if (!isset($retVal[$role])) {
                        $retVal[$role] = [];
                    }
                    foreach ($current['permissions'] as $permission) {
                        $retVal[$role][] = $permission;
                    }
                }
            }
        }
        return $retVal;
    }

    /**
     * Given a settings array, return the appropriate roles.
     *
     * @param array $settings Settings for finding roles that allow a permission
     *
     * @return array
     */
    protected function getRolesForSettings($settings)
    {
        // Extract require setting:
        if (isset($settings['require'])) {
            $mode = strtoupper(trim($settings['require']));
            unset($settings['require']);
        } else {
            $mode = 'ALL';
        }

        // Extract permission setting:
        $permissions = isset($settings['permission'])
            ? (array)$settings['permission'] : [];
        unset($settings['permission']);

        // Process everything:
        $roles = null;
        foreach ($settings as $provider => $options) {
            $providerObj = $this->manager->get($provider);
            $currentRoles = $providerObj->getPermissions($options);
            if ($roles === null) {
                $roles = $currentRoles;
            } elseif ($mode == 'ANY') {
                $roles = array_merge($roles, $currentRoles);
            } else {
                $roles = array_intersect($roles, $currentRoles);
            }
        }
        return compact('permissions', 'roles');
    }
}
