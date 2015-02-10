<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Rbac\Traversal\Strategy;

use Generator;
use Rbac\Role\HierarchicalRoleInterface;
use Rbac\Role\RoleInterface;
use Traversable;

/**
 * Recursively traverse roles using generator
 * Requires PHP >= 5.5
 */
class GeneratorStrategy implements TraversalStrategyInterface
{
    /**
     * @param  RoleInterface[]|Traversable $roles
     * @return Generator
     */
    public function getRolesIterator($roles)
    {
        foreach ($roles as $role) {
            yield $role;

            if (!$role instanceof HierarchicalRoleInterface) {
                continue;
            }

            $children = $this->getRolesIterator($role->getChildren());

            foreach ($children as $child) {
                yield $child;
            }
        }
    }
}
