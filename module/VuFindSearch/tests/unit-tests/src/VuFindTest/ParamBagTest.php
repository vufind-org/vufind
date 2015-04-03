<?php

/**
 * Unit tests for ParamBag.
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
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */
namespace VuFindTest;

use VuFindSearch\ParamBag;

use PHPUnit_Framework_TestCase as TestCase;

/**
 * Unit tests for ParamBag.
 *
 * @category VuFind2
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */
class ParamBagTest extends TestCase
{
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
}