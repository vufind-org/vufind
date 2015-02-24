<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace RbacTest;

use PHPUnit_Framework_TestCase as TestCase;
use Rbac\Role\HierarchicalRole;

/**
 * @covers Rbac\Role\HierarchicalRole
 * @group  Coverage
 */
class HierarchicalRoleTest extends TestCase
{
    /**
     * @covers Rbac\Role\HierarchicalRole::addChild
     */
    public function testCanAddChild()
    {
        $role  = new HierarchicalRole('role');
        $child = new HierarchicalRole('child');

        $role->addChild($child);

        $this->assertCount(1, $role->getChildren());
    }

    /**
     * @covers Rbac\Role\HierarchicalRole::hasChildren
     */
    public function testHasChildren()
    {
        $role = new HierarchicalRole('role');

        $this->assertFalse($role->hasChildren());

        $role->addChild(new HierarchicalRole('child'));

        $this->assertTrue($role->hasChildren());
    }

    /**
     * @covers Rbac\Role\HierarchicalRole::getChildren
     */
    public function testCanGetChildren()
    {
        $role   = new HierarchicalRole('role');
        $child1 = new HierarchicalRole('child 1');
        $child2 = new HierarchicalRole('child 2');

        $role->addChild($child1);
        $role->addChild($child2);

        $children = $role->getChildren();

        $this->assertCount(2, $children);
        $this->assertContainsOnlyInstancesOf('Rbac\Role\HierarchicalRole', $children);
    }
}
