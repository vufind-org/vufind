<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace RbacTest;

use ArrayIterator;
use PHPUnit_Framework_TestCase as TestCase;
use Rbac\Rbac;
use Rbac\Role\Role;
use Rbac\Traversal\Strategy\RecursiveRoleIteratorStrategy;

/**
 * @covers Rbac\Rbac
 * @group  Coverage
 */
class RbacTest extends TestCase
{
    /**
     * @covers Rbac\Rbac::__construct
     */
    public function testConstructorAcceptCustomTraversalStrategy()
    {
        $customStrategy = $this->getMock('Rbac\Traversal\Strategy\TraversalStrategyInterface');
        $rbac           = new Rbac($customStrategy);

        $this->assertAttributeSame($customStrategy, 'traversalStrategy', $rbac);
    }

    /**
     * @covers Rbac\Rbac::isGranted
     */
    public function testInjectSingleRoleToArray()
    {
        $role = new Role('Foo');

        $traversalStrategy = $this->getMock('Rbac\Traversal\Strategy\TraversalStrategyInterface');
        $traversalStrategy->expects($this->once())
            ->method('getRolesIterator')
            ->with($this->equalTo([$role]))
            ->will($this->returnValue(new ArrayIterator([])));

        $rbac = new Rbac($traversalStrategy);

        $rbac->isGranted($role, 'permission');
    }

    /**
     * @covers Rbac\Rbac::isGranted
     */
    public function testFetchIteratorFromTraversalStrategy()
    {
        $traversalStrategy = $this->getMock('Rbac\Traversal\Strategy\TraversalStrategyInterface');
        $traversalStrategy->expects($this->once())
            ->method('getRolesIterator')
            ->will($this->returnValue(new ArrayIterator([])));

        $rbac = new Rbac($traversalStrategy);

        $rbac->isGranted([], 'permission');
    }

    /**
     * @covers Rbac\Rbac::isGranted
     */
    public function testTraverseRoles()
    {
        $role = $this->getMock('Rbac\Role\RoleInterface');
        $role->expects($this->exactly(3))
            ->method('hasPermission')
            ->with($this->equalTo('permission'))
            ->will($this->returnValue(false));

        $roles = [$role, $role, $role];
        $rbac  = new Rbac(new RecursiveRoleIteratorStrategy());

        $rbac->isGranted($roles, 'permission');
    }

    /**
     * @covers Rbac\Rbac::isGranted
     */
    public function testReturnTrueWhenRoleHasPermission()
    {
        $grantedRole = $this->getMock('Rbac\Role\RoleInterface');
        $grantedRole->expects($this->once())
            ->method('hasPermission')
            ->with('permission')
            ->will($this->returnValue(true));

        $nextRole = $this->getMock('Rbac\Role\RoleInterface');
        $nextRole->expects($this->never())->method('hasPermission');

        $roles = [$grantedRole, $nextRole];
        $rbac  = new Rbac(new RecursiveRoleIteratorStrategy());

        $this->assertTrue($rbac->isGranted($roles, 'permission'));
    }

    public function testReturnFalseIfNoRoleHasPermission()
    {
        $roles = [new Role('Foo'), new Role('Bar')];
        $rbac  = new Rbac(new RecursiveRoleIteratorStrategy());

        $this->assertFalse($rbac->isGranted($roles, 'permission'));
    }

    public function testGetTraversalStrategy()
    {
        $customStrategy = $this->getMock('Rbac\Traversal\Strategy\TraversalStrategyInterface');
        $rbac           = new Rbac($customStrategy);

        $this->assertSame($customStrategy, $rbac->getTraversalStrategy());
    }
}
