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
     * Permission provider manager
     *
     * @var PermissionProviderPluginManager
     */
    protected $manager;

    /**
     * Configuration for determining permissions.
     *
     * @var array
     */
    protected $config;

    /**
     * Constructor
     *
     * @param PermissionProvider\PluginManager $manager Permission provider manager
     * @param array                            $config  Configuration for determining
     * permissions
     */
    public function __construct(PermissionProvider\PluginManager $manager,
        array $config
    ) {
        $this->manager = $manager;
        $this->config = $config;
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
        // First expand the configuration to account for inheritance:
        $config = $this->getInheritedConfigurationArray();

        // Now loop through all of the permissions:
        $retVal = [];
        foreach ($config as $permission => $settings) {
            foreach ($this->getRolesForSettings($settings) as $role) {
                if (!isset($retVal[$role])) {
                    $retVal[$role] = [];
                }
                $retVal[$role][] = $permission;
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
        if (isset($settings['boolean'])) {
            $mode = strtoupper(trim($settings['boolean']));
            unset($settings['boolean']);
        } else {
            $mode = 'AND';
        }
        $roles = null;
        foreach ($settings as $provider => $options) {
            $providerObj = $this->manager->get($provider);
            $currentRoles = $providerObj->getPermissions($options);
            if ($roles === null) {
                $roles = $currentRoles;
            } else if ($mode == 'OR') {
                $roles = array_merge($roles, $currentRoles);
            } else {
                $roles = array_intersect($roles, $currentRoles);
            }
        }
        return $roles;
    }

    /**
     * Retrieve the stored configuration with inheritance applied.
     *
     * @return void
     */
    protected function getInheritedConfigurationArray()
    {
        $retVal = [];
        foreach (array_keys($this->config) as $permission) {
            $retVal[$permission] = $this->getInheritedConfiguration($permission);
        }
        return $retVal;
    }

    /**
     * Process inheritance for a single permission.
     *
     * @param string $permission Name of permission to process.
     * @param array  $stack      Stack of inherited permissions, used to avoid
     * infinite loops.
     *
     * @throws \Exception
     * @return array
     */
    protected function getInheritedConfiguration($permission, $stack = [])
    {
        // Not defined? Empty array.
        if (!isset($this->config[$permission])) {
            return [];
        }

        // No inheritance? Return as-is.
        if (!isset($this->config[$permission]['inherit'])) {
            return $this->config[$permission];
        }

        // Assemble using inheritance:
        if (in_array($parent, $stack)) {
            throw new \Exception('Inheritance loop detected!');
        }
        $stack[] = $permission;
        $base = $this->getInheritedConfiguration($parent, $stack);
        foreach ($this->config[$permission] as $key => $value) {
            $base[$key] = $value;
        }
        unset($base['inherit']);
        return $base;
    }
}
