<?php

/**
 * Memory unit tests.
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
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Search;

use Laminas\Session\Container;
use VuFind\Search\Memory;

/**
 * Memory unit tests.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class MemoryTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test basic memory.
     *
     * @return void
     */
    public function testBasicMemory()
    {
        $mem = $this->getMemory();
        $this->assertEquals(null, $mem->retrieveSearch());
        $url = 'http://test';
        $mem->rememberSearch($url, -123);
        $this->assertEquals($url, $mem->retrieveSearch());
    }

    /**
     * Test forgetting.
     *
     * @return void
     */
    public function testForgetting()
    {
        $mem = $this->getMemory();
        $url = 'http://test';
        $mem->rememberSearch($url, -123);
        $this->assertEquals($url, $mem->retrieveSearch());
        $mem->forgetSearch();
        $this->assertEquals(null, $mem->retrieveSearch());
    }

    /**
     * Test setting an empty URL.
     *
     * @return void
     */
    public function testEmptyURL()
    {
        $mem = $this->getMemory();
        $mem->rememberSearch('', -123);
        $this->assertEquals(null, $mem->retrieveSearch());
    }

    /**
     * Test disabling the memory.
     *
     * @return void
     */
    public function testDisable()
    {
        $mem = $this->getMemory();
        $url = 'http://test';
        $mem->rememberSearch($url, -123);
        $this->assertEquals($url, $mem->retrieveSearch());
        $mem->disable();
        $mem->rememberSearch('http://ignoreme', -124);
        $this->assertEquals($url, $mem->retrieveSearch());
    }

    /**
     * Create a search memory class
     *
     * @return Memory
     */
    protected function getMemory(): Memory
    {
        $mockRequest = $this->getMockBuilder(
            \Laminas\Http\PhpEnvironment\Request::class
        )->disableOriginalConstructor()
            ->getMock();
        $mockSearchTable = $this->getMockBuilder(\VuFind\Db\Table\Search::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockManager = $this->getMockBuilder(
            \VuFind\Search\Results\PluginManager::class
        )->disableOriginalConstructor()->getMock();
        return new Memory(
            new Container('test'),
            'fake_session',
            $mockRequest,
            $mockSearchTable,
            $mockManager
        );
    }
}
