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
     * Cache for record driver mocks.
     *
     * @var array
     */
    protected $recordDrivers = array();

    /**
     * Test disabled behavior
     *
     * @return void
     */
    public function testDisabled()
    {
        $plugin = new ResultScroller(false);
        $this->assertFalse($plugin->init($this->getMockResults()));
        $expected = array(
            'previousRecord'=>null, 'nextRecord'=>null,
            'currentPosition'=>null, 'resultTotal'=>null
        );
        $this->assertEquals($expected, $plugin->getScrollData($this->getMockRecordDriver('foo')));
    }

    /**
     * Test scrolling for a record in the middle of the page
     *
     * @return void
     */
    public function testScrollingInMiddleOfPage()
    {
        $results = $this->getMockResults(1, 10, 10);
        $plugin = $this->getMockResultScroller();
        $plugin->expects($this->any())->method('restoreLastSearch')->will($this->returnValue($results));
        $this->assertTrue($plugin->init($results));
        $expected = array(
            'previousRecord'=>'VuFind|4', 'nextRecord'=>'VuFind|6',
            'currentPosition'=>5, 'resultTotal'=>10
        );
        $this->assertEquals($expected, $plugin->getScrollData($this->getMockRecordDriver(5)));
    }

    /**
     * Test scrolling for a record at the start of the first page
     *
     * @return void
     */
    public function testScrollingAtStartOfFirstPage()
    {
        $results = $this->getMockResults(1, 10, 10);
        $plugin = $this->getMockResultScroller();
        $plugin->expects($this->any())->method('restoreLastSearch')->will($this->returnValue($results));
        $this->assertTrue($plugin->init($results));
        $expected = array(
            'previousRecord'=>null, 'nextRecord'=>'VuFind|2',
            'currentPosition'=>1, 'resultTotal'=>10
        );
        $this->assertEquals($expected, $plugin->getScrollData($this->getMockRecordDriver(1)));
    }

    /**
     * Test scrolling for a record at the end of the last page (single-page example)
     *
     * @return void
     */
    public function testScrollingAtEndOfLastPage()
    {
        $results = $this->getMockResults(1, 10, 10);
        $plugin = $this->getMockResultScroller();
        $plugin->expects($this->any())->method('restoreLastSearch')->will($this->returnValue($results));
        $this->assertTrue($plugin->init($results));
        $expected = array(
            'previousRecord'=>'VuFind|9', 'nextRecord'=>null,
            'currentPosition'=>10, 'resultTotal'=>10
        );
        $this->assertEquals($expected, $plugin->getScrollData($this->getMockRecordDriver(10)));
    }

    /**
     * Test scrolling for a record at the end of the last page (multi-page example)
     *
     * @return void
     */
    public function testScrollingAtEndOfLastPageInMultiPageScenario()
    {
        $results = $this->getMockResults(2, 10, 17);
        $plugin = $this->getMockResultScroller();
        $plugin->expects($this->any())->method('restoreLastSearch')->will($this->returnValue($results));
        $this->assertTrue($plugin->init($results));
        $expected = array(
            'previousRecord'=>'VuFind|16', 'nextRecord'=>null,
            'currentPosition'=>17, 'resultTotal'=>17
        );
        $this->assertEquals($expected, $plugin->getScrollData($this->getMockRecordDriver(17)));
    }

    /**
     * Get mock record driver
     *
     * @param string $id     ID
     * @param string $source Backend
     *
     * @return \VuFind\RecordDriver\AbstractBase
     */
    protected function getMockRecordDriver($id, $source = 'VuFind')
    {
        if (!isset($this->recordDrivers["$source|$id"])) {
            $driver = $this->getMockBuilder('VuFind\RecordDriver\AbstractBase')
                ->disableOriginalConstructor()
                ->getMock();
            $driver->expects($this->any())->method('getUniqueId')->will($this->returnValue($id));
            $driver->expects($this->any())->method('getResourceSource')->will($this->returnValue($source));
            $this->recordDrivers["$source|$id"] = $driver;
        }
        return $this->recordDrivers["$source|$id"];
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
    protected function getMockResults($page = 0, $limit = 0, $total = 0)
    {
        $results = $this->getMockBuilder('VuFind\Search\Solr\Results')
            ->disableOriginalConstructor()
            ->getMock();
        $results->expects($this->any())->method('getSearchId')->will($this->returnValue('dummy-search-id'));
        $results->expects($this->any())->method('getParams')->will($this->returnValue($this->getMockParams($page, $limit)));
        $results->expects($this->any())->method('getResultTotal')->will($this->returnValue($total));
        $results->expects($this->any())->method('getResults')->will($this->returnValue($this->getResultSet($page, $limit, $total)));
        return $results;
    }

    /**
     * Get mock search params
     *
     * @param int $page  Current page number
     * @param int $limit Page size
     *
     * @return \VuFind\Search\Base\Params
     */
    protected function getMockParams($page = 0, $limit = 0)
    {
        $params = $this->getMockBuilder('VuFind\Search\Solr\Params')
            ->disableOriginalConstructor()
            ->getMock();
        $params->expects($this->any())->method('getPage')->will($this->returnValue($page));
        $params->expects($this->any())->method('getLimit')->will($this->returnValue($limit));
        return $params;
    }

    /**
     * Get mock result scroller
     *
     * @param array $methods Methods to mock
     *
     * @return ResultScroller
     */
    protected function getMockResultScroller($methods = array('restoreLastSearch', 'rememberSearch'))
    {
        return $this->getMock('VuFind\Controller\Plugin\ResultScroller', $methods);
    }

    /**
     * Get set of fake record drivers.
     *
     * @param int $page  Current page number
     * @param int $limit Page size
     * @param int $total Total size of fake result set
     *
     * @return array
     */
    protected function getResultSet($page, $limit, $total)
    {
        $retVal = array();
        for ($i = 1; $i <= $limit; $i++) {
            $current = ($page - 1) * $limit + $i;
            if ($current > $total) {
                break;
            }
            $retVal[] = $this->getMockRecordDriver($current);
        }
        return $retVal;
    }
}