<?php

/**
 * Unit tests for SOLR NamedList.
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
namespace VuFindTest\Backend\Solr\Json\Response;

use VuFindSearch\Backend\Solr\Response\Json\NamedList;
use PHPUnit_Framework_TestCase as TestCase;

/**
 * Unit tests for SOLR NamedList.
 *
 * @category VuFind2
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
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
            $keys [] = $key;
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
        $this->assertEquals(2, count($list));
    }

    /**
     * Test converting the list to an array.
     *
     * @return void
     */
    public function testToArray()
    {
        $list = new NamedList([['first term', 'info'], ['second term', 'info2']]);
        $this->assertEquals(['first term' => 'info', 'second term' => 'info2'], $list->toArray());
    }
}