<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Rbac;

use Rbac\Role\RoleInterface;
use Rbac\Traversal\Strategy\TraversalStrategyInterface;
use Traversable;

/**
 * Rbac object. It is used to check a permission against roles
 */
class Rbac
{
    /**
     * @var TraversalStrategyInterface
     */
    protected $traversalStrategy;

    /**
     * @param TraversalStrategyInterface $strategy
     */
    public function __construct(TraversalStrategyInterface $strategy)
    {
        $this->traversalStrategy = $strategy;
    }

    /**
     * Determines if access is granted by checking the roles for permission.
     *
     * @param  RoleInterface|RoleInterface[]|Traversable $roles
     * @param  mixed                                     $permission
     * @return bool
     */
    public function isGranted($roles, $permission)
    {
        if ($roles instanceof RoleInterface) {
            $roles = [$roles];
        }

        $iterator = $this->traversalStrategy->getRolesIterator($roles);

        foreach ($iterator as $role) {
            /* @var RoleInterface $role */
            if ($role->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the strategy.
     *
     * @return TraversalStrategyInterface
     */
    public function getTraversalStrategy()
    {
        return $this->traversalStrategy;
    }
}
