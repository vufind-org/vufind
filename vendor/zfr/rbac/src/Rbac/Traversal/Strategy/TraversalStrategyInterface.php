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
use Traversable;

/**
 * Interface for a traversal strategy
 */
interface TraversalStrategyInterface
{
    /**
     * @param  RoleInterface[]|Traversable
     * @return Traversable
     */
    public function getRolesIterator($roles);
}
