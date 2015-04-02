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

namespace ZfcRbac\Collector;

use Rbac\Role\HierarchicalRoleInterface;
use Rbac\Role\RoleInterface;
use Rbac\Traversal\RecursiveRoleIterator;
use RecursiveIteratorIterator;
use ReflectionProperty;
use Serializable;
use Traversable;
use Zend\Mvc\MvcEvent;
use ZendDeveloperTools\Collector\CollectorInterface;
use ZfcRbac\Options\ModuleOptions;
use ZfcRbac\Service\RoleService;

/**
 * RbacCollector
 *
 * @author  Michaël Gallego <mic.gallego@gmail.com>
 * @licence MIT
 */
class RbacCollector implements CollectorInterface, Serializable
{
    /**
     * Collector priority
     */
    const PRIORITY = -100;

    /**
     * @var array
     */
    protected $collection = [];

    /**
     * @var array
     */
    protected $collectedGuards = [];

    /**
     * @var array
     */
    protected $collectedRoles = [];

    /**
     * @var array
     */
    protected $collectedPermissions = [];

    /**
     * @var array
     */
    protected $collectedOptions = [];

    /**
     * Collector Name.
     *
     * @return string
     */
    public function getName()
    {
        return 'zfc_rbac';
    }

    /**
     * Collector Priority.
     *
     * @return integer
     */
    public function getPriority()
    {
        return self::PRIORITY;
    }

    /**
     * Collects data.
     *
     * @param MvcEvent $mvcEvent
     */
    public function collect(MvcEvent $mvcEvent)
    {
        if (!$application = $mvcEvent->getApplication()) {
            return;
        }

        $serviceManager = $application->getServiceManager();

        /* @var \ZfcRbac\Service\RoleService $roleService */
        $roleService = $serviceManager->get('ZfcRbac\Service\RoleService');

        /* @var \ZfcRbac\Options\ModuleOptions $options */
        $options = $serviceManager->get('ZfcRbac\Options\ModuleOptions');

        // Start collect all the data we need!
        $this->collectOptions($options);
        $this->collectGuards($options->getGuards());
        $this->collectIdentityRolesAndPermissions($roleService);
    }

    /**
     * Collect options
     *
     * @param  ModuleOptions $moduleOptions
     * @return void
     */
    private function collectOptions(ModuleOptions $moduleOptions)
    {
        $this->collectedOptions = [
            'guest_role'        => $moduleOptions->getGuestRole(),
            'protection_policy' => $moduleOptions->getProtectionPolicy()
        ];
    }

    /**
     * Collect guards
     *
     * @param  array $guards
     * @return void
     */
    private function collectGuards($guards)
    {
        $this->collectedGuards = [];

        foreach ($guards as $type => $rules) {
            $this->collectedGuards[$type] = $rules;
        }
    }

    /**
     * Collect roles and permissions
     *
     * @param  RoleService $roleService
     * @return void
     */
    private function collectIdentityRolesAndPermissions(RoleService $roleService)
    {
        $identityRoles = $roleService->getIdentityRoles();

        foreach ($identityRoles as $role) {
            $roleName = $role->getName();

            if (!$role instanceof HierarchicalRoleInterface) {
                $this->collectedRoles[] = $roleName;
            } else {
                $iteratorIterator = new RecursiveIteratorIterator(
                    new RecursiveRoleIterator($role->getChildren()),
                    RecursiveIteratorIterator::SELF_FIRST
                );

                foreach ($iteratorIterator as $childRole) {
                    $this->collectedRoles[$roleName][] = $childRole->getName();
                    $this->collectPermissions($childRole);
                }
            }

            $this->collectPermissions($role);
        }
    }

    /**
     * Collect permissions for the given role
     *
     * @param  RoleInterface $role
     * @return void
     */
    private function collectPermissions(RoleInterface $role)
    {
        // Gather the permissions for the given role. We have to use reflection as
        // the RoleInterface does not have "getPermissions" method
        $reflectionProperty = new ReflectionProperty($role, 'permissions');
        $reflectionProperty->setAccessible(true);

        $permissions = $reflectionProperty->getValue($role);

        if ($permissions instanceof Traversable) {
            $permissions = iterator_to_array($permissions);
        }

        array_walk($permissions, function (&$permission) {
            $permission = (string) $permission;
        });

        $this->collectedPermissions[$role->getName()] = array_values($permissions);
    }

    /**
     * @return array|string[]
     */
    public function getCollection()
    {
        return $this->collection;
    }

    /**
     * {@inheritDoc}
     */
    public function serialize()
    {
        return serialize([
            'guards'      => $this->collectedGuards,
            'roles'       => $this->collectedRoles,
            'permissions' => $this->collectedPermissions,
            'options'     => $this->collectedOptions
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function unserialize($serialized)
    {
        $this->collection = unserialize($serialized);
    }
}
