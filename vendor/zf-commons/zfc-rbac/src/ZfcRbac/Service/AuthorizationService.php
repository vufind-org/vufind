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

use Rbac\Rbac;
use Rbac\Permission\PermissionInterface;
use ZfcRbac\Assertion\AssertionPluginManager;
use ZfcRbac\Assertion\AssertionInterface;
use ZfcRbac\Exception;
use ZfcRbac\Identity\IdentityInterface;

/**
 * Authorization service is a simple service that internally uses Rbac to check if identity is
 * granted a permission
 *
 * @author  Michaël Gallego <mic.gallego@gmail.com>
 * @licence MIT
 */
class AuthorizationService implements AuthorizationServiceInterface
{
    /**
     * @var Rbac
     */
    protected $rbac;

    /**
     * @var RoleService
     */
    protected $roleService;

    /**
     * @var AssertionPluginManager
     */
    protected $assertionPluginManager;

    /**
     * @var array
     */
    protected $assertions = [];

    /**
     * Constructor
     *
     * @param Rbac                   $rbac
     * @param RoleService            $roleService
     * @param AssertionPluginManager $assertionPluginManager
     */
    public function __construct(Rbac $rbac, RoleService $roleService, AssertionPluginManager $assertionPluginManager)
    {
        $this->rbac                   = $rbac;
        $this->roleService            = $roleService;
        $this->assertionPluginManager = $assertionPluginManager;
    }

    /**
     * Set an assertion
     *
     * @param string|PermissionInterface         $permission
     * @param string|callable|AssertionInterface $assertion
     * @return void
     */
    public function setAssertion($permission, $assertion)
    {
        $this->assertions[(string) $permission] = $assertion;
    }

    /**
     * Set assertions
     *
     * @param array $assertions
     * @return void
     */
    public function setAssertions(array $assertions)
    {
        $this->assertions = $assertions;
    }

    /**
     * Checks if a assertion exists
     *
     * @param string|PermissionInterface $permission
     * @return bool
     */
    public function hasAssertion($permission)
    {
        return isset($this->assertions[(string) $permission]);
    }

    /**
     * Get the current identity from the role service
     *
     * @return IdentityInterface|null
     */
    public function getIdentity()
    {
        return $this->roleService->getIdentity();
    }

    /**
     * Check if the permission is granted to the current identity
     *
     * @param string|PermissionInterface $permission
     * @param mixed                      $context
     * @return bool
     */
    public function isGranted($permission, $context = null)
    {
        $roles = $this->roleService->getIdentityRoles();

        if (empty($roles)) {
            return false;
        }

        if (!$this->rbac->isGranted($roles, $permission)) {
            return false;
        }

        if ($this->hasAssertion($permission)) {
            return $this->assert($this->assertions[(string) $permission], $context);
        }

        return true;
    }

    /**
     * @param  string|callable|AssertionInterface $assertion
     * @param  mixed                              $context
     * @return bool
     * @throws Exception\InvalidArgumentException If an invalid assertion is passed
     */
    protected function assert($assertion, $context = null)
    {
        if (is_callable($assertion)) {
            return $assertion($this, $context);
        } elseif ($assertion instanceof AssertionInterface) {
            return $assertion->assert($this, $context);
        } elseif (is_string($assertion)) {
            $assertion = $this->assertionPluginManager->get($assertion);

            return $assertion->assert($this, $context);
        }

        throw new Exception\InvalidArgumentException(sprintf(
            'Assertion must be callable, string or implement ZfcRbac\Assertion\AssertionInterface, "%s" given',
            is_object($assertion) ? get_class($assertion) : gettype($assertion)
        ));
    }
}
