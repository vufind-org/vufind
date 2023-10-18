<?php

/**
 * Cookie Container Test Class
 *
 * PHP version 8
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Cookie;

use VuFind\Cookie\Container;

use function in_array;

/**
 * Cookie Container Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class ContainerTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Cookie container
     *
     * @var Container
     */
    protected $container;

    /**
     * Setup method: establish Container.
     *
     * @return void
     */
    public function setUp(): void
    {
        $this->container = new Container('test');
    }

    /**
     * Teardown method: empty container.
     *
     * @return void
     */
    public function tearDown(): void
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
        $this->assertCount(2, $this->container->testArray);

        // Test getAllValues:
        $all = $this->container->getAllValues();
        $this->assertCount(2, $all);
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
