<?php

/**
 * Unit tests for SOLR NamedList.
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
use VuFindSearch\Backend\Solr\Response\Json\NamedList;

/**
 * Unit tests for SOLR NamedList.
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class NamedListTest extends TestCase
{
    /**
     * Test iterating over the list.
     *
     * @return void
     */
    public function testIterate()
    {
        $list = new NamedList([['first term', 'info'], ['second term', 'info']]);
        $keys = [];
        foreach ($list as $key => $value) {
            $keys[] = $key;
        }
        $this->assertEquals(['first term', 'second term'], $keys);
    }

    /**
     * Test counting the list.
     *
     * @return void
     */
    public function testCountable()
    {
        $list = new NamedList([['first term', 'info'], ['second term', 'info']]);
        $this->assertCount(2, $list);
    }

    /**
     * Test converting the list to an array.
     *
     * @return void
     */
    public function testToArray()
    {
        $list = new NamedList([['first term', 'info'], ['second term', 'info2']]);
        $this->assertEquals(
            ['first term' => 'info', 'second term' => 'info2'],
            $list->toArray()
        );
    }

    /**
     * Test key removal.
     *
     * @return void
     */
    public function testKeyRemoval()
    {
        $list = new NamedList([['first term', 'info'], ['second term', 'info2']]);
        $list->removeKey('second term');
        $this->assertEquals(['first term' => 'info'], $list->toArray());

        $list = new NamedList(
            [
                ['first term', 'info'],
                ['second term', 'info2'],
                ['third term', 'info3'],
            ]
        );
        $list->removeKeys(['first term', 'second term']);
        $this->assertEquals(['third term' => 'info3'], $list->toArray());
    }

    /**
     * Test multiple key removal.
     *
     * @return void
     */
    public function testMultipleKeyRemoval()
    {
        $list = new NamedList(
            [
                ['first term', 'info'],
                ['second term', 'info2'],
                ['third term', 'info3'],
            ]
        );
        $list->removeKeys(['first term', 'second term']);
        $this->assertEquals(['third term' => 'info3'], $list->toArray());
    }
}
