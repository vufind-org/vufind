<?php

/**
 * Unit tests for ParamBag.
 *
 * PHP version 7
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
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */

namespace VuFindTest;

use PHPUnit\Framework\TestCase;

use VuFindSearch\ParamBag;

/**
 * Unit tests for ParamBag.
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class ParamBagTest extends TestCase
{
    /**
     * Test "contains"
     *
     * @return void
     */
    public function testContains()
    {
        $bag = new ParamBag();
        $bag->set('foo', 'bar');
        $this->assertTrue($bag->contains('foo', 'bar'));
        $this->assertFalse($bag->contains('bar', 'foo'));
        $this->assertFalse($bag->contains('foo', 'baz'));
    }

    /**
     * Test "hasParam"
     *
     * @return void
     */
    public function testHasParam()
    {
        $bag = new ParamBag();
        $bag->set('foo', 'bar');
        $this->assertTrue($bag->hasParam('foo'));
        $this->assertFalse($bag->hasParam('bar'));
    }

    /**
     * Test "remove"
     *
     * @return void
     */
    public function testRemove()
    {
        $bag = new ParamBag();
        $bag->set('foo', 'bar');
        $bag->set('bar', 'baz');
        $bag->remove('foo');
        $this->assertEquals(['bar' => ['baz']], $bag->getArrayCopy());
    }

    /**
     * Test "merge with all"
     *
     * @return void
     */
    public function testMergeWithAll()
    {
        $bag1 = new ParamBag(['a' => 1]);
        $bag2 = new ParamBag(['b' => 2]);
        $bag3 = new ParamBag(['c' => 3]);
        $bag3->mergeWithAll([$bag1, $bag2]);
        $this->assertEquals(['a' => [1], 'b' => [2], 'c' => [3]], $bag3->getArrayCopy());
    }

    /**
     * Test countability.
     *
     * @return void
     */
    public function testCountability()
    {
        $bag = new ParamBag();
        $this->assertEquals(0, count($bag));
        $bag->set('foo', 'bar');
        $this->assertEquals(1, count($bag));
        $bag->set('xyzzy', 'baz');
        $this->assertEquals(2, count($bag));
    }
}
