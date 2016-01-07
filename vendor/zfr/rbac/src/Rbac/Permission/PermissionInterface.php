<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2013 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Rbac\Permission;

/**
 * Interface for permission
 *
 * @deprecated It will be removed from final implementation (likely for Zend Framework 3)
 */
interface PermissionInterface
{
    /**
     * Get the permission name
     *
     * You really must return the name of the permission as internally, the casting to string is used
     * as an optimization to avoid type checkings
     *
     * @return string
     */
    public function __toString();
}
