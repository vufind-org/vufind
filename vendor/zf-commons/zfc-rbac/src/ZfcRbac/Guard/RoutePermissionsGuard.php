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
namespace ZfcRbac\Guard;

use Zend\Mvc\MvcEvent;
use ZfcRbac\Exception\InvalidArgumentException;
use ZfcRbac\Service\AuthorizationServiceInterface;

/**
 * A route guard can protect a route or a hierarchy of routes (using simple wildcard pattern)
 *
 * @author  Michaël Gallego <mic.gallego@gmail.com>
 * @author  JM Leroux <jmleroux.pro@gmail.com>
 * @licence MIT
 */
class RoutePermissionsGuard extends AbstractGuard
{
    use ProtectionPolicyTrait;

    const EVENT_PRIORITY = -8;

    /**
     * @var AuthorizationServiceInterface
     */
    protected $authorizationService;

    /**
     * Route guard rules
     * Those rules are an associative array that map a rule with one or multiple permissions
     * @var array
     */
    protected $rules = [];

    /**
     * @param AuthorizationServiceInterface $authorizationService
     * @param array $rules
     */
    public function __construct(AuthorizationServiceInterface $authorizationService, array $rules = [])
    {
        $this->authorizationService = $authorizationService;
        $this->setRules($rules);
    }

    /**
     * Set the rules (it overrides any existing rules)
     *
     * @param  array $rules
     * @return void
     */
    public function setRules(array $rules)
    {
        $this->rules = [];
        foreach ($rules as $key => $value) {
            if (is_int($key)) {
                $routeRegex  = $value;
                $permissions = [];
            } else {
                $routeRegex  = $key;
                $permissions = (array) $value;
            }
            $this->rules[$routeRegex] = $permissions;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function isGranted(MvcEvent $event)
    {
        $matchedRouteName = $event->getRouteMatch()->getMatchedRouteName();
        $allowedPermissions = null;

        foreach (array_keys($this->rules) as $routeRule) {
            if (fnmatch($routeRule, $matchedRouteName, FNM_CASEFOLD)) {
                $allowedPermissions = $this->rules[$routeRule];
                break;
            }
        }

        // If no rules apply, it is considered as granted or not based on the protection policy
        if (null === $allowedPermissions) {
            return $this->protectionPolicy === self::POLICY_ALLOW;
        }

        if (in_array('*', $allowedPermissions)) {
            return true;
        }

        $permissions = isset($allowedPermissions['permissions'])
            ? $allowedPermissions['permissions']
            : $allowedPermissions;

        $condition   = isset($allowedPermissions['condition'])
            ? $allowedPermissions['condition']
            : GuardInterface::CONDITION_AND;

        if (GuardInterface::CONDITION_AND === $condition) {
            foreach ($permissions as $permission) {
                if (!$this->authorizationService->isGranted($permission)) {
                    return false;
                }
            }

            return true;
        }

        if (GuardInterface::CONDITION_OR === $condition) {
            foreach ($permissions as $permission) {
                if ($this->authorizationService->isGranted($permission)) {
                    return true;
                }
            }

            return false;
        }

        throw new InvalidArgumentException(sprintf(
            'Condition must be either "AND" or "OR", %s given',
            is_object($condition) ? get_class($condition) : gettype($condition)
        ));
    }
}
