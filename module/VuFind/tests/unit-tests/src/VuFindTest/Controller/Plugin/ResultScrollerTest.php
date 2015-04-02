<?php

/**
 * ResultScroller controller plugin tests.
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
namespace VuFindTest\Controller\Plugin;

use VuFind\Controller\Plugin\ResultScroller;
use VuFindTest\Unit\TestCase as TestCase;

/**
 * ResultScroller controller plugin tests.
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */
class ResultScrollerTest extends TestCase
{
    /**
     * Test disabled behavior
     *
     * @return void
     */
    public function testDisabled()
    {
        $plugin = new ResultScroller(false);
        $results = $this->getMockResults();
        $this->assertFalse($plugin->init($results));
        $expected = [
            'previousRecord' => null, 'nextRecord' => null,
            'currentPosition' => null, 'resultTotal' => null
        ];
        $this->assertEquals($expected, $plugin->getScrollData($results->getMockRecordDriver(1)));
    }

    /**
     * Test scrolling for a record in the middle of the page
     *
     * @return void
     */
    public function testScrollingInMiddleOfPage()
    {
        $results = $this->getMockResults(1, 10, 10);
        $plugin = $this->getMockResultScroller($results);
        $this->assertTrue($plugin->init($results));
        $expected = [
            'previousRecord' => 'VuFind|4', 'nextRecord' => 'VuFind|6',
            'currentPosition' => 5, 'resultTotal' => 10
        ];
        $this->assertEquals($expected, $plugin->getScrollData($results->getMockRecordDriver(5)));
    }

    /**
     * Test scrolling for a record at the start of the first page
     *
     * @return void
     */
    public function testScrollingAtStartOfFirstPage()
    {
        $results = $this->getMockResults(1, 10, 10);
        $plugin = $this->getMockResultScroller($results);
        $this->assertTrue($plugin->init($results));
        $expected = [
            'previousRecord' => null, 'nextRecord' => 'VuFind|2',
            'currentPosition' => 1, 'resultTotal' => 10
        ];
        $this->assertEquals($expected, $plugin->getScrollData($results->getMockRecordDriver(1)));
    }

    /**
     * Test scrolling for a record at the end of the last page (single-page example)
     *
     * @return void
     */
    public function testScrollingAtEndOfLastPage()
    {
        $results = $this->getMockResults(1, 10, 10);
        $plugin = $this->getMockResultScroller($results);
        $this->assertTrue($plugin->init($results));
        $expected = [
            'previousRecord' => 'VuFind|9', 'nextRecord' => null,
            'currentPosition' => 10, 'resultTotal' => 10
        ];
        $this->assertEquals($expected, $plugin->getScrollData($results->getMockRecordDriver(10)));
    }

    /**
     * Test scrolling for a record at the end of the last page (multi-page example)
     *
     * @return void
     */
    public function testScrollingAtEndOfLastPageInMultiPageScenario()
    {
        $results = $this->getMockResults(2, 10, 17);
        $plugin = $this->getMockResultScroller($results);
        $this->assertTrue($plugin->init($results));
        $expected = [
            'previousRecord' => 'VuFind|16', 'nextRecord' => null,
            'currentPosition' => 17, 'resultTotal' => 17
        ];
        $this->assertEquals($expected, $plugin->getScrollData($results->getMockRecordDriver(17)));
    }

    /**
     * Test scrolling at beginning of middle page.
     *
     * @return void
     */
    public function testScrollingAtStartOfMiddlePage()
    {
        $results = $this->getMockResults(2, 10, 30);
        $plugin = $this->getMockResultScroller($results);
        $this->assertTrue($plugin->init($results));
        $expected = [
            'previousRecord' => 'VuFind|10', 'nextRecord' => 'VuFind|12',
            'currentPosition' => 11, 'resultTotal' => 30
        ];
        $this->assertEquals($expected, $plugin->getScrollData($results->getMockRecordDriver(11)));
    }

    /**
     * Test scrolling at end of middle page.
     *
     * @return void
     */
    public function testScrollingAtEndOfMiddlePage()
    {
        $results = $this->getMockResults(2, 10, 30);
        $plugin = $this->getMockResultScroller($results);
        $this->assertTrue($plugin->init($results));
        $expected = [
            'previousRecord' => 'VuFind|19', 'nextRecord' => 'VuFind|21',
            'currentPosition' => 20, 'resultTotal' => 30
        ];
        $this->assertEquals($expected, $plugin->getScrollData($results->getMockRecordDriver(20)));
    }

    /**
     * Get mock search results
     *
     * @param int $page  Current page number
     * @param int $limit Page size
     * @param int $total Total size of fake result set
     *
     * @return \VuFind\Search\Base\Results
     */
    protected function getMockResults($page = 1, $limit = 20, $total = 0)
    {
        $pm = $this->getMockBuilder('VuFind\Config\PluginManager')->disableOriginalConstructor()->getMock();
        $options = new \VuFindTest\Search\TestHarness\Options($pm);
        $params = new \VuFindTest\Search\TestHarness\Params($options, $pm);
        $params->setPage($page);
        $params->setLimit($limit);
        $results = new \VuFindTest\Search\TestHarness\Results($params, $total);
        return $results;
    }

    /**
     * Get mock result scroller
     *
     * @param \VuFind\Search\Base\Results restoreLastSearch results (null to ignore)
     * @param array                                                                  $methods Methods to mock
     *
     * @return ResultScroller
     */
    protected function getMockResultScroller($results = null, $methods = ['restoreLastSearch', 'rememberSearch'])
    {
        $mock = $this->getMock('VuFind\Controller\Plugin\ResultScroller', $methods);
        if (in_array('restoreLastSearch', $methods) && null !== $results) {
            $mock->expects($this->any())->method('restoreLastSearch')->will($this->returnValue($results));
        }
        return $mock;
    }
}