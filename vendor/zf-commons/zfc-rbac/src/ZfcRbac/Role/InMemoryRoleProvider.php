<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the MIT license.
 */

namespace ZfcRbac\Role;

use Rbac\Role\HierarchicalRole;
use Rbac\Role\Role;

/**
 * Simple role providers that store them in memory (ideal for small websites)
 *
 * This provider expects role to be specified using string only. The format is as follow:
 *
 *  [
 *      'myRole' => [
 *          'children'    => ['subRole1', 'subRole2'], // OPTIONAL
 *          'permissions' => ['permission1'] // OPTIONAL
 *      ]
 *  ]
 *
 * For maximum performance, this provider DOES NOT do a lot of type check, so you must closely
 * follow the format :)
 *
 * @author  Michaël Gallego <mic.gallego@gmail.com>
 * @licence MIT
 */
class InMemoryRoleProvider implements RoleProviderInterface
{
    /**
     * @var array
     */
    private $rolesConfig = [];

    /**
     * @param array
     */
    public function __construct(array $rolesConfig)
    {
        $this->rolesConfig = $rolesConfig;
    }

    /**
     * {@inheritDoc}
     */
    public function getRoles(array $roleNames)
    {
        $roles = [];

        foreach ($roleNames as $roleName) {
            // If no config, we create a simple role with no permission
            if (!isset($this->rolesConfig[$roleName])) {
                $roles[] = new Role($roleName);
                continue;
            }

            $roleConfig = $this->rolesConfig[$roleName];

            if (isset($roleConfig['children'])) {
                $role       = new HierarchicalRole($roleName);
                $childRoles = (array) $roleConfig['children'];

                foreach ($this->getRoles($childRoles) as $childRole) {
                    $role->addChild($childRole);
                }
            } else {
                $role = new Role($roleName);
            }

            $permissions = isset($roleConfig['permissions']) ? $roleConfig['permissions'] : [];

            foreach ($permissions as $permission) {
                $role->addPermission($permission);
            }

            $roles[] = $role;
        }

        return $roles;
    }
}
