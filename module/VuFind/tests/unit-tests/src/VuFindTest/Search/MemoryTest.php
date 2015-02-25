<?php

/**
 * Memory unit tests.
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
namespace VuFindTest\Search;

use VuFind\Search\Memory;
use VuFindTest\Unit\TestCase as TestCase;

/**
 * Memory unit tests.
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */
class MemoryTest extends TestCase
{
    /**
     * Test basic memory.
     *
     * @return void
     */
    public function testBasicMemory()
    {
        $mem = new Memory();
        $this->assertEquals(null, $mem->retrieve());
        $url = 'http://test';
        $mem->rememberSearch($url);
        $this->assertEquals($url, $mem->retrieve());
    }

    /**
     * Test forgetting.
     *
     * @return void
     */
    public function testForgetting()
    {
        $mem = new Memory();
        $url = 'http://test';
        $mem->rememberSearch($url);
        $this->assertEquals($url, $mem->retrieve());
        $mem->forgetSearch();
        $this->assertEquals(null, $mem->retrieve());
    }

    /**
     * Test setting an empty URL.
     *
     * @return void
     */
    public function testEmptyURL()
    {
        $mem = new Memory();
        $mem->rememberSearch('');
        $this->assertEquals(null, $mem->retrieve());
    }

    /**
     * Test disabling the memory.
     *
     * @return void
     */
    public function testDisable()
    {
        $mem = new Memory();
        $url = 'http://test';
        $mem->rememberSearch($url);
        $this->assertEquals($url, $mem->retrieve());
        $mem->disable();
        $mem->rememberSearch('http://ignoreme');
        $this->assertEquals($url, $mem->retrieve());
    }
}