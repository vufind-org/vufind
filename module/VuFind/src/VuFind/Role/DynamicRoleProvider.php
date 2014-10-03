<?php
/**
 * VuFind dynamic role provider.
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
use ZfcRbac\Role\RoleProviderInterface;
use Rbac\Role\Role;

/**
 * VuFind dynamic role provider.
 *
 * @category VuFind2
 * @package  Authorization
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
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
     * @param PermissionProviderPluginManager $manager   Permission provider manager
     * @param array                           $providers Configured providers
     */
    public function __construct(PermissionProviderPluginManager $manager,
        array $providers
    ) {
        $this->manager = $manager;
        $this->providers = $providers;
    }

    /**
     * Get the roles from the provider
     *
     * @param  string[] $roleNames Role(s) to look up.
     *
     * @return \Rbac\Role\RoleInterface[]
     */
    public function getRoles(array $roleNames)
    {
        return array_map(array($this, 'getRole'), $roleNames);
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
        $this->roles = array();

        // Load permissions from providers:
        foreach ($this->providers as $providerName) {
            $provider = $this->manager->get($providerName);
            $permissions = $provider->getPermissions();
            foreach ($permissions as $roleName => $permissionArr) {
                $role = $this->getRole($roleName);
                foreach ($permissionArr as $permission) {
                    $role->addPermission($permission);
                }
            }
        }
    }
}
