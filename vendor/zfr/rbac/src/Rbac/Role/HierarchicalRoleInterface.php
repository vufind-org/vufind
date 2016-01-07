<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Rbac\Role;

use Traversable;

/**
 * Interface for a hierarchical role
 *
 * A hierarchical role is a role that can have children.
 */
interface HierarchicalRoleInterface extends RoleInterface
{
    /**
     * Check if the role has children
     *
     * @return bool
     */
    public function hasChildren();

    /**
     * Get child roles
     *
     * @return array|RoleInterface[]|Traversable
     */
    public function getChildren();
}
