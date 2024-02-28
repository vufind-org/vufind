<?php

/**
 * ResultScroller controller plugin tests.
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
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Controller\Plugin;

use Laminas\Session\Container;
use VuFind\Controller\Plugin\ResultScroller;

/**
 * ResultScroller controller plugin tests.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class ResultScrollerTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\ConfigPluginManagerTrait;

    /**
     * Test next_prev_nav bug
     * Expect next_prev to behave like it's disabled if the last search didn't return
     * any results
     *
     * @return void
     */
    public function testNextPrevNavBug()
    {
        $results = $this->getMockResults(0, 0, 0, true, 'sorted');
        $plugin = $this->getMockResultScroller($results);
        $this->assertFalse($plugin->init($results));

        $expected = [
            'firstRecord' => null, 'lastRecord' => null,
            'previousRecord' => null, 'nextRecord' => null,
            'currentPosition' => null, 'resultTotal' => null,
        ];

        $this->assertEquals(
            $expected,
            $plugin->getScrollData(
                $results->getMockRecordDriver('sorted20')
            )
        );
    }

    /**
     * Test disabled behavior
     *
     * @return void
     */
    public function testDisabled()
    {
        $mockManager
            = $this->getMockBuilder(\VuFind\Search\Results\PluginManager::class)
            ->disableOriginalConstructor()->getMock();
        $mockMemory = $this->getMockBuilder(\VuFind\Search\Memory::class)
            ->disableOriginalConstructor()->getMock();
        $plugin = new ResultScroller(
            new Container('test'),
            $mockManager,
            $mockMemory,
            true
        );
        $results = $this->getMockResults();
        $this->assertFalse($plugin->init($results));
        $expected = [
            'firstRecord' => null, 'lastRecord' => null,
            'previousRecord' => null, 'nextRecord' => null,
            'currentPosition' => null, 'resultTotal' => null,
        ];
        $this->assertEquals(
            $expected,
            $plugin->getScrollData($results->getMockRecordDriver(1))
        );
    }

    /**
     * Test scrolling on single-record set
     *
     * @return void
     */
    public function testScrollingOnSingleRecord()
    {
        $results = $this->getMockResults(1, 10, 1);
        $plugin = $this->getMockResultScroller($results);
        $this->assertTrue($plugin->init($results));
        $expected = [
            'firstRecord' => 'Solr|1', 'lastRecord' => 'Solr|1',
            'previousRecord' => null, 'nextRecord' => null,
            'currentPosition' => 1, 'resultTotal' => 1,
        ];
        $this->assertEquals(
            $expected,
            $plugin->getScrollData($results->getMockRecordDriver(1))
        );
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
            'firstRecord' => 'Solr|1', 'lastRecord' => 'Solr|10',
            'previousRecord' => 'Solr|4', 'nextRecord' => 'Solr|6',
            'currentPosition' => 5, 'resultTotal' => 10,
        ];
        $this->assertEquals(
            $expected,
            $plugin->getScrollData($results->getMockRecordDriver(5))
        );
    }

    /**
     * Test scrolling to the first record in a set.
     *
     * @return void
     */
    public function testScrollingToFirstRecord()
    {
        $results = $this->getMockResults(5, 2, 10);
        $plugin = $this->getMockResultScroller($results);
        $this->assertTrue($plugin->init($results));
        $expected = [
            'firstRecord' => 'Solr|1', 'lastRecord' => 'Solr|10',
            'previousRecord' => null, 'nextRecord' => 'Solr|2',
            'currentPosition' => 1, 'resultTotal' => 10,
        ];
        $this->assertEquals(
            $expected,
            $plugin->getScrollData($results->getMockRecordDriver(1))
        );
    }

    /**
     * Test scrolling to the first record in a set (with page size set to 1).
     *
     * @return void
     */
    public function testScrollingToFirstRecordWithPageSize1()
    {
        $results = $this->getMockResults(10, 1, 10);
        $plugin = $this->getMockResultScroller($results);
        $this->assertTrue($plugin->init($results));
        $expected = [
            'firstRecord' => 'Solr|1', 'lastRecord' => 'Solr|10',
            'previousRecord' => null, 'nextRecord' => 'Solr|2',
            'currentPosition' => 1, 'resultTotal' => 10,
        ];
        $this->assertEquals(
            $expected,
            $plugin->getScrollData($results->getMockRecordDriver(1))
        );
    }

    /**
     * Test scrolling to the last record in a set (with multiple records on the
     * last page of results).
     *
     * @return void
     */
    public function testScrollingToLastRecord()
    {
        $results = $this->getMockResults(1, 2, 10);
        $plugin = $this->getMockResultScroller($results);
        $this->assertTrue($plugin->init($results));
        $expected = [
            'firstRecord' => 'Solr|1', 'lastRecord' => 'Solr|10',
            'previousRecord' => 'Solr|9', 'nextRecord' => null,
            'currentPosition' => 10, 'resultTotal' => 10,
        ];
        $this->assertEquals(
            $expected,
            $plugin->getScrollData($results->getMockRecordDriver(10))
        );
    }

    /**
     * Test scrolling to the last record in a set (with only one record on the
     * last page of results).
     *
     * @return void
     */
    public function testScrollingToLastRecordAcrossPageBoundaries()
    {
        $results = $this->getMockResults(1, 2, 9);
        $plugin = $this->getMockResultScroller($results);
        $this->assertTrue($plugin->init($results));
        $expected = [
            'firstRecord' => 'Solr|1', 'lastRecord' => 'Solr|9',
            'previousRecord' => 'Solr|8', 'nextRecord' => null,
            'currentPosition' => 9, 'resultTotal' => 9,
        ];
        $this->assertEquals(
            $expected,
            $plugin->getScrollData($results->getMockRecordDriver(9))
        );
    }

    /**
     * Test that first/last results can be disabled (this is the same as the
     * testScrollingInMiddleOfPage() test, but with first/last setting off).
     *
     * @return void
     */
    public function testDisabledFirstLast()
    {
        $results = $this->getMockResults(1, 10, 10, false);
        $plugin = $this->getMockResultScroller($results);
        $this->assertTrue($plugin->init($results));
        $expected = [
            'firstRecord' => null, 'lastRecord' => null,
            'previousRecord' => 'Solr|4', 'nextRecord' => 'Solr|6',
            'currentPosition' => 5, 'resultTotal' => 10,
        ];
        $this->assertEquals(
            $expected,
            $plugin->getScrollData($results->getMockRecordDriver(5))
        );
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
            'firstRecord' => 'Solr|1', 'lastRecord' => 'Solr|10',
            'previousRecord' => null, 'nextRecord' => 'Solr|2',
            'currentPosition' => 1, 'resultTotal' => 10,
        ];
        $this->assertEquals(
            $expected,
            $plugin->getScrollData($results->getMockRecordDriver(1))
        );
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
            'firstRecord' => 'Solr|1', 'lastRecord' => 'Solr|10',
            'previousRecord' => 'Solr|9', 'nextRecord' => null,
            'currentPosition' => 10, 'resultTotal' => 10,
        ];
        $this->assertEquals(
            $expected,
            $plugin->getScrollData($results->getMockRecordDriver(10))
        );
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
            'firstRecord' => 'Solr|1', 'lastRecord' => 'Solr|17',
            'previousRecord' => 'Solr|16', 'nextRecord' => null,
            'currentPosition' => 17, 'resultTotal' => 17,
        ];
        $this->assertEquals(
            $expected,
            $plugin->getScrollData($results->getMockRecordDriver(17))
        );
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
            'firstRecord' => 'Solr|1', 'lastRecord' => 'Solr|30',
            'previousRecord' => 'Solr|10', 'nextRecord' => 'Solr|12',
            'currentPosition' => 11, 'resultTotal' => 30,
        ];
        $this->assertEquals(
            $expected,
            $plugin->getScrollData($results->getMockRecordDriver(11))
        );
    }

    /**
     * Get a configuration array to turn on first/last setting.
     *
     * @return array
     */
    protected function getFirstLastConfig()
    {
        return ['Record' => ['first_last_navigation' => true]];
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
            'firstRecord' => 'Solr|1', 'lastRecord' => 'Solr|30',
            'previousRecord' => 'Solr|19', 'nextRecord' => 'Solr|21',
            'currentPosition' => 20, 'resultTotal' => 30,
        ];
        $this->assertEquals(
            $expected,
            $plugin->getScrollData($results->getMockRecordDriver(20))
        );
    }

    /**
     * Test scrolling at end of middle page with sorting.
     *
     * @return void
     */
    public function testScrollingAtEndOfMiddlePageWithSorting()
    {
        $results = $this->getMockResults(2, 10, 30, true, 'sorted');
        $plugin = $this->getMockResultScroller($results);
        $this->assertTrue($plugin->init($results));
        $expected = [
            'firstRecord' => 'Solr|sorted1', 'lastRecord' => 'Solr|sorted30',
            'previousRecord' => 'Solr|sorted19', 'nextRecord' => 'Solr|sorted21',
            'currentPosition' => 20, 'resultTotal' => 30,
        ];
        $this->assertEquals(
            $expected,
            $plugin->getScrollData(
                $results->getMockRecordDriver('sorted20')
            )
        );
    }

    /**
     * Get mock search results
     *
     * @param int    $page      Current page number
     * @param int    $limit     Page size
     * @param int    $total     Total size of fake result set
     * @param bool   $firstLast Turn on first/last config?
     * @param string $sort      Sort type (null for default)
     *
     * @return \VuFindTest\Search\TestHarness\Results
     */
    protected function getMockResults(
        $page = 1,
        $limit = 20,
        $total = 0,
        $firstLast = true,
        $sort = null
    ): \VuFindTest\Search\TestHarness\Results {
        $pm = $this->getMockConfigPluginManager(
            ['config' => $firstLast ? $this->getFirstLastConfig() : []]
        );
        $options = new \VuFindTest\Search\TestHarness\Options($pm);
        $params = new \VuFindTest\Search\TestHarness\Params($options, $pm);
        $params->setPage($page);
        $params->setLimit($limit);
        if (null !== $sort) {
            $params->setSort($sort, true);
        }
        $ss = $this->getMockBuilder(\VuFindSearch\Service::class)
            ->disableOriginalConstructor()->getMock();
        $rl = $this->getMockBuilder(\VuFind\Record\Loader::class)
            ->disableOriginalConstructor()->getMock();
        $results = new \VuFindTest\Search\TestHarness\Results(
            $params,
            $ss,
            $rl,
            $total
        );
        return $results;
    }

    /**
     * Get mock result scroller
     *
     * @param \VuFind\Search\Base\Results $results restoreSearch results (null to ignore)
     *
     * @return ResultScroller
     */
    protected function getMockResultScroller($results): ResultScroller
    {
        $mockManager = $this->getMockBuilder(
            \VuFind\Search\Results\PluginManager::class
        )->disableOriginalConstructor()->getMock();
        $mockMemory = $this->getMockBuilder(\VuFind\Search\Memory::class)
            ->disableOriginalConstructor()->getMock();
        $mockMemory->expects($this->any())
            ->method('getLastSearchId')
            ->willReturn(-123);
        $params = [
            new Container('test'),
            $mockManager,
            $mockMemory,
            true,
        ];
        // Create an anonymous class to stub out some behavior:
        $resultScroller = new class (...$params) extends ResultScroller {
            /**
             * Search results to return
             *
             * @var \VuFind\Search\Base\Results
             */
            protected $testResults;

            /**
             * Set results to remember for restoreSearch
             *
             * @param \VuFind\Search\Base\Results $testResults Results
             *
             * @return void
             */
            public function setResults(?\VuFind\Search\Base\Results $testResults): void
            {
                $this->testResults = $testResults;
            }

            /**
             * Stubbed
             *
             * @param int $searchId Search ID
             *
             * @return ?\VuFind\Search\Base\Results
             */
            protected function restoreSearch(int $searchId): ?\VuFind\Search\Base\Results
            {
                return $this->testResults->getSearchId() === $searchId
                    ? $this->testResults : null;
            }

            /**
             * Stubbed
             *
             * @param \VuFind\Search\Base\Results $search Search object to remember.
             *
             * @return void
             */
            protected function rememberSearch($search)
            {
                // Do nothing
            }
        };

        $resultScroller->setResults($results);
        return $resultScroller;
    }
}
