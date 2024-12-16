<?php

/**
 * Unit tests for ParamBag.
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
        $this->assertCount(0, $bag);
        $bag->set('foo', 'bar');
        $this->assertCount(1, $bag);
        $bag->set('xyzzy', 'baz');
        $this->assertCount(2, $bag);
    }

    /**
     * Test deduplication
     *
     * @return void
     */
    public function testDeduplication()
    {
        $bag = new ParamBag();
        $bag->add('foo', 'bar');
        $bag->add('foo', 'bar');
        $bag->add('foo', ['bar', 'bar', 'bar']);
        $this->assertEquals(['bar'], $bag->get('foo'));
        $bag->add('foo', ['bar', 'baz', 'bar', 'baz']);
        $this->assertEquals(['bar', 'baz'], $bag->get('foo'));
        // Associative arrays are not deduplicated:
        $bag->add('fooz', ['bar' => 'baz']);
        $bag->add('fooz', ['bar' => 'baz']);
        $bag->add('fooz', ['bar' => 'haz']);
        $this->assertEquals(['bar' => ['baz', 'baz', 'haz']], $bag->get('fooz'));
    }

    /**
     * Test disabling deduplication
     *
     * @return void
     */
    public function testDisabledDeduplication()
    {
        $bag = new ParamBag();
        $bag->add('foo', 'bar', false);
        $bag->add('foo', 'bar', false);
        $bag->add('foo', ['bar', 'bar', 'bar'], false);
        $this->assertEquals(['bar', 'bar', 'bar', 'bar', 'bar'], $bag->get('foo'));
        $bag->add('foo', ['bar', 'baz', 'bar', 'baz'], false);
        $this->assertEquals(['bar', 'bar', 'bar', 'bar', 'bar', 'bar', 'baz', 'bar', 'baz'], $bag->get('foo'));
        // Now deduplicate everything:
        $bag->add('foo', 'bar');
        $this->assertEquals(['bar', 'baz'], $bag->get('foo'));
    }
}
