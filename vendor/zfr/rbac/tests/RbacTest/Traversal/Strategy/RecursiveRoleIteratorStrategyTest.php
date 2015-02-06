<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace RbacTest\Traversal\Strategy;

use PHPUnit_Framework_TestCase as TestCase;
use Rbac\Role\Role;
use Rbac\Traversal\Strategy\RecursiveRoleIteratorStrategy;

/**
 * @covers Rbac\Traversal\Strategy\RecursiveRoleIteratorStrategy
 * @group  Coverage
 */
class RecursiveRoleIteratorStrategyTest extends TestCase
{
    /**
     * @covers Rbac\Traversal\Strategy\RecursiveRoleIteratorStrategy::getRolesIterator
     */
    public function testGetIterator()
    {
        $strategy      = new RecursiveRoleIteratorStrategy;
        $roles         = [new Role('Foo'), new Role('Bar')];
        $iterator      = $strategy->getRolesIterator($roles);
        $innerIterator = $iterator->getInnerIterator();

        $this->assertInstanceOf('RecursiveIteratorIterator', $iterator);
        $this->assertInstanceOf('Rbac\Traversal\RecursiveRoleIterator', $innerIterator);
        $this->assertEquals($roles, $innerIterator->getArrayCopy());
    }
}
