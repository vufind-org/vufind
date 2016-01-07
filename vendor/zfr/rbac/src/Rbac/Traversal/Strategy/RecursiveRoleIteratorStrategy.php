<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Rbac\Traversal\Strategy;

use Rbac\Role\RoleInterface;
use Rbac\Traversal\RecursiveRoleIterator;
use RecursiveIteratorIterator;

/**
 * Create a {@link RecursiveRoleIterator} and wrap it into a {@link RecursiveIteratorIterator}
 */
class RecursiveRoleIteratorStrategy implements TraversalStrategyInterface
{
    /**
     * @param  RoleInterface[]           $roles
     * @return RecursiveIteratorIterator
     */
    public function getRolesIterator($roles)
    {
        return new RecursiveIteratorIterator(
            new RecursiveRoleIterator($roles),
            RecursiveIteratorIterator::SELF_FIRST
        );
    }
}
