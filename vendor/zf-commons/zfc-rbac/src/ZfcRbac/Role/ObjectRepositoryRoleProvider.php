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

use Doctrine\Common\Persistence\ObjectRepository;
use ZfcRbac\Exception\RoleNotFoundException;

/**
 * Role provider that uses Doctrine object repository to fetch roles
 *
 * @author  Michaël Gallego <mic.gallego@gmail.com>
 * @licence MIT
 */
class ObjectRepositoryRoleProvider implements RoleProviderInterface
{
    /**
     * @var ObjectRepository
     */
    private $objectRepository;

    /**
     * @var string
     */
    private $roleNameProperty;

    /**
     * @var array
     */
    private $roleCache = [];

    /**
     * Constructor
     *
     * @param ObjectRepository $objectRepository
     * @param string           $roleNameProperty
     */
    public function __construct(ObjectRepository $objectRepository, $roleNameProperty)
    {
        $this->objectRepository = $objectRepository;
        $this->roleNameProperty = $roleNameProperty;
    }

    /**
     * Clears the role cache
     *
     * @return void
     */
    public function clearRoleCache()
    {
        $this->roleCache = [];
    }

    /**
     * {@inheritDoc}
     */
    public function getRoles(array $roleNames)
    {
        $key = implode($roleNames);

        if (isset($this->roleCache[$key])) {
            return $this->roleCache[$key];
        }

        $roles = $this->objectRepository->findBy([$this->roleNameProperty => $roleNames]);

        // We allow more roles to be loaded than asked (although this should not happen because
        // role name should have a UNIQUE constraint in database... but just in case ;))
        if (count($roles) >= count($roleNames)) {
            $this->roleCache[$key] = $roles;

            return $roles;
        }

        // We have roles that were asked but couldn't be found in database... problem!
        foreach ($roles as &$role) {
            $role = $role->getName();
        }

        throw new RoleNotFoundException(sprintf(
            'Some roles were asked but could not be loaded from database: %s',
            implode(', ', array_diff($roleNames, $roles))
        ));
    }
}
