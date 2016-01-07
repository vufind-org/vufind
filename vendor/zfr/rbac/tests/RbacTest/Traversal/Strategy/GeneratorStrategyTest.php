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
use Rbac\Role\HierarchicalRole;
use Rbac\Role\Role;
use Rbac\Traversal\Strategy\GeneratorStrategy;

/**
 * @requires PHP 5.5.0
 * @covers   Rbac\Traversal\Strategy\GeneratorStrategy
 * @group    Coverage
 */
class GeneratorStrategyTest extends TestCase
{
    /**
     * @covers Rbac\Traversal\Strategy\GeneratorStrategy::getRolesIterator
     */
    public function testTraverseFlatRoles()
    {
        $strategy = new GeneratorStrategy;
        $roles    = [new Role('Foo'), new Role('Bar')];

        $this->assertEquals(
            $roles,
            iterator_to_array($strategy->getRolesIterator($roles))
        );
    }

    /**
     * @covers Rbac\Traversal\Strategy\GeneratorStrategy::getRolesIterator
     */
    public function testTraverseHierarchicalRoles()
    {
        $strategy = new GeneratorStrategy;

        $child1 = new Role('child 1');
        $child2 = new Role('child 2');
        $child3 = new Role('child 3');

        $parent1 = new HierarchicalRole('parent 1');
        $parent1->addChild($child1);

        $parent2 = new HierarchicalRole('parent 2');
        $parent2->addChild($child2);

        $parent3 = new HierarchicalRole('parent 3');
        $parent3->addChild($child3);

        $roles = [$parent1, $parent2, $parent3];

        $expectedResult = [
            $parent1, $child1,
            $parent2, $child2,
            $parent3, $child3,
        ];

        $this->assertEquals(
            $expectedResult,
            iterator_to_array($strategy->getRolesIterator($roles))
        );
    }
}
