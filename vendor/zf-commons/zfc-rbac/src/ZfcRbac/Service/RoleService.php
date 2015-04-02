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

namespace ZfcRbac\Service;

use Rbac\Role\HierarchicalRoleInterface;
use Rbac\Role\RoleInterface;
use RecursiveIteratorIterator;
use Traversable;
use ZfcRbac\Exception;
use ZfcRbac\Identity\IdentityInterface;
use ZfcRbac\Identity\IdentityProviderInterface;
use ZfcRbac\Role\RoleProviderInterface;
use Rbac\Traversal\Strategy\TraversalStrategyInterface;

/**
 * Role service
 *
 * @author  Michaël Gallego <mic.gallego@gmail.com>
 * @licence MIT
 */
class RoleService
{
    /**
     * @var IdentityProviderInterface
     */
    protected $identityProvider;

    /**
     * @var RoleProviderInterface
     */
    protected $roleProvider;

    /**
     * @var TraversalStrategyInterface
     */
    protected $traversalStrategy;

    /**
     * @var string
     */
    protected $guestRole = '';

    /**
     * Constructor
     *
     * @param IdentityProviderInterface  $identityProvider
     * @param RoleProviderInterface      $roleProvider
     * @param TraversalStrategyInterface $traversalStrategy
     */
    public function __construct(
        IdentityProviderInterface $identityProvider,
        RoleProviderInterface $roleProvider,
        TraversalStrategyInterface $traversalStrategy
    ) {
        $this->identityProvider  = $identityProvider;
        $this->roleProvider      = $roleProvider;
        $this->traversalStrategy = $traversalStrategy;
    }

    /**
     * Set the guest role
     *
     * @param  string $guestRole
     * @return void
     */
    public function setGuestRole($guestRole)
    {
        $this->guestRole = (string) $guestRole;
    }

    /**
     * Get the guest role
     *
     * @return string
     */
    public function getGuestRole()
    {
        return $this->guestRole;
    }

    /**
     * Get the current identity from the identity provider
     *
     * @return IdentityInterface|null
     */
    public function getIdentity()
    {
        return $this->identityProvider->getIdentity();
    }

    /**
     * Get the identity roles from the current identity, applying some more logic
     *
     * @return RoleInterface[]
     * @throws Exception\RuntimeException
     */
    public function getIdentityRoles()
    {
        if (!$identity = $this->getIdentity()) {
            return $this->convertRoles([$this->guestRole]);
        }

        if (!$identity instanceof IdentityInterface) {
            throw new Exception\RuntimeException(sprintf(
                'ZfcRbac expects your identity to implement ZfcRbac\Identity\IdentityInterface, "%s" given',
                is_object($identity) ? get_class($identity) : gettype($identity)
            ));
        }

        return $this->convertRoles($identity->getRoles());
    }

    /**
     * Check if the given roles match one of the identity roles
     *
     * This method is smart enough to automatically recursively extracts roles for hierarchical roles
     *
     * @param  string[]|RoleInterface[] $roles
     * @return bool
     */
    public function matchIdentityRoles(array $roles)
    {
        $identityRoles = $this->getIdentityRoles();

        // Too easy...
        if (empty($identityRoles)) {
            return false;
        }

        $roleNames = [];

        foreach ($roles as $role) {
            $roleNames[] = $role instanceof RoleInterface ? $role->getName() : (string) $role;
        }

        $identityRoles = $this->flattenRoles($identityRoles);

        return count(array_intersect($roleNames, $identityRoles)) > 0;
    }

    /**
     * Convert the roles (potentially strings) to concrete RoleInterface objects using role provider
     *
     * @param  array|Traversable $roles
     * @return RoleInterface[]
     */
    protected function convertRoles($roles)
    {
        if ($roles instanceof Traversable) {
            $roles = iterator_to_array($roles);
        }

        $collectedRoles = [];
        $toCollect      = [];

        foreach ((array) $roles as $role) {
            // If it's already a RoleInterface, nothing to do as a RoleInterface contains everything already
            if ($role instanceof RoleInterface) {
                $collectedRoles[] = $role;
                continue;
            }

            // Otherwise, it's a string and hence we need to collect it
            $toCollect[] = (string) $role;
        }

        // Nothing to collect, we don't even need to hit the (potentially) costly role provider
        if (empty($toCollect)) {
            return $collectedRoles;
        }

        return array_merge($collectedRoles, $this->roleProvider->getRoles($toCollect));
    }

    /**
     * Flatten an array of role with role names
     *
     * This method iterates through the list of roles, and convert any RoleInterface to a string. For any
     * role, it also extracts all the children
     *
     * @param  array|RoleInterface[] $roles
     * @return string[]
     */
    protected function flattenRoles(array $roles)
    {
        $roleNames = [];
        $iterator  = $this->traversalStrategy->getRolesIterator($roles);

        foreach ($iterator as $role) {
            $roleNames[] = $role->getName();
        }

        return array_unique($roleNames);
    }
}
