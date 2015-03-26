<?php
/**
 * Cookie Container Test Class
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */
namespace VuFindTest\Cookie;
use VuFind\Cookie\Container;

/**
 * Cookie Container Test Class
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */
class ContainerTest extends \VuFindTest\Unit\TestCase
{
    protected $ns;

    /**
     * Setup method: establish Container.
     *
     * @return void
     */
    public function setup()
    {
        $this->container = new Container('test');
    }

    /**
     * Teardown method: empty container.
     *
     * @return void
     */
    public function tearDown()
    {
        foreach ($this->container->getAllValues() as $k => $v) {
            unset($this->container->$k);
        }
    }

    /**
     * Test getters and setters
     *
     * @return void
     */
    public function testSettersAndGetters()
    {
        // Test get/set of single value:
        $this->container->value = 'tmp';
        $this->assertEquals('tmp', $this->container->value);

        // Test get/set of array:
        $this->container->testArray = [1, 2];
        $this->assertEquals(2, count($this->container->testArray));

        // Test getAllValues:
        $all = $this->container->getAllValues();
        $this->assertEquals(2, count($all));
        $this->assertTrue(in_array('value', array_keys($all)));
        $this->assertTrue(in_array('testArray', array_keys($all)));
    }

    /**
     * Test isset/unset
     *
     * @return void
     */
    public function testIssetAndUnset()
    {
        // Test isset before setting a value:
        $this->assertFalse(isset($this->container->value));

        // Test isset after setting a value:
        $this->container->value = true;
        $this->assertTrue(isset($this->container->value));

        // Test that unset works correctly:
        unset($this->container->value);
        $this->assertFalse(isset($this->container->value));
    }
}