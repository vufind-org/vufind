<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2013 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Rbac\Role;

/**
 * Interface for a flat role
 *
 * The role embeds all the information needed to evaluate if a given role has a given permission
 */
interface RoleInterface
{
    /**
     * Get the name of the role.
     *
     * @return string
     */
    public function getName();

    /**
     * Checks if a permission exists for this role (it does not check child roles)
     *
     * @param  mixed $permission
     * @return bool
     */
    public function hasPermission($permission);
}
