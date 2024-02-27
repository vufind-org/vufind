<?php

/**
 * Unit tests for spellcheck information.
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

namespace VuFindTest\Backend\Solr\Json\Response;

use PHPUnit\Framework\TestCase;
use VuFindSearch\Backend\Solr\Response\Json\Spellcheck;

/**
 * Unit tests for spellcheck information.
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class SpellcheckTest extends TestCase
{
    /**
     * Test merge.
     *
     * @return void
     */
    public function testMerge()
    {
        $s1 = new Spellcheck(
            [
                ['this is a phrase', []],
                ['foo', []],
                ['foobar', []],
                ['1842', []],   // test numeric handling (can cause problems)
            ],
            'fake query'
        );
        $s2 = new Spellcheck(
            [
                ['is a', []],
                ['bar', []],
                ['foo bar', []],
                ['1842', []],
                ['1843', []],
            ],
            'fake query'
        );
        $s1->mergeWith($s2);
        $this->assertCount(7, $s1);
        $this->assertEquals($s2, $s1->getSecondary());
        $this->assertEquals(
            [
                'this is a phrase' => [],
                'foobar' => [],
                'foo' => [],
                'bar' => [],
                'foo bar' => [],
                '1842' => [],
                '1843' => [],
            ],
            iterator_to_array($s1->getIterator())
        );
    }

    /**
     * Test double merge.
     *
     * @return void
     */
    public function testDoubleMerge()
    {
        $s1 = new Spellcheck([['a', []]], 'fake');
        $s2 = new Spellcheck([['b', []]], 'fake');
        $s3 = new Spellcheck([['c', []]], 'fake');
        $s1->mergeWith($s2);
        $s1->mergeWith($s3);
        $this->assertCount(3, $s1);
        $this->assertCount(2, $s1->getSecondary());
        $this->assertCount(1, $s1->getSecondary()->getSecondary());
    }

    /**
     * Test exact duplication.
     *
     * @return void
     */
    public function testExactDuplication()
    {
        $s1 = new Spellcheck([['a', []]], 'fake');
        $s2 = new Spellcheck([['a', []]], 'fake');
        $s1->mergeWith($s2);
        $this->assertCount(1, $s1);
    }

    /**
     * Test getQuery()
     *
     * @return void
     */
    public function testGetQuery()
    {
        $s = new Spellcheck([], 'test');
        $this->assertEquals('test', $s->getQuery());
    }
}
